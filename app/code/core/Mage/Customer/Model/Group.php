<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer group model
 *
 * @category   Mage
 * @package    Mage_Customer
 *
 * @method Mage_Customer_Model_Resource_Group _getResource()
 * @method Mage_Customer_Model_Resource_Group getResource()
 * @method Mage_Customer_Model_Resource_Group_Collection getCollection()
 * @method Mage_Customer_Model_Resource_Group_Collection getResourceCollection()
 *
 * @method string getCustomerGroupCode()
 * @method $this setCustomerGroupCode(string $value)
 * @method $this setTaxClassId(int $value)
 */
class Mage_Customer_Model_Group extends Mage_Core_Model_Abstract
{
    /**
     * Xml config path for create account default group
     */
    public const XML_PATH_DEFAULT_ID               = 'customer/create_account/default_group';

    public const ENTITY                            = 'customer_group';

    public const NOT_LOGGED_IN_ID                  = 0;
    public const DEFAULT_ATTRIBUTE_SET_ID          = 1;
    public const DEFAULT_ADDRESS_ATTRIBUTE_SET_ID  = 2;

    public const CUST_GROUP_ALL                    = 32000;

    public const GROUP_CODE_MAX_LENGTH             = 32;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'customer_group';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'object';

    /** @deprecated */
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
     * @return $this
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
            $taxClassId = $this->getResource()->loadGroupTableData($groupId)['tax_class_id'];
            self::$_taxClassIds[$groupId] = $taxClassId;
            return $taxClassId;
        }
        return $this->getData('tax_class_id');
    }

    /**
     * @param int|null $groupId
     * @return int
     */
    public function getCustomerAttributeSetId($groupId = null)
    {
        if (!is_null($groupId)) {
            return $this->getResource()->loadGroupTableData($groupId)['customer_attribute_set_id'];
        }
        return $this->getData('customer_attribute_set_id');
    }

    /**
     * @param int|null $groupId
     * @return int
     */
    public function getCustomerAddressAttributeSetId($groupId = null)
    {
        if (!is_null($groupId)) {
            return $this->getResource()->loadGroupTableData($groupId)['customer_address_attribute_set_id'];
        }
        return $this->getData('customer_address_attribute_set_id');
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
            Mage_Index_Model_Event::TYPE_SAVE
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
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
            substr($this->getCode(), 0, self::GROUP_CODE_MAX_LENGTH)
        );
        return $this;
    }
}
