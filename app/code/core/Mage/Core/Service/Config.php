<?php

/**
 * Read the fields of System > Configuration, and save and delete their values, for the admin, the API and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Mage_Adminhtml_Model_Config_Data as ConfigData;

/**
 * @phpstan-type FieldInfo array{
 *     path: string,
 *     section: string,
 *     targetSection: string,
 *     group: string,
 *     field: string,
 *     label: string,
 *     groupLabel: string,
 *     sectionLabel: string,
 *     comment: string,
 *     frontendType: ?string,
 *     backendModel: ?string,
 *     sourceModel: ?string,
 *     showInDefault: bool,
 *     showInWebsite: bool,
 *     showInStore: bool,
 * }
 */
class Mage_Core_Service_Config
{
    private const SENSITIVE_NAME_PATTERN = '/key|secret|password|token|salt/i';
    private const SENSITIVE_FRONTEND_TYPES = ['obscure', 'password'];

    /**
     * Every labelled field of system.xml, by its configuration path.
     *
     * @return array<string, FieldInfo>
     */
    public function getFields(): array
    {
        $fields = [];
        foreach (Mage::getSingleton('adminhtml/config')->getSections()->children() as $sectionCode => $section) {
            if (!isset($section->groups)) {
                continue;
            }
            foreach ($section->groups->children() as $groupCode => $group) {
                $this->collectGroup($fields, (string) $sectionCode, $this->label($section), (string) $groupCode, $group);
            }
        }
        return $fields;
    }

    /**
     * @return FieldInfo
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getField(string $path): array
    {
        return $this->findField(trim($path, '/'))
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__("Configuration setting '%s' not found", $path));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $configId): Mage_Core_Model_Config_Data
    {
        $row = Mage::getModel('core/config_data')->load($configId);
        if (!$row->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('Configuration with ID %d not found', $configId));
        }
        return $row;
    }

    /**
     * @param FieldInfo $field
     */
    public function isShownAtScope(array $field, string $scope): bool
    {
        return match ($scope) {
            ConfigData::SCOPE_WEBSITES => $field['showInWebsite'],
            ConfigData::SCOPE_STORES => $field['showInStore'],
            default => $field['showInDefault'],
        };
    }

    /**
     * Whether the field node $fieldNode of system.xml shows at $scope. A node without a label counts too:
     * the admin form sends it.
     */
    public function isNodeShownAtScope(\Maho\Simplexml\Element $fieldNode, string $scope): bool
    {
        return (bool) (int) match ($scope) {
            ConfigData::SCOPE_WEBSITES => $fieldNode->show_in_website,
            ConfigData::SCOPE_STORES => $fieldNode->show_in_store,
            default => $fieldNode->show_in_default,
        };
    }

    /**
     * @return string|null the path that the field node $fieldNode names in config_path, when it stores its
     *                     value outside its own group
     */
    public function getConfigPath(\Maho\Simplexml\Element $fieldNode): ?string
    {
        $configPath = isset($fieldNode->config_path) ? trim((string) $fieldNode->config_path, '/') : '';
        return str_contains($configPath, '/') ? $configPath : null;
    }

    /**
     * The sections that a client needs access to for $field: the section that shows the field, and the
     * section that holds its value when config_path names another one.
     *
     * @param FieldInfo $field
     * @return list<string>
     */
    public function getAclSections(array $field): array
    {
        return array_values(array_unique([$field['section'], $field['targetSection']]));
    }

    /**
     * Whether the value of $field is a secret, which a reader never gets.
     *
     * @param FieldInfo $field
     */
    public function isSensitive(array $field): bool
    {
        if (in_array($field['frontendType'], self::SENSITIVE_FRONTEND_TYPES, true)
            || preg_match(self::SENSITIVE_NAME_PATTERN, $field['field']) === 1
        ) {
            return true;
        }
        return $field['backendModel'] !== null && is_a(
            Mage::getConfig()->getModelClassName($field['backendModel']),
            Mage_Adminhtml_Model_System_Config_Backend_Encrypted::class,
            true,
        );
    }

    /**
     * Save the fields in $groups of the section $section, as the admin form sends them, at the website
     * code $website or the store view code $store, or at the default scope when both are empty.
     *
     * @param array<string, array{fields?: array<string, array<string, mixed>>}> $groups
     * @throws Mage_Core_Exception
     */
    public function saveSection(string $section, string $website, string $store, array $groups): void
    {
        Mage::getSingleton('adminhtml/config_data')
            ->setSection($section)
            ->setWebsite($website)
            ->setStore($store)
            ->setGroupsSelector($groups)
            ->save();

        $this->afterChange($section, $website ?: null, $store ?: null);
    }

