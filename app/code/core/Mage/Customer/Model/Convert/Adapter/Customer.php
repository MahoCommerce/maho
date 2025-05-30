<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Model_Convert_Adapter_Customer extends Mage_Eav_Model_Convert_Adapter_Entity
{
    public const MULTI_DELIMITER = ' , ';

    /**
     * Customer model
     *
     * @var Mage_Customer_Model_Customer|string|null
     */
    protected $_customerModel;

    protected $_stores;
    protected $_attributes = [];
    protected $_customerGroups;

    protected $_billingAddressModel;
    protected $_shippingAddressModel;

    protected $_requiredFields = [];

    protected $_ignoreFields = [];

    protected $_billingFields = [];

    protected $_billingMappedFields = [];

    protected $_billingStreetFields = [];

    protected $_billingRequiredFields = [];

    protected $_shippingFields = [];

    protected $_shippingMappedFields = [];

    protected $_shippingStreetFields = [];

    protected $_shippingRequiredFields = [];

    protected $_addressFields = [];

    protected $_regions;
    protected $_websites;

    protected $_customer = null;
    protected $_address = null;

    protected $_customerId = '';

    /**
     * Retrieve customer model cache
     *
     * @return Mage_Customer_Model_Customer|object
     */
    public function getCustomerModel()
    {
        if (is_null($this->_customerModel)) {
            $object = Mage::getModel('customer/customer');
            $this->_customerModel = Mage::objects()->save($object);
        }
        return Mage::objects()->load($this->_customerModel);
    }

    /**
     * Retrieve customer address model cache
     *
     * @return Mage_Customer_Model_Address|object
     */
    public function getBillingAddressModel()
    {
        if (is_null($this->_billingAddressModel)) {
            $object = Mage::getModel('customer/address');
            $this->_billingAddressModel = Mage::objects()->save($object);
        }
        return Mage::objects()->load($this->_billingAddressModel);
    }

    /**
     * Retrieve customer address model cache
     *
     * @return Mage_Customer_Model_Address|object
     */
    public function getShippingAddressModel()
    {
        if (is_null($this->_shippingAddressModel)) {
            $object = Mage::getModel('customer/address');
            $this->_shippingAddressModel = Mage::objects()->save($object);
        }
        return Mage::objects()->load($this->_shippingAddressModel);
    }

    /**
     * Retrieve store object by code
     *
     * @param string $store
     * @return Mage_Core_Model_Store|false
     */
    public function getStoreByCode($store)
    {
        if (is_null($this->_stores)) {
            $this->_stores = Mage::app()->getStores(true, true);
        }
        return $this->_stores[$store] ?? false;
    }

    /**
     * Retrieve website model by code
     *
     * @param string $websiteCode
     * @return Mage_Core_Model_Website|false
     */
    public function getWebsiteByCode($websiteCode)
    {
        if (is_null($this->_websites)) {
            $this->_websites = Mage::app()->getWebsites(true, true);
        }
        return $this->_websites[$websiteCode] ?? false;
    }

    /**
     * Retrieve eav entity attribute model
     *
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttribute($code)
    {
        if (!isset($this->_attributes[$code])) {
            $this->_attributes[$code] = $this->getCustomerModel()->getResource()->getAttribute($code);
        }
        return $this->_attributes[$code];
    }

    /**
     * Retrieve region id by country code and region name (if exists)
     *
     * @param string $country
     * @param string $regionName
     * @return int
     */
    public function getRegionId($country, $regionName)
    {
        if (is_null($this->_regions)) {
            $this->_regions = [];

            $collection = Mage::getModel('directory/region')
                ->getCollection();
            /** @var Mage_Directory_Model_Region $region */
            foreach ($collection as $region) {
                if (!isset($this->_regions[$region->getCountryId()])) {
                    $this->_regions[$region->getCountryId()] = [];
                }

                $this->_regions[$region->getCountryId()][$region->getDefaultName()] = $region->getId();
            }
        }

        return $this->_regions[$country][$regionName] ?? 0;
    }

    /**
     * Retrieve customer group collection array
     *
     * @return array
     */
    public function getCustomerGroups()
    {
        if (is_null($this->_customerGroups)) {
            $this->_customerGroups = [];
            $collection = Mage::getModel('customer/group')
                ->getCollection()
                ->addFieldToFilter('customer_group_id', ['gt' => 0]);
            /** @var Mage_Customer_Model_Group $group */
            foreach ($collection as $group) {
                $this->_customerGroups[$group->getCustomerGroupCode()] = $group->getId();
            }
        }
        return $this->_customerGroups;
    }

    /**
     * Alias at getCustomerGroups()
     *
     * @return array
     */
    public function getCustomerGoups()
    {
        return $this->getCustomerGroups();
    }

    public function __construct()
    {
        $this->setVar('entity_type', 'customer/customer');

        if (!Mage::registry('Object_Cache_Customer')) {
            $this->setCustomer(Mage::getModel('customer/customer'));
        }
        //$this->setAddress(Mage::getModel('catalog/'))

        /**
         * @var string $code
         * @var Mage_Core_Model_Config_Element $node
         */
        foreach (Mage::getConfig()->getFieldset('customer_dataflow', 'admin') as $code => $node) {
            if ($node->is('ignore')) {
                $this->_ignoreFields[] = $code;
            }
            if ($node->is('billing')) {
                $this->_billingFields[] = 'billing_' . $code;
            }
            if ($node->is('shipping')) {
                $this->_shippingFields[] = 'shipping_' . $code;
            }

            if ($node->is('billing') && $node->is('shipping')) {
                $this->_addressFields[] = $code;
            }

            if ($node->is('mapped') || $node->is('billing_mapped')) {
                $this->_billingMappedFields['billing_' . $code] = $code;
            }
            if ($node->is('mapped') || $node->is('shipping_mapped')) {
                $this->_shippingMappedFields['shipping_' . $code] = $code;
            }
            if ($node->is('street')) {
                $this->_billingStreetFields[] = 'billing_' . $code;
                $this->_shippingStreetFields[] = 'shipping_' . $code;
            }
            if ($node->is('required')) {
                $this->_requiredFields[] = $code;
            }
            if ($node->is('billing_required')) {
                $this->_billingRequiredFields[] = 'billing_' . $code;
            }
            if ($node->is('shipping_required')) {
                $this->_shippingRequiredFields[] = 'shipping_' . $code;
            }
        }
    }

    /**
     * @return Mage_Eav_Model_Convert_Adapter_Entity
     * @throws Mage_Core_Model_Store_Exception
     * @throws Varien_Convert_Exception
     */
    #[\Override]
    public function load()
    {
        $addressType = $this->getVar('filter/adressType'); //error in key filter addressType
        if ($addressType == 'both') {
            $addressType = ['default_billing','default_shipping'];
        }
        $attrFilterArray = [];
        $attrFilterArray ['firstname']                  = 'like';
        $attrFilterArray ['lastname']                   = 'like';
        $attrFilterArray ['email']                      = 'like';
        $attrFilterArray ['group']                      = 'eq';
        $attrFilterArray ['customer_address/telephone'] = [
            'type'  => 'like',
            'bind'  => $addressType,
        ];
        $attrFilterArray ['customer_address/postcode']  = [
            'type'  => 'like',
            'bind'  => $addressType,
        ];
        $attrFilterArray ['customer_address/country']   = [
            'type'  => 'eq',
            'bind'  => $addressType,
        ];
        $attrFilterArray ['customer_address/region']    = [
            'type'  => 'like',
            'bind'  => $addressType,
        ];
        $attrFilterArray ['created_at']                 = 'datetimeFromTo';

        /*
         * Fixing date filter from and to
         */
        if ($var = $this->getVar('filter/created_at/from')) {
            $this->setVar('filter/created_at/from', $var . ' 00:00:00');
        }

        if ($var = $this->getVar('filter/created_at/to')) {
            $this->setVar('filter/created_at/to', $var . ' 23:59:59');
        }

        $attrToDb = [
            'group'                     => 'group_id',
            'customer_address/country'  => 'customer_address/country_id',
        ];

        // Added store filter
        if ($storeId = $this->getStoreId()) {
            $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
            if ($websiteId) {
                $this->_filter[] = [
                    'attribute' => 'website_id',
                    'eq'        => $websiteId,
                ];
            }
        }

        parent::setFilter($attrFilterArray, $attrToDb);
        return parent::load();
    }

    /**
     * Not use :(
     */
    public function parse()
    {
        $batchModel = Mage::getSingleton('dataflow/batch');
        /** @var Mage_Dataflow_Model_Batch $batchModel */

        $batchImportModel = $batchModel->getBatchImportModel();
        $importIds = $batchImportModel->getIdCollection();

        foreach ($importIds as $importId) {
            $batchImportModel->load($importId);
            $importData = $batchImportModel->getBatchData();

            $this->saveRow($importData);
        }
    }

    /**
     * @throws Mage_Core_Exception
     * @throws Varien_Exception
     */
    public function setCustomer(Mage_Customer_Model_Customer $customer)
    {
        $id = Mage::objects()->save($customer);
        Mage::register('Object_Cache_Customer', $id);
    }

    /**
     * @return Mage_Customer_Model_Customer|object
     */
    public function getCustomer()
    {
        return Mage::objects()->load(Mage::registry('Object_Cache_Customer'));
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function save()
    {
        $stores = [];
        foreach (Mage::getConfig()->getNode('stores')->children() as $storeNode) {
            $stores[(int) $storeNode->system->store->id] = $storeNode->getName();
        }

        $collections = $this->getData();
        if ($collections instanceof Mage_Customer_Model_Entity_Customer_Collection) {
            $collections = [$collections->getEntity()->getStoreId() => $collections];
        } elseif (!is_array($collections)) {
            $this->addException(Mage::helper('customer')->__('No customer collections found'), Mage_Dataflow_Model_Convert_Exception::FATAL);
        }

        foreach ($collections as $storeId => $collection) {
            $this->addException(Mage::helper('customer')->__('Records for %s store found.', $stores[$storeId]));

            if (!$collection instanceof Mage_Customer_Model_Entity_Customer_Collection) {
                $this->addException(Mage::helper('customer')->__('Customer collection expected.'), Mage_Dataflow_Model_Convert_Exception::FATAL);
            }
            try {
                $i = 0;
                foreach ($collection->getIterator() as $model) {
                    $new = false;
                    // if customer is new, create default values first
                    if (!$model->getId()) {
                        $new = true;
                        $model->save();
                    }
                    if (!$new || $storeId !== 0) {
                        $model->save();
                    }
                    $i++;
                }
                $this->addException(Mage::helper('customer')->__('Saved %d record(s)', $i));
            } catch (Exception $e) {
                if (!$e instanceof Mage_Dataflow_Model_Convert_Exception) {
                    $this->addException(
                        Mage::helper('customer')->__('An error occurred while saving the collection, aborting. Error: %s', $e->getMessage()),
                        Mage_Dataflow_Model_Convert_Exception::FATAL,
                    );
                }
            }
        }
        return $this;
    }

    /**
     * saveRow function for saving each customer data
     *
     * @param array $importData
     * @return $this
     */
    public function saveRow($importData)
    {
        $customer = $this->getCustomerModel();
        $customer->setId(null);

        if (empty($importData['website'])) {
            $message = Mage::helper('customer')->__('Skipping import row, required field "%s" is not defined.', 'website');
            Mage::throwException($message);
        }

        $website = $this->getWebsiteByCode($importData['website']);

        if ($website === false) {
            $message = Mage::helper('customer')->__('Skipping import row, website "%s" field does not exist.', $importData['website']);
            Mage::throwException($message);
        }
        if (empty($importData['email'])) {
            $message = Mage::helper('customer')->__('Skipping import row, required field "%s" is not defined.', 'email');
            Mage::throwException($message);
        }

        $customer->setWebsiteId($website->getId())
            ->loadByEmail($importData['email']);
        if (!$customer->getId()) {
            $customerGroups = $this->getCustomerGroups();
            /**
             * Check customer group
             */
            if (empty($importData['group']) || !isset($customerGroups[$importData['group']])) {
                $value = $importData['group'] ?? '';
                $message = Mage::helper('catalog')->__('Skipping import row, the value "%s" is not valid for the "%s" field.', $value, 'group');
                Mage::throwException($message);
            }
            $customer->setGroupId($customerGroups[$importData['group']]);

            foreach ($this->_requiredFields as $field) {
                if (!isset($importData[$field])) {
                    $message = Mage::helper('catalog')->__('Skip import row, required field "%s" for the new customer is not defined.', $field);
                    Mage::throwException($message);
                }
            }

            $customer->setWebsiteId($website->getId());

            if (empty($importData['created_in']) || !$this->getStoreByCode($importData['created_in'])) {
                $customer->setStoreId(0);
            } else {
                $customer->setStoreId($this->getStoreByCode($importData['created_in'])->getId());
            }

            if (empty($importData['password_hash'])) {
                $customer->setPasswordHash($customer->hashPassword($customer->generatePassword(8)));
            }
        } elseif (!empty($importData['group'])) {
            $customerGroups = $this->getCustomerGroups();
            /**
             * Check customer group
             */
            if (isset($customerGroups[$importData['group']])) {
                $customer->setGroupId($customerGroups[$importData['group']]);
            }
        }

        foreach ($this->_ignoreFields as $field) {
            if (isset($importData[$field])) {
                unset($importData[$field]);
            }
        }

        foreach ($importData as $field => $value) {
            if (in_array($field, $this->_billingFields)) {
                continue;
            }
            if (in_array($field, $this->_shippingFields)) {
                continue;
            }

            $attribute = $this->getAttribute($field);
            if (!$attribute) {
                continue;
            }

            $isArray = false;
            $setValue = $value;

            if ($attribute->getFrontendInput() == 'multiselect') {
                $value = explode(self::MULTI_DELIMITER, $value);
                $isArray = true;
                $setValue = [];
            }

            if ($attribute->usesSource()) {
                $options = $attribute->getSource()->getAllOptions(false);

                if ($isArray) {
                    foreach ($options as $item) {
                        if (in_array($item['label'], $value)) {
                            $setValue[] = $item['value'];
                        }
                    }
                } else {
                    $setValue = null;
                    foreach ($options as $item) {
                        if ($item['label'] == $value) {
                            $setValue = $item['value'];
                        }
                    }
                }
            }

            $customer->setData($field, $setValue);
        }

        if (isset($importData['is_subscribed'])) {
            $customer->setData('is_subscribed', $importData['is_subscribed']);
        }

        $importBillingAddress = $importShippingAddress = true;
        $savedBillingAddress = $savedShippingAddress = false;

        /**
         * Check Billing address required fields
         */
        foreach ($this->_billingRequiredFields as $field) {
            if (empty($importData[$field])) {
                $importBillingAddress = false;
            }
        }

        /**
         * Check Sipping address required fields
         */
        foreach ($this->_shippingRequiredFields as $field) {
            if (empty($importData[$field])) {
                $importShippingAddress = false;
            }
        }

        $onlyAddress = false;

        /**
         * Check addresses
         */
        if ($importBillingAddress && $importShippingAddress) {
            $onlyAddress = true;
            foreach ($this->_addressFields as $field) {
                if (!isset($importData['billing_' . $field]) && !isset($importData['shipping_' . $field])) {
                    continue;
                }
                if (!isset($importData['billing_' . $field]) || !isset($importData['shipping_' . $field])) {
                    $onlyAddress = false;
                    break;
                }
                if ($importData['billing_' . $field] != $importData['shipping_' . $field]) {
                    $onlyAddress = false;
                    break;
                }
            }

            if ($onlyAddress) {
                $importShippingAddress = false;
            }
        }

        /**
         * Import billing address
         */
        if ($importBillingAddress) {
            $billingAddress = $this->getBillingAddressModel();
            if ($customer->getDefaultBilling()) {
                $billingAddress->load($customer->getDefaultBilling());
            } else {
                $billingAddress->setData([]);
            }

            foreach ($this->_billingFields as $field) {
                $cleanField = Mage::helper('core/string')->substr($field, 8);

                if (isset($importData[$field])) {
                    $billingAddress->setDataUsingMethod($cleanField, $importData[$field]);
                } elseif (isset($this->_billingMappedFields[$field])
                    && isset($importData[$this->_billingMappedFields[$field]])
                ) {
                    $billingAddress->setDataUsingMethod($cleanField, $importData[$this->_billingMappedFields[$field]]);
                }
            }

            $street = [];
            foreach ($this->_billingStreetFields as $field) {
                if (!empty($importData[$field])) {
                    $street[] = $importData[$field];
                }
            }
            if ($street) {
                $billingAddress->setDataUsingMethod('street', $street);
            }

            $billingAddress->setCountryId($importData['billing_country']);
            $regionName = $importData['billing_region'] ?? '';
            if ($regionName) {
                $regionId = $this->getRegionId($importData['billing_country'], $regionName);
                $billingAddress->setRegionId($regionId);
            }

            if ($customer->getId()) {
                $billingAddress->setCustomerId($customer->getId());

                $billingAddress->save();
                $customer->setDefaultBilling($billingAddress->getId());

                if ($onlyAddress) {
                    $customer->setDefaultShipping($billingAddress->getId());
                }

                $savedBillingAddress = true;
            }
        }

        /**
         * Import shipping address
         */
        if ($importShippingAddress) {
            $shippingAddress = $this->getShippingAddressModel();
            if ($customer->getDefaultShipping() && $customer->getDefaultBilling() != $customer->getDefaultShipping()) {
                $shippingAddress->load($customer->getDefaultShipping());
            } else {
                $shippingAddress->setData([]);
            }

            foreach ($this->_shippingFields as $field) {
                $cleanField = Mage::helper('core/string')->substr($field, 9);

                if (isset($importData[$field])) {
                    $shippingAddress->setDataUsingMethod($cleanField, $importData[$field]);
                } elseif (isset($this->_shippingMappedFields[$field])
                    && isset($importData[$this->_shippingMappedFields[$field]])
                ) {
                    $shippingAddress->setDataUsingMethod($cleanField, $importData[$this->_shippingMappedFields[$field]]);
                }
            }

            $street = [];
            foreach ($this->_shippingStreetFields as $field) {
                if (!empty($importData[$field])) {
                    $street[] = $importData[$field];
                }
            }
            if ($street) {
                $shippingAddress->setDataUsingMethod('street', $street);
            }

            $shippingAddress->setCountryId($importData['shipping_country']);
            $regionName = $importData['shipping_region'] ?? '';
            if ($regionName) {
                $regionId = $this->getRegionId($importData['shipping_country'], $regionName);
                $shippingAddress->setRegionId($regionId);
            }

            if ($customer->getId()) {
                $shippingAddress->setCustomerId($customer->getId());
                $shippingAddress->save();
                $customer->setDefaultShipping($shippingAddress->getId());

                $savedShippingAddress = true;
            }
        }

        $customer->setImportMode(true);
        $customer->save();
        $saveCustomer = false;

        if ($importBillingAddress && !$savedBillingAddress) {
            $saveCustomer = true;
            $billingAddress->setCustomerId($customer->getId());
            $billingAddress->save();
            $customer->setDefaultBilling($billingAddress->getId());
            if ($onlyAddress) {
                $customer->setDefaultShipping($billingAddress->getId());
            }
        }
        if ($importShippingAddress && !$savedShippingAddress) {
            $saveCustomer = true;
            $shippingAddress->setCustomerId($customer->getId());
            $shippingAddress->save();
            $customer->setDefaultShipping($shippingAddress->getId());
        }
        if ($saveCustomer) {
            $customer->save();
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCustomerId()
    {
        return $this->_customerId;
    }
}
