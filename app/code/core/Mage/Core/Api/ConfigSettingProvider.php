<?php

/**
 * Reads the system.xml fields and their values at one scope.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @phpstan-type FieldInfo array{
 *     path: string,
 *     section: string,
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
 * @phpstan-type ScopeInfo array{scope: string, scopeId: int, scopeCode: ?string}
 */
final class ConfigSettingProvider extends \Maho\ApiPlatform\Provider
{
    private const SENSITIVE_NAME_PATTERN = '/key|secret|password|token|salt/i';
    private const SENSITIVE_FRONTEND_TYPES = ['obscure', 'password'];
    private const MAX_OPTIONS = 50;

    /** @var array<string, FieldInfo>|null */
    private ?array $fields = null;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $user = $this->requireUser();
        $filters = $this->requestFilters($context);
        $scope = $this->resolveScope(
            isset($filters['scope']) ? (string) $filters['scope'] : ConfigSetting::SCOPE_DEFAULT,
            isset($filters['scopeCode']) ? (string) $filters['scopeCode'] : null,
        );
        $this->assertScopeReadable($scope, $user);

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideSettings($filters, $scope, $user, $context);
        }

        $path = (string) ($uriVariables['path'] ?? $filters['path'] ?? '');
        $field = $this->field($path);
        if ($field === null) {
            throw new NotFoundHttpException("Configuration setting '$path' not found");
        }
        $this->assertSectionAllowed($field, $user);

        return $this->toSettingDto($field, $scope, $this->ownPaths($scope), withOptions: true);
    }

    /**
     * Query string, GraphQL args and MCP arguments all carry the same keys.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function requestFilters(array $context): array
    {
        $query = $context['request']?->query->all() ?? [];
        return ($context['args'] ?? []) + ($context['filters'] ?? []) + $query;
    }

    /**
     * @param array<string, mixed> $filters
     * @param ScopeInfo $scope
     * @return TraversablePaginator<ConfigSetting>
     */
    private function provideSettings(array $filters, array $scope, ApiUser $user, array $context): TraversablePaginator
    {
        $pathPrefix = isset($filters['pathPrefix']) ? ltrim((string) $filters['pathPrefix'], '/') : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';

        $matching = [];
        foreach ($this->fields() as $field) {
            if (!$this->isShownAtScope($field, $scope['scope'])) {
                continue;
            }
            if ($pathPrefix !== '' && !str_starts_with($field['path'], $pathPrefix)) {
                continue;
            }
            if ($search !== ''
                && stripos($field['path'], $search) === false
                && stripos($field['label'], $search) === false
                && stripos($field['comment'], $search) === false
            ) {
                continue;
            }
            if (!$this->isSectionAllowed($field['section'], $user)) {
                continue;
            }
            $matching[] = $field;
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(
            $context,
            $this->defaultPageSize,
            $this->maxPageSize,
        );

        $ownPaths = $this->ownPaths($scope);
        $items = [];
        foreach (array_slice($matching, ($page - 1) * $pageSize, $pageSize) as $field) {
            $items[] = $this->toSettingDto($field, $scope, $ownPaths);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($matching));
    }

    /**
     * @param FieldInfo $field
     * @param ScopeInfo $scope
     * @param array<string, true> $ownPaths Paths that have a row at this scope
     */
    public function toSettingDto(array $field, array $scope, array $ownPaths, bool $withOptions = false): ConfigSetting
    {
        $dto = new ConfigSetting();
        $dto->path = $field['path'];
        $dto->scope = $scope['scope'];
        $dto->scopeCode = $scope['scopeCode'];
        $dto->label = $field['label'];
        $dto->groupLabel = $field['groupLabel'];
        $dto->sectionLabel = $field['sectionLabel'];
        $dto->comment = $field['comment'] !== '' ? $field['comment'] : null;
        $dto->frontendType = $field['frontendType'];
        $dto->isSensitive = $this->isSensitive($field);
        $dto->inherited = $scope['scope'] !== ConfigSetting::SCOPE_DEFAULT && !isset($ownPaths[$field['path']]);
        $dto->value = $dto->isSensitive ? null : $this->valueAtScope($field['path'], $scope);
        $dto->options = $withOptions ? $this->options($field) : null;

        return $dto;
    }

    /**
     * @param ScopeInfo $scope
     */
    private function valueAtScope(string $path, array $scope): ?string
    {
        $value = match ($scope['scope']) {
            ConfigSetting::SCOPE_WEBSITES => \Mage::app()->getWebsite($scope['scopeId'])->getConfig($path),
            ConfigSetting::SCOPE_STORES => \Mage::getStoreConfig($path, $scope['scopeId']),
            default => \Mage::getConfig()->getNode('default/' . $path),
        };

        if ($value instanceof \Maho\Simplexml\Element) {
            $value = $value->hasChildren() ? null : (string) $value;
        }
        if ($value === false || $value === null || is_array($value)) {
            return null;
        }

        return (string) $value;
    }

    /**
     * Paths that have their own core_config_data row at the scope.
     *
     * @param ScopeInfo $scope
     * @return array<string, true>
     */
    public function ownPaths(array $scope): array
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_DEFAULT) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $select = $read->select()
            ->from($resource->getTableName('core/config_data'), ['path'])
            ->where('scope = ?', $scope['scope'])
            ->where('scope_id = ?', $scope['scopeId']);

        return array_fill_keys($read->fetchCol($select), true);
    }

    /**
     * @return ScopeInfo
     */
    public function resolveScope(string $scope, ?string $scopeCode): array
    {
        if ($scope === ConfigSetting::SCOPE_DEFAULT) {
            return ['scope' => $scope, 'scopeId' => 0, 'scopeCode' => null];
        }

        if ($scopeCode === null || $scopeCode === '') {
            throw new ValidationException("scopeCode is required for scope '$scope'", 'scopeCode', 'NotBlank');
        }

        if ($scope === ConfigSetting::SCOPE_WEBSITES) {
            try {
                $website = \Mage::app()->getWebsite($scopeCode);
            } catch (\Mage_Core_Exception) {
                $website = null;
            }
            if (!$website || !$website->getId()) {
                throw new ValidationException("Website '$scopeCode' not found", 'scopeCode', 'Invalid');
            }
            return ['scope' => $scope, 'scopeId' => (int) $website->getId(), 'scopeCode' => $website->getCode()];
        }

        if ($scope === ConfigSetting::SCOPE_STORES) {
            try {
                $store = \Mage::app()->getStore($scopeCode);
            } catch (\Mage_Core_Model_Store_Exception) {
                $store = null;
            }
            if (!$store || !$store->getId()) {
                throw new ValidationException("Store view '$scopeCode' not found", 'scopeCode', 'Invalid');
            }
            return ['scope' => $scope, 'scopeId' => (int) $store->getId(), 'scopeCode' => $store->getCode()];
        }

        throw new ValidationException('scope must be default, websites or stores', 'scope', 'Choice');
    }

    /**
     * A token with a store restriction reads only the store views it may access.
     *
     * @param ScopeInfo $scope
     */
    private function assertScopeReadable(array $scope, ApiUser $user): void
    {
        if ($scope['scope'] === ConfigSetting::SCOPE_STORES && !$user->canAccessStore($scope['scopeId'])) {
            throw new AccessDeniedHttpException('Token is not authorized for the requested store.');
        }
    }

    /**
     * @param FieldInfo $field
     */
    public function isShownAtScope(array $field, string $scope): bool
    {
        return match ($scope) {
            ConfigSetting::SCOPE_WEBSITES => $field['showInWebsite'],
            ConfigSetting::SCOPE_STORES => $field['showInStore'],
            default => $field['showInDefault'],
        };
    }

    /**
     * @param FieldInfo $field
     */
    public function isSensitive(array $field): bool
    {
        if (in_array($field['frontendType'], self::SENSITIVE_FRONTEND_TYPES, true)) {
            return true;
        }
        if (preg_match(self::SENSITIVE_NAME_PATTERN, $field['field']) === 1) {
            return true;
        }
        if ($field['backendModel'] !== null) {
            $class = \Mage::getConfig()->getModelClassName($field['backendModel']);
            if (is_a($class, \Mage_Adminhtml_Model_System_Config_Backend_Encrypted::class, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An admin token sees the sections that its admin role allows. A service token sees all.
     */
    public function isSectionAllowed(string $section, ApiUser $user): bool
    {
        if (!$user->isAdmin()) {
            return true;
        }

        try {
            return (bool) \Mage::getSingleton('admin/session')->isAllowed('admin/system/config/' . $section);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param FieldInfo $field
     */
    public function assertSectionAllowed(array $field, ApiUser $user): void
    {
        if (!$this->isSectionAllowed($field['section'], $user)) {
            throw new AccessDeniedHttpException(
                sprintf('Your admin role does not grant access to the configuration section "%s".', $field['section']),
            );
        }
    }

    /**
     * @return FieldInfo|null
     */
    public function field(string $path): ?array
    {
        return $this->fields()[trim($path, '/')] ?? null;
    }

    /**
     * Every labelled field of system.xml, keyed by its configuration path.
     *
     * @return array<string, FieldInfo>
     */
    public function fields(): array
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $this->fields = [];
        $sections = \Mage::getSingleton('adminhtml/config')->getSections();
        foreach ($sections->children() as $sectionCode => $section) {
            if (!isset($section->groups)) {
                continue;
            }
            $sectionLabel = $this->label($section);
            foreach ($section->groups->children() as $groupCode => $group) {
                $this->collectGroup((string) $sectionCode, $sectionLabel, (string) $groupCode, $group);
            }
        }

        return $this->fields;
    }

    private function collectGroup(string $section, string $sectionLabel, string $group, \Maho\Simplexml\Element $groupNode): void
    {
        if (!isset($groupNode->fields)) {
            return;
        }
        $groupLabel = $this->label($groupNode);
        foreach ($groupNode->fields->children() as $fieldCode => $fieldNode) {
            // A nested group sits among the fields of its parent with type="group"
            if ((string) $fieldNode->attributes()->type === 'group') {
                $this->collectGroup($section, $sectionLabel, (string) $fieldCode, $fieldNode);
                continue;
            }
            if (!isset($fieldNode->label)) {
                continue;
            }

            $configPath = isset($fieldNode->config_path) ? trim((string) $fieldNode->config_path, '/') : '';
            $path = $configPath !== '' && str_contains($configPath, '/')
                ? $configPath
                : $section . '/' . $group . '/' . $fieldCode;

            $this->fields[$path] = [
                'path' => $path,
                'section' => $section,
                'group' => $group,
                'field' => (string) $fieldCode,
                'label' => $this->label($fieldNode),
                'groupLabel' => $groupLabel,
                'sectionLabel' => $sectionLabel,
                'comment' => isset($fieldNode->comment) ? trim(html_entity_decode(strip_tags((string) $fieldNode->comment))) : '',
                'frontendType' => isset($fieldNode->frontend_type) ? (string) $fieldNode->frontend_type : 'text',
                'backendModel' => isset($fieldNode->backend_model) ? (string) $fieldNode->backend_model : null,
                'sourceModel' => isset($fieldNode->source_model) ? (string) $fieldNode->source_model : null,
                'showInDefault' => (bool) (int) $fieldNode->show_in_default,
                'showInWebsite' => (bool) (int) $fieldNode->show_in_website,
                'showInStore' => (bool) (int) $fieldNode->show_in_store,
            ];
        }
    }

    /**
     * The values of a select, multiselect or boolean field with their labels, as System >
     * Configuration shows them. Null when the field has no source model or more than MAX_OPTIONS values.
     *
     * @param FieldInfo $field
     * @return list<array{value: string, label: string}>|null
     */
    public function options(array $field): ?array
    {
        if ($field['frontendType'] === 'boolean') {
            return [
                ['value' => '1', 'label' => \Mage::helper('core')->__('Yes')],
                ['value' => '0', 'label' => \Mage::helper('core')->__('No')],
            ];
        }
        if ($field['sourceModel'] === null || !in_array($field['frontendType'], ['select', 'multiselect'], true)) {
            return null;
        }
        $factoryName = $field['sourceModel'];
        $method = null;
        if (preg_match('/^([^:]+?)::([^:]+?)$/', $factoryName, $matches)) {
            [, $factoryName, $method] = $matches;
        }
        $multiselect = $field['frontendType'] === 'multiselect';
        try {
            $source = \Mage::getSingleton($factoryName);
            if (!is_object($source)) {
                return null;
            }
            if ($source instanceof \Maho\DataObject) {
                $source->setPath($field['path']);
            }
            if ($method === null) {
                $raw = method_exists($source, 'toOptionArray') ? $source->toOptionArray($multiselect) : [];
            } else {
                $raw = $source->$method() ?? [];
                if (!$multiselect) {
                    $raw = array_map(static fn($value, $label): array => ['value' => $value, 'label' => $label], array_keys($raw), $raw);
                }
            }
        } catch (\Throwable) {
            return null;
        }

        $options = [];
        $this->flattenOptions(is_array($raw) ? $raw : [], $options);

        return $options === [] || count($options) > self::MAX_OPTIONS ? null : $options;
    }

    /**
     * Add the options in $raw to $options. An option group holds its options in its value.
     *
     * @param array<mixed> $raw
     * @param list<array{value: string, label: string}> $options
     */
    private function flattenOptions(array $raw, array &$options): void
    {
        foreach ($raw as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            if (is_array($option['value'])) {
                $this->flattenOptions($option['value'], $options);
                continue;
            }
            if (is_scalar($option['value'])) {
                $options[] = ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])];
            }
        }
    }

    private function label(\Maho\Simplexml\Element $node): string
    {
        return isset($node->label) ? trim((string) $node->label) : '';
    }
}
