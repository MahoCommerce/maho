<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Address _getResource()
 * @method Mage_Sales_Model_Resource_Order_Address getResource()
 * @method Mage_Sales_Model_Resource_Order_Address_Collection getCollection()
 *
 * @method string getAddressType()
 * @method $this setAddressType(string $value)
 *
 * @method string getCity()
 * @method $this setCity(string $value)
 * @method string getCompany()
 * @method $this setCompany(string $value)
 * @method string getCountryId()
 * @method $this setCountryId(string $value)
 * @method Mage_Customer_Model_Address getCustomerAddress()
 * @method $this setCustomerAddress(Mage_Customer_Model_Address $value)
 * @method int getCustomerAddressId()
 * @method $this setCustomerAddressId(int $value)
 * @method int getCustomerId()
 * @method $this setCustomerId(int $value)
 *
 * @method string getEmail()
 * @method $this setEmail(string $value)
 *
 * @method string getFax()
 * @method $this setFax(string $value)
 * @method string getFirstname()
 * @method $this setFirstname(string $value)
 *
 * @method string getLastname()
 * @method $this setLastname(string $value)
 *
 * @method string getMiddlename()
 * @method $this setMiddlename(string $value)
 *
 * @method int getParentId()
 * @method $this setParentId(int $value)
 * @method string getPostcode()
 * @method $this setPostcode(string $value)
 * @method string getPrefix()
 * @method $this setPrefix(string $value)
 *
 * @method int getQuoteAddressId()
 * @method $this setQuoteAddressId(int $value)
 *
 * @method $this setRegionId(int $value)
 * @method $this setRegion(string $value)
 *
 * @method bool getSameAsBilling()
 * @method $this setSameAsBilling(bool $value)
 * @method $this getStoreId(int $value)
 * @method string getSuffix()
 * @method $this setSuffix(string $value)
 *
 * @method string getTelephone()
 * @method $this setTelephone(string $value)
 */
class Mage_Sales_Model_Order_Address extends Mage_Customer_Model_Address_Abstract
{
    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    protected $_eventPrefix = 'sales_order_address';
    protected $_eventObject = 'address';

    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_address');
    }

    /**
     * Init mapping array of short fields to its full names
     *
     * @return $this
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Set order
     *
     * @return $this
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        return $this;
    }

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = Mage::getModel('sales/order')->load($this->getParentId());
        }
        return $this->_order;
    }

    /**
     * Before object save manipulations
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getParentId() && $this->getOrder()) {
            $this->setParentId($this->getOrder()->getId());
        }

        // Init customer address id if customer address is assigned
        if ($this->getCustomerAddress()) {
            $this->setCustomerAddressId($this->getCustomerAddress()->getId());
        }

        return $this;
    }
}
