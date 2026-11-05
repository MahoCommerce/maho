<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * XML path to input types validator data in config
     */
    public const XML_PATH_VALIDATOR_DATA_INPUT_TYPES = 'general/validator_data/input_types';

    #[\Override]
    protected $_moduleName = 'Mage_Eav';

    protected $_attributesLockedFields = [];

    protected $_entityTypeFrontendClasses = [];

    /**
     * Return default frontend classes value labal array
     *
     * @return array
     */
    protected function _getDefaultFrontendClasses()
    {
        return [
            [
                'value' => '',
                'label' => Mage::helper('eav')->__('None'),
            ],
            [
                'value' => 'validate-number',
                'label' => Mage::helper('eav')->__('Decimal Number'),
            ],
            [
                'value' => 'validate-digits',
                'label' => Mage::helper('eav')->__('Integer Number'),
            ],
            [
                'value' => 'validate-email',
                'label' => Mage::helper('eav')->__('Email'),
            ],
            [
                'value' => 'validate-url',
                'label' => Mage::helper('eav')->__('URL'),
            ],
            [
                'value' => 'validate-alpha',
                'label' => Mage::helper('eav')->__('Letters'),
            ],
            [
                'value' => 'validate-alphanum',
                'label' => Mage::helper('eav')->__('Letters (a-z, A-Z) or Numbers (0-9)'),
            ],
        ];
    }

    /**
     * Return merged default and entity type frontend classes value label array
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getFrontendClasses($entityTypeCode)
    {
        $_defaultClasses = $this->_getDefaultFrontendClasses();
        if (isset($this->_entityTypeFrontendClasses[$entityTypeCode])) {
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode],
            );
        }
        $_entityTypeClasses = Mage::app()->getConfig()
            ->getNode('global/eav_frontendclasses/' . $entityTypeCode);
        if ($_entityTypeClasses) {
            foreach ($_entityTypeClasses->children() as $item) {
                $this->_entityTypeFrontendClasses[$entityTypeCode][] = [
                    'value' => (string) $item->value,
                    'label' => (string) $item->label,
                ];
            }
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode],
            );
        }
        return $_defaultClasses;
    }

    /**
     * Retrieve attributes locked fields to edit
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getAttributeLockedFields($entityTypeCode)
    {
        if (!$entityTypeCode) {
            return [];
        }
        if (isset($this->_attributesLockedFields[$entityTypeCode])) {
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        $_data = Mage::app()->getConfig()->getNode('global/eav_attributes/' . $entityTypeCode);
        if ($_data) {
            foreach ($_data->children() as $attribute) {
                $this->_attributesLockedFields[$entityTypeCode][(string) $attribute->code]
                    = array_keys($attribute->locked_fields->asArray());
            }
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        return [];
    }

    /**
     * Get input types validator data
     *
     * @return array
     */
    public function getInputTypesValidatorData()
    {
        return Mage::getStoreConfig(self::XML_PATH_VALIDATOR_DATA_INPUT_TYPES);
    }

    /**
     * Return the attributes of an entity type that the admin attribute pages show.
     *
     * The collection leaves out the attributes that the platform hides: those with is_visible = 0
     * in a catalog table, and in a customer table the hidden system attributes and the attributes
     * with is_visible = 0 that no admin created. An admin sets is_visible = 0 with "Show in Forms".
     * When the type sets user_defined_only, or sets_user_defined_only for the set editor ($forSets),
     * the collection holds only the attributes that an admin added.
     */
    public function getManagedAttributeCollection(Mage_Eav_Model_Entity_Type $entityType, bool $forSets = false): Mage_Eav_Model_Resource_Entity_Attribute_Collection
    {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel($entityType->getEntityAttributeCollection());
        $collection->setEntityTypeFilter((int) $entityType->getId());
        $settings = $this->getAdminPageSettings($entityType);
        if (($settings['user_defined_only'] ?? false) || ($forSets && ($settings['sets_user_defined_only'] ?? false))) {
            $collection->addFieldToFilter('main_table.is_user_defined', 1);
        }
        if ($collection instanceof Mage_Eav_Model_Resource_Attribute_Collection) {
            $collection->addSystemHiddenFilter()->addFieldToFilter(['is_visible', 'is_user_defined'], [1, 1]);
        } elseif (method_exists($collection, 'addVisibleFilter')) {
            $collection->addVisibleFilter();
        }
        return $collection;
    }

    /**
     * Return the settings of the admin attribute pages of $entityType, or null when System > Attributes
     * does not manage the type. See Mage_Eav_Model_Entity_Attribute::getAdminPageSettings().
     *
     * @return array{acl: string, title: string, sets_title: ?string, main_tab?: string, allowed_tags?: list<string>, user_defined_only?: bool, sets_user_defined_only?: bool, add_to_default_set?: bool}|null
     */
    public function getAdminPageSettings(Mage_Eav_Model_Entity_Type $entityType): ?array
    {
        $attribute = Mage::getModel((string) $entityType->getAttributeModel());
        if (!$attribute instanceof Mage_Eav_Model_Entity_Attribute) {
            return null;
        }
        return $attribute->setEntityTypeId((int) $entityType->getId())->getAdminPageSettings();
    }

    /**
     * Split $attributes by the groups of attribute set $setId, in the order of the groups. Each
     * group keeps the order of $attributes.
     *
     * The first part holds the attributes of the first group and the attributes that are not in the
     * set, with no label, because they join the fields that the page shows itself. Each other group
     * that holds an attribute follows, with its label in $storeId.
     *
     * @template T of Mage_Eav_Model_Entity_Attribute_Abstract
     * @param list<T> $attributes
     * @return list<array{id: ?int, label: ?string, attributes: list<T>}>
     */
    public function groupAttributesBySet(array $attributes, ?int $setId, ?int $storeId = null): array
    {
        if (!$setId || !$attributes) {
            return [['id' => null, 'label' => null, 'attributes' => $attributes]];
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $groupOf = $adapter->fetchPairs(
            $adapter->select()
                ->from($resource->getTableName('eav/entity_attribute'), ['attribute_id', 'attribute_group_id'])
                ->where('attribute_set_id = ?', $setId),
        );
        $groups = Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->addStoreLabel($storeId ?? (int) Mage::app()->getStore()->getId());

        $firstGroupId = null;
        $parts = [];
        foreach ($groups as $group) {
            $firstGroupId ??= (int) $group->getId();
            $parts[(int) $group->getId()] = ['id' => (int) $group->getId(), 'label' => $group->getStoreLabel(), 'attributes' => []];
        }
        $first = ['id' => $firstGroupId, 'label' => null, 'attributes' => []];
        foreach ($attributes as $attribute) {
            $groupId = (int) ($groupOf[$attribute->getId()] ?? 0);
            if ($groupId && $groupId !== $firstGroupId && isset($parts[$groupId])) {
                $parts[$groupId]['attributes'][] = $attribute;
            } else {
                $first['attributes'][] = $attribute;
            }
        }

        $result = $first['attributes'] ? [$first] : [];
        foreach ($parts as $part) {
            if ($part['attributes']) {
                $result[] = $part;
            }
        }
        return $result;
    }
}
