<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

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

    #[\Override]
    protected function _beforeSave()
    {
        $this->_prepareData();
        return parent::_beforeSave();
    }

    /**
     * Prepare customer group data
     *
     * @return $this
     */
    protected function _prepareData()
    {
        $this->setCode(
            substr($this->getCode(), 0, self::GROUP_CODE_MAX_LENGTH),
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

}
