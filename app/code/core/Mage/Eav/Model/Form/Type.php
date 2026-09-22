<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Form_Type _getResource()
 * @method Mage_Eav_Model_Resource_Form_Type getResource()
 * @method Mage_Eav_Model_Resource_Form_Type_Collection getCollection()
 */
class Mage_Eav_Model_Form_Type extends Mage_Core_Model_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'eav_form_type';

    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/form_type');
    }

    /**
     * Retrieve assigned Eav Entity types
     *
     * @return array
     */
    public function getEntityTypes()
    {
        if (!$this->hasData('entity_types')) {
            $this->setData('entity_types', $this->_getResource()->getEntityTypes($this));
        }
        return $this->_getData('entity_types');
    }

    /**
     * Set assigned Eav Entity types
     *
     * @return $this
     */
    public function setEntityTypes(array $entityTypes)
    {
        $this->setData('entity_types', $entityTypes);
        return $this;
    }

    /**
     * Assign Entity Type to Form Type
     *
     * @param int $entityTypeId
     * @return $this
     */
    public function addEntityType($entityTypeId)
    {
        $entityTypes = $this->getEntityTypes();
        if (!empty($entityTypeId) && !in_array($entityTypeId, $entityTypes)) {
            $entityTypes[] = $entityTypeId;
            $this->setEntityTypes($entityTypes);
        }
        return $this;
    }

    /**
     * Copy Form Type properties from skeleton form type
     *
     * @return $this
     */
    public function createFromSkeleton(Mage_Eav_Model_Form_Type $skeleton)
    {
        $fieldsetCollection = Mage::getModel('eav/form_fieldset')->getCollection()
            ->addTypeFilter($skeleton)
            ->setSortOrder();
        $elementCollection = Mage::getModel('eav/form_element')->getCollection()
            ->addTypeFilter($skeleton)
            ->setSortOrder();

        // copy fieldsets
        $fieldsetMap = [];
        foreach ($fieldsetCollection as $skeletonFieldset) {
            /** @var Mage_Eav_Model_Form_Fieldset $skeletonFieldset */
            $fieldset = Mage::getModel('eav/form_fieldset');
            $fieldset->setTypeId($this->getId())
                ->setCode($skeletonFieldset->getCode())
                ->setLabels($skeletonFieldset->getLabels())
                ->setSortOrder($skeletonFieldset->getSortOrder())
                ->save();
            $fieldsetMap[$skeletonFieldset->getId()] = $fieldset->getId();
        }

        // copy elements
        foreach ($elementCollection as $skeletonElement) {
            /** @var Mage_Eav_Model_Form_Element $skeletonElement */
            $element = Mage::getModel('eav/form_element');
            $fieldsetId = null;
            if ($skeletonElement->getFieldsetId()) {
                $fieldsetId = $fieldsetMap[$skeletonElement->getFieldsetId()];
            }
            $element->setTypeId($this->getId())
                ->setFieldsetId($fieldsetId)
                ->setAttributeId($skeletonElement->getAttributeId())
                ->setSortOrder($skeletonElement->getSortOrder());
        }

        return $this;
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getIsSystem(): ?bool
    {
        $value = $this->getData('is_system');
        return $value === null ? null : (bool) $value;
    }

    public function setIsSystem(?bool $value = true): static
    {
        return $this->setData('is_system', $value);
    }

    public function getLabel(): ?string
    {
        $value = $this->getData('label');
        return $value === null ? null : (string) $value;
    }

    public function setLabel(?string $value): static
    {
        return $this->setData('label', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTheme(): ?string
    {
        $value = $this->getData('theme');
        return $value === null ? null : (string) $value;
    }

    public function setTheme(?string $value): static
    {
        return $this->setData('theme', $value);
    }

}