    /**
     * Save $value at one scope through the backend model of the field, so a secret is encrypted. A path that no
     * field of system.xml declares is refused, as in the admin, unless $allowUndeclared: then it is saved as it is.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when no field declares the path and $allowUndeclared is false
     * @throws Mage_Core_Exception_Input when the scope does not exist or the field does not show at it
     * @throws Mage_Core_Exception when the backend model refuses the value
     */
    public function save(
        string $path,
        ?string $value,
        string $scope = ConfigData::SCOPE_DEFAULT,
        int $scopeId = 0,
        bool $allowUndeclared = false,
    ): void {
        $this->saveAll([[$path, $value, $scope, $scopeId]], $allowUndeclared);
    }

    /**
     * Save each value of $values in order, as save() does. Then reload the configuration and tell the observers
     * once for each section and scope that changed, so an import of many values does not do it for each value.
     *
     * @param iterable<array{0: string, 1: ?string, 2: string, 3: int}> $values the path, the value, the scope and
     *                                                                         the scope ID of each value
     * @throws Mage_Core_Exception_NoSuchEntity when no field declares a path and $allowUndeclared is false
     * @throws Mage_Core_Exception_Input when a scope does not exist or a field does not show at it
     * @throws Mage_Core_Exception when a backend model refuses a value
     */
    public function saveAll(iterable $values, bool $allowUndeclared = false): void
    {
        $changes = [];
        foreach ($values as [$path, $value, $scope, $scopeId]) {
            $field = $this->assertWritable($path, $scope, $scopeId, $allowUndeclared);
            Mage::getModel('adminhtml/config_data')->saveValue($path, $value, $scope, $scopeId);
            $section = $field['section'] ?? explode('/', $path)[0];
            $changes["{$section}/{$scope}/{$scopeId}"] = [$section, $scope, $scopeId];
        }
        foreach ($changes as [$section, $scope, $scopeId]) {
            $this->afterChange($section, ...$this->scopeCodes($scope, $scopeId));
        }
    }

    /**
     * Check that $path can be saved at the scope, and return its field. Return null for a path that no field
     * declares when $allowUndeclared.
     *
     * @return FieldInfo|null
     * @throws Mage_Core_Exception_NoSuchEntity when no field declares the path and $allowUndeclared is false
     * @throws Mage_Core_Exception_Input when the scope does not exist or the field does not show at it
     */
    public function assertWritable(string $path, string $scope, int $scopeId, bool $allowUndeclared = false): ?array
    {
        $field = $allowUndeclared ? $this->findField($path) : $this->getField($path);
        $this->assertScope($scope, $scopeId);
        if ($field !== null && !$this->isShownAtScope($field, $scope)) {
            throw new Mage_Core_Exception_Input()
                ->addError('scope', Mage::helper('core')->__("Field '%s' cannot be set at scope '%s'", $path, $scope));
        }
        return $field;
    }

    /**
     * Delete the own value of $path at one scope through the backend model of the field. A website
     * or a store view then uses the value of its parent scope again. Return false when the scope has no own value.
     */
    public function delete(string $path, string $scope, int $scopeId): bool
    {
        if (!Mage::getModel('adminhtml/config_data')->deleteValue($path, $scope, $scopeId)) {
            return false;
        }
        $this->afterChange($this->getFields()[$path]['section'] ?? explode('/', $path)[0], ...$this->scopeCodes($scope, $scopeId));
        return true;
    }

    /**
     * Reload the configuration and tell the observers of the section, as after a save in the admin.
     */
    protected function afterChange(string $section, ?string $website, ?string $store): void
    {
        if ($section === 'advanced') {
            // The Disable Modules Output fields change the layout
            Mage::app()->cleanCache(['layout', Mage_Core_Model_Layout_Update::LAYOUT_GENERAL_CACHE_TAG]);
        }
        Mage::getConfig()->reinit();
        Mage::dispatchEvent('admin_system_config_section_save_after', [
            'website' => $website,
            'store' => $store,
            'section' => $section,
        ]);
        Mage::app()->reinitStores();
        Mage::dispatchEvent("admin_system_config_changed_section_{$section}", ['website' => $website, 'store' => $store]);
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function assertScope(string $scope, int $scopeId): void
    {
        $helper = Mage::helper('core');
        [$field, $error] = match ($scope) {
            ConfigData::SCOPE_DEFAULT => ['scope_id', $scopeId === 0 ? null : $helper->__('The default scope has the scope ID 0.')],
            ConfigData::SCOPE_WEBSITES => ['scope_id', $this->hasId('core/website', 'website_id', $scopeId)
                ? null : $helper->__('The website with the ID %d does not exist.', $scopeId)],
            ConfigData::SCOPE_STORES => ['scope_id', $this->hasId('core/store', 'store_id', $scopeId)
                ? null : $helper->__('The store view with the ID %d does not exist.', $scopeId)],
            default => ['scope', $helper->__('The scope must be default, websites or stores.')],
        };
        if ($error !== null) {
            throw new Mage_Core_Exception_Input()->addError($field, $error);
        }
    }

    /**
     * Read all the IDs of $table, because a WHERE with an ID out of the column range fails the query on PostgreSQL.
     */
    private function hasId(string $table, string $column, int $id): bool
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $ids = $adapter->fetchCol($adapter->select()->from($resource->getTableName($table), [$column]));
        return in_array($id, array_map(intval(...), $ids), true);
    }

