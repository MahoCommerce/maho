<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Customer group model
 *
 * @package    Mage_Customer
 *
 * @method Mage_Customer_Model_Resource_Group _getResource()
 * @method Mage_Customer_Model_Resource_Group getResource()
 * @method Mage_Customer_Model_Resource_Group_Collection getCollection()
 * @method Mage_Customer_Model_Resource_Group_Collection getResourceCollection()
 */
class Mage_Customer_Model_Group extends Mage_Core_Model_Abstract
{
    /**
     * Xml config path for create account default group
     */
    public const XML_PATH_DEFAULT_ID       = 'customer/create_account/default_group';

    public const NOT_LOGGED_IN_ID          = 0;
    public const CUST_GROUP_ALL            = 32000;

    public const ENTITY                    = 'customer_group';

    public const GROUP_CODE_MAX_LENGTH     = 32;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'customer_group';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'object';

    protected static $_taxClassIds = [];

    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/group');
    }

    /**
     * Alias for setCustomerGroupCode
     *
     * @param string $value
     * @return static
     */
    public function setCode($value)
    {
        return $this->setCustomerGroupCode($value);
    }

    /**
     * Alias for getCustomerGroupCode
     *
     * @return string
     */
    public function getCode()
    {
        return $this->getCustomerGroupCode();
    }

    /**
     * @param int|null $groupId
     * @return int
     */
    public function getTaxClassId($groupId = null)
    {
        if (!is_null($groupId)) {
            if (empty(self::$_taxClassIds[$groupId])) {
                $this->load($groupId);
                self::$_taxClassIds[$groupId] = $this->getData('tax_class_id');
            }
            $this->setData('tax_class_id', self::$_taxClassIds[$groupId]);
        }
        return $this->getData('tax_class_id');
    }

    /**
     * @return bool
     */
    public function usesAsDefault()
    {
        $data = Mage::getConfig()->getStoresConfigByPath(self::XML_PATH_DEFAULT_ID);
        if (in_array($this->getId(), $data)) {
            return true;
        }
        return false;
    }

    /**
     * Processing data save after transaction commit
     *
     * @return $this
     */
    #[\Override]
    public function afterCommitCallback()
    {
        parent::afterCommitCallback();
        Mage::getSingleton('index/indexer')->processEntityAction(
            $this,
            self::ENTITY,
            Mage_Index_Model_Event::TYPE_SAVE,
        );
        return $this;
    }

    /**
     * Check the code and the tax class. A code that another group uses is a conflict of the resource model.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when the change renames NOT LOGGED IN
     */
    #[\Override]
    protected function _beforeSave()
    {
        $helper = Mage::helper('customer');
        $errors = new Mage_Core_Exception_Input();

        $code = trim((string) $this->getCode());
        if ($code === '') {
            $errors->addError('code', $helper->__('Customer group code is required.'));
        } elseif (mb_strlen($code) > self::GROUP_CODE_MAX_LENGTH) {
            $errors->addError('code', $helper->__('Customer group code must not exceed %d characters.', self::GROUP_CODE_MAX_LENGTH));
        }
        $this->setCode($code);
        // The admin form does not let an admin rename the group of the guests, so no other write may
        if ($this->getOrigData('customer_group_id') !== null
            && (int) $this->getId() === self::NOT_LOGGED_IN_ID
            && $this->dataHasChangedFor('customer_group_code')
        ) {
            throw new Mage_Core_Exception_Conflict($helper->__('The NOT LOGGED IN group cannot be renamed.'));
        }

        // Accept a stored tax class, because an admin can delete it after the group is saved
        $taxClassId = (int) $this->getData('tax_class_id');
        if ($this->getId() === null || $this->dataHasChangedFor('tax_class_id')) {
            $taxClass = $taxClassId === 0 ? null : Mage::getModel('tax/class')->load($taxClassId);
            if ($taxClass === null) {
                $errors->addError('tax_class_id', $helper->__('Customer tax class is required.'));
            } elseif ($taxClass->getClassType() !== Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER) {
                $errors->addError('tax_class_id', $helper->__('Customer tax class not found with ID: %s', $taxClassId));
            }
        }

        $this->addAttributeSetError($errors, 'customer_attribute_set_id', $this->getCustomerAttributeSetId(), 'customer');
        $this->addAttributeSetError($errors, 'customer_address_attribute_set_id', $this->getCustomerAddressAttributeSetId(), 'customer_address');
        $errors->throwIfErrors();

        $this->_prepareData();
        return parent::_beforeSave();
    }

    /**
     * Add an error for $field to $errors when $setId is not a set of the entity type.
     */
    protected function addAttributeSetError(Mage_Core_Exception_Input $errors, string $field, ?int $setId, string $entityTypeCode): void
    {
        if (!$setId) {
            return;
        }
        try {
            Mage::helper('eav')->assertSetOfType($setId, Mage::getSingleton('eav/config')->getEntityType($entityTypeCode));
        } catch (Mage_Core_Exception $e) {
            $errors->addError($field, $e->getMessage());
        }
    }

    #[\Deprecated(message: 'use addAttributeSetError(), which records the problem of each attribute set', since: '26.11')]
    protected function validateAttributeSet(?int $setId, string $entityTypeCode): void
    {
        if ($setId) {
            Mage::helper('eav')->assertSetOfType($setId, Mage::getSingleton('eav/config')->getEntityType($entityTypeCode));
        }
    }

    /**
     * Prepare customer group data
     *
     * @return $this
     */
    protected function _prepareData()
    {
        $this->setCode(
            mb_substr((string) $this->getCode(), 0, self::GROUP_CODE_MAX_LENGTH),
        );
        return $this;
    }

    public function getCustomerGroupCode(): ?string
    {
        $value = $this->getData('customer_group_code');
        return $value === null ? null : (string) $value;
    }

    public function setCustomerGroupCode(?string $value): static
    {
        return $this->setData('customer_group_code', $value);
    }

    public function setTaxClassId(?int $value): static
    {
        return $this->setData('tax_class_id', $value);
    }

    public function getCustomerAttributeSetId(): ?int
    {
        $value = $this->getData('customer_attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerAttributeSetId(?int $value): static
    {
        return $this->setData('customer_attribute_set_id', $value);
    }

    public function getCustomerAddressAttributeSetId(): ?int
    {
        $value = $this->getData('customer_address_attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerAddressAttributeSetId(?int $value): static
    {
        return $this->setData('customer_address_attribute_set_id', $value);
    }
}