    /**
     * @return FieldInfo|null the field of $path in system.xml, or in a group that makes its fields when the admin
     *                        renders it, such as Disable Modules Output
     */
    private function findField(string $path): ?array
    {
        $field = $this->getFields()[$path] ?? null;
        if ($field !== null) {
            return $field;
        }
        $parts = explode('/', $path);
        if (count($parts) !== 3) {
            return null;
        }
        [$sectionCode, $groupCode, $fieldCode] = $parts;
        $section = Mage::getSingleton('adminhtml/config')->getSections()->{$sectionCode};
        $group = $section?->groups?->{$groupCode};
        // The admin saves any field of a dynamic group, so the service accepts it too
        if (!$group || !(int) $group->dynamic_group) {
            return null;
        }
        return [
            'path' => $path,
            'section' => $sectionCode,
            'targetSection' => $sectionCode,
            'group' => $groupCode,
            'field' => $fieldCode,
            'label' => $fieldCode,
            'groupLabel' => $this->label($group),
            'sectionLabel' => $this->label($section),
            'comment' => '',
            'frontendType' => 'select',
            'backendModel' => null,
            'sourceModel' => null,
            'showInDefault' => (bool) (int) $group->show_in_default,
            'showInWebsite' => (bool) (int) $group->show_in_website,
            'showInStore' => (bool) (int) $group->show_in_store,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?string} the website code and the store view code of the scope, for the events
     */
    private function scopeCodes(string $scope, int $scopeId): array
    {
        return match ($scope) {
            ConfigData::SCOPE_WEBSITES => [Mage::getModel('core/website')->load($scopeId)->getCode(), null],
            ConfigData::SCOPE_STORES => [null, Mage::getModel('core/store')->load($scopeId)->getCode()],
            default => [null, null],
        };
    }

    /**
     * @param array<string, FieldInfo> $fields
     */
    private function collectGroup(array &$fields, string $section, string $sectionLabel, string $group, \Maho\Simplexml\Element $groupNode): void
    {
        if (!isset($groupNode->fields)) {
            return;
        }
        $groupLabel = $this->label($groupNode);
        foreach ($groupNode->fields->children() as $fieldCode => $fieldNode) {
            // A nested group sits among the fields of its parent with type="group"
            if ((string) $fieldNode->attributes()->type === 'group') {
                $this->collectGroup($fields, $section, $sectionLabel, (string) $fieldCode, $fieldNode);
                continue;
            }
            if (!isset($fieldNode->label)) {
                continue;
            }

            $path = $this->getConfigPath($fieldNode) ?? $section . '/' . $group . '/' . $fieldCode;

            $fields[$path] = [
                'path' => $path,
                'section' => $section,
                'targetSection' => explode('/', $path)[0],
                'group' => $group,
                'field' => (string) $fieldCode,
                'label' => $this->label($fieldNode),
                'groupLabel' => $groupLabel,
                'sectionLabel' => $sectionLabel,
                'comment' => isset($fieldNode->comment) ? trim(html_entity_decode(strip_tags((string) $fieldNode->comment))) : '',
                'frontendType' => isset($fieldNode->frontend_type) ? (string) $fieldNode->frontend_type : 'text',
                'backendModel' => isset($fieldNode->backend_model) ? (string) $fieldNode->backend_model : null,
                'sourceModel' => isset($fieldNode->source_model) ? (string) $fieldNode->source_model : null,
                'showInDefault' => $this->isNodeShownAtScope($fieldNode, ConfigData::SCOPE_DEFAULT),
                'showInWebsite' => $this->isNodeShownAtScope($fieldNode, ConfigData::SCOPE_WEBSITES),
                'showInStore' => $this->isNodeShownAtScope($fieldNode, ConfigData::SCOPE_STORES),
            ];
        }
    }

    private function label(\Maho\Simplexml\Element $node): string
    {
        return isset($node->label) ? trim((string) $node->label) : '';
    }
}
