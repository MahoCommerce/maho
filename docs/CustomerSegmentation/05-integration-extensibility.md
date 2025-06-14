# Maho Customer Segmentation - Integration & Extensibility Plan

## Overview

This document outlines how the Customer Segmentation module integrates with existing Maho features and provides extensibility mechanisms for third-party developers and future enhancements.

## Core Maho Integrations

### 1. Cart Price Rules Integration

#### Segment Condition in Price Rules
```php
class Maho_CustomerSegmentation_Model_Rule_Condition_Segment
    extends Mage_Rule_Model_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/rule_condition_segment')
            ->setValue(null);
    }
    
    public function loadAttributeOptions()
    {
        $attributes = [
            'customer_segment' => Mage::helper('customersegmentation')->__('Customer Segment')
        ];
        $this->setAttributeOption($attributes);
        return $this;
    }
    
    public function getValueSelectOptions()
    {
        $segments = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('is_active', 1)
            ->load();
        
        $options = [];
        foreach ($segments as $segment) {
            $options[] = [
                'value' => $segment->getId(),
                'label' => $segment->getName()
            ];
        }
        
        return $options;
    }
    
    public function validate(Varien_Object $object)
    {
        $customerId = $object->getCustomerId();
        if (!$customerId) {
            return false; // Guest customers
        }
        
        $segmentIds = Mage::getModel('customersegmentation/customer')
            ->getCustomerSegmentIds($customerId);
        
        return $this->validateAttribute($segmentIds);
    }
}
```

#### Price Rule Enhancement
```php
// Observer to add segment conditions to price rules
class Maho_CustomerSegmentation_Model_Observer_PriceRule
{
    public function addSegmentConditionToSalesRule($observer)
    {
        $additional = $observer->getAdditional();
        $conditions = $additional->getConditions();
        
        if (!is_array($conditions)) {
            $conditions = [];
        }
        
        $conditions[] = [
            'label' => Mage::helper('customersegmentation')->__('Customer Segment'),
            'value' => 'customersegmentation/rule_condition_segment'
        ];
        
        $additional->setConditions($conditions);
    }
}
```

### 2. Customer Management Integration

#### Customer Profile Enhancement
```php
class Maho_CustomerSegmentation_Block_Adminhtml_Customer_Edit_Tab_Segments
    extends Mage_Adminhtml_Block_Widget_Grid
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customer_segments_grid');
        $this->setDefaultSort('name');
        $this->setUseAjax(true);
    }
    
    protected function _prepareCollection()
    {
        $customerId = Mage::registry('current_customer')->getId();
        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addCustomerFilter($customerId);
        
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }
    
    public function getTabLabel()
    {
        return Mage::helper('customersegmentation')->__('Customer Segments');
    }
    
    public function getTabTitle()
    {
        return Mage::helper('customersegmentation')->__('Customer Segments');
    }
    
    public function canShowTab()
    {
        return true;
    }
    
    public function isHidden()
    {
        return false;
    }
}
```

### 3. Email Marketing Integration

#### Newsletter Segment Targeting
```php
class Maho_CustomerSegmentation_Model_Newsletter_Queue extends Mage_Newsletter_Model_Queue
{
    /**
     * Add segment-based subscriber filtering
     */
    public function sendPerSubscriber($count = 20)
    {
        if ($this->getSegmentIds()) {
            return $this->sendPerSegmentSubscriber($count);
        }
        
        return parent::sendPerSubscriber($count);
    }
    
    protected function sendPerSegmentSubscriber($count)
    {
        $segmentIds = explode(',', $this->getSegmentIds());
        $subscribers = Mage::getResourceModel('newsletter/subscriber_collection')
            ->useQueue($this)
            ->addSegmentFilter($segmentIds)
            ->showCustomerInfo()
            ->setPageSize($count)
            ->setCurPage(1)
            ->load();
        
        return $this->sendToSubscribers($subscribers);
    }
}
```

### 4. Reporting Integration

#### Segment Analytics Report
```php
class Maho_CustomerSegmentation_Model_Resource_Report_Segment_Collection
    extends Mage_Reports_Model_Resource_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('customersegmentation/segment');
    }
    
    public function addRevenueData($from = null, $to = null)
    {
        $orderTable = $this->getTable('sales/order');
        $segmentCustomerTable = $this->getTable('customersegmentation/segment_customer');
        
        $this->getSelect()
            ->joinLeft(
                ['sc' => $segmentCustomerTable],
                'main_table.segment_id = sc.segment_id',
                []
            )
            ->joinLeft(
                ['o' => $orderTable],
                'sc.customer_id = o.customer_id',
                [
                    'total_revenue' => 'COALESCE(SUM(o.grand_total), 0)',
                    'total_orders' => 'COUNT(DISTINCT o.entity_id)',
                    'avg_order_value' => 'COALESCE(AVG(o.grand_total), 0)'
                ]
            )
            ->group('main_table.segment_id');
        
        if ($from || $to) {
            $this->addDateFilter($from, $to);
        }
        
        return $this;
    }
}
```

## Extensibility Framework

### 1. Custom Condition Types

#### Condition Interface
```php
interface Maho_CustomerSegmentation_Model_Segment_Condition_Interface
{
    /**
     * Get SQL conditions for segment matching
     */
    public function getConditionsSql(Varien_Db_Select $select);
    
    /**
     * Validate individual customer against condition
     */
    public function validateCustomer(Mage_Customer_Model_Customer $customer);
    
    /**
     * Get condition configuration form
     */
    public function getConditionForm();
    
    /**
     * Load condition from array
     */
    public function loadArray(array $data);
    
    /**
     * Convert condition to array
     */
    public function asArray();
}
```

#### Example Custom Condition
```php
class MyModule_Model_Segment_Condition_CustomAttribute
    extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('mymodule/segment_condition_customattribute');
    }
    
    public function getConditionsSql(Varien_Db_Select $select)
    {
        $attribute = $this->getAttribute();
        $operator = $this->getOperator();
        $value = $this->getValue();
        
        // Join custom attribute table
        $select->joinLeft(
            ['ca' => $this->getTable('mymodule/customer_attribute')],
            'ca.customer_id = e.entity_id',
            []
        );
        
        // Apply condition
        $condition = $this->getOperatorCondition('ca.' . $attribute, $operator, $value);
        $select->where($condition);
        
        return $this;
    }
    
    public function validateCustomer(Mage_Customer_Model_Customer $customer)
    {
        $attributeValue = $customer->getData($this->getAttribute());
        return $this->validateAttribute($attributeValue);
    }
}
```

### 2. Event System

#### Dispatched Events
```php
class Maho_CustomerSegmentation_Model_Segment extends Mage_Core_Model_Abstract
{
    protected function _afterSave()
    {
        parent::_afterSave();
        
        // Dispatch segment save event
        Mage::dispatchEvent('customer_segment_save_after', [
            'segment' => $this,
            'is_new' => $this->isObjectNew()
        ]);
        
        return $this;
    }
    
    public function refreshCustomers()
    {
        Mage::dispatchEvent('customer_segment_refresh_before', [
            'segment' => $this
        ]);
        
        $matchedCustomers = $this->getMatchingCustomerIds();
        $this->updateCustomerMembership($matchedCustomers);
        
        Mage::dispatchEvent('customer_segment_refresh_after', [
            'segment' => $this,
            'matched_customers' => $matchedCustomers
        ]);
        
        return $this;
    }
}
```

#### Event Observers Registration
```xml
<!-- config.xml -->
<events>
    <customer_segment_save_after>
        <observers>
            <customersegmentation_cache_clean>
                <class>customersegmentation/observer</class>
                <method>cleanSegmentCache</method>
            </customersegmentation_cache_clean>
        </observers>
    </customer_segment_save_after>
    
    <customer_segment_refresh_after>
        <observers>
            <customersegmentation_update_stats>
                <class>customersegmentation/observer</class>
                <method>updateSegmentStatistics</method>
            </customersegmentation_update_stats>
        </observers>
    </customer_segment_refresh_after>
    
    <customer_save_after>
        <observers>
            <customersegmentation_customer_update>
                <class>customersegmentation/observer</class>
                <method>onCustomerUpdate</method>
            </customersegmentation_customer_update>
        </observers>
    </customer_save_after>
</events>
```

### 3. API Endpoints

#### RESTful API
```php
class Maho_CustomerSegmentation_Model_Api2_Segment extends Mage_Api2_Model_Resource
{
    /**
     * Get segments list
     */
    protected function _retrieveCollection()
    {
        $collection = Mage::getResourceModel('customersegmentation/segment_collection');
        
        if ($this->getRequest()->getParam('include_customers')) {
            $collection->addCustomerCount();
        }
        
        return $collection->toArray();
    }
    
    /**
     * Get single segment
     */
    protected function _retrieve()
    {
        $segmentId = $this->getRequest()->getParam('id');
        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        
        if (!$segment->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        
        $data = $segment->toArray();
        
        if ($this->getRequest()->getParam('include_customers')) {
            $data['customers'] = $segment->getCustomerIds();
        }
        
        return $data;
    }
    
    /**
     * Create new segment
     */
    protected function _create(array $data)
    {
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->addData($data);
        
        try {
            $segment->save();
            return $segment->toArray();
        } catch (Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
    }
    
    /**
     * Update segment
     */
    protected function _update(array $data)
    {
        $segmentId = $this->getRequest()->getParam('id');
        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        
        if (!$segment->getId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        
        $segment->addData($data);
        
        try {
            $segment->save();
            return $segment->toArray();
        } catch (Exception $e) {
            $this->_critical($e->getMessage(), Mage_Api2_Model_Server::HTTP_INTERNAL_ERROR);
        }
    }
}
```

### 4. Plugin System

#### Plugin Registration
```xml
<!-- config.xml -->
<global>
    <customer_segmentation>
        <condition_plugins>
            <social_media>
                <class>mymodule/segment_condition_social</class>
                <label>Social Media Conditions</label>
            </social_media>
            <loyalty_points>
                <class>mymodule/segment_condition_loyalty</class>
                <label>Loyalty Points Conditions</label>
            </loyalty_points>
        </condition_plugins>
    </customer_segmentation>
</global>
```

#### Plugin Manager
```php
class Maho_CustomerSegmentation_Model_Plugin_Manager
{
    protected $_plugins = null;
    
    public function getAvailablePlugins()
    {
        if ($this->_plugins === null) {
            $this->_plugins = [];
            $config = Mage::getConfig()->getNode('global/customer_segmentation/condition_plugins');
            
            foreach ($config->children() as $pluginId => $pluginConfig) {
                $this->_plugins[$pluginId] = [
                    'class' => (string)$pluginConfig->class,
                    'label' => (string)$pluginConfig->label,
                    'enabled' => (bool)$pluginConfig->enabled
                ];
            }
        }
        
        return $this->_plugins;
    }
    
    public function createCondition($pluginId, $config = [])
    {
        $plugins = $this->getAvailablePlugins();
        
        if (!isset($plugins[$pluginId])) {
            throw new Exception("Plugin '{$pluginId}' not found");
        }
        
        $condition = Mage::getModel($plugins[$pluginId]['class']);
        if ($config) {
            $condition->loadArray($config);
        }
        
        return $condition;
    }
}
```

## Third-Party Integration Points

### 1. CRM Integration

#### Customer Data Sync
```php
class Maho_CustomerSegmentation_Model_Integration_Crm
{
    public function syncSegmentsToCrm($segmentIds = null)
    {
        $segments = Mage::getResourceModel('customersegmentation/segment_collection');
        
        if ($segmentIds) {
            $segments->addFieldToFilter('segment_id', ['in' => $segmentIds]);
        }
        
        foreach ($segments as $segment) {
            $customers = $segment->getCustomers();
            $this->sendToCrm($segment, $customers);
        }
    }
    
    protected function sendToCrm($segment, $customers)
    {
        $crmAdapter = $this->getCrmAdapter();
        
        foreach ($customers as $customer) {
            $crmAdapter->updateCustomerSegment(
                $customer->getEmail(),
                $segment->getName(),
                true
            );
        }
    }
}
```

### 2. Email Service Provider Integration

#### Mailchimp Integration Example
```php
class Maho_CustomerSegmentation_Model_Integration_Mailchimp
{
    public function syncSegmentToList($segmentId, $listId)
    {
        $segment = Mage::getModel('customersegmentation/segment')->load($segmentId);
        $customers = $segment->getCustomers();
        
        $mailchimp = $this->getMailchimpClient();
        
        foreach ($customers as $customer) {
            $mailchimp->lists->members->createOrUpdate($listId, $customer->getEmail(), [
                'email_address' => $customer->getEmail(),
                'status' => 'subscribed',
                'merge_fields' => [
                    'FNAME' => $customer->getFirstname(),
                    'LNAME' => $customer->getLastname(),
                    'SEGMENT' => $segment->getName()
                ],
                'tags' => [$segment->getName()]
            ]);
        }
    }
}
```

### 3. Analytics Integration

#### Google Analytics Enhanced Ecommerce
```php
class Maho_CustomerSegmentation_Model_Integration_Analytics
{
    public function addSegmentDimension($customerId)
    {
        $segmentIds = Mage::getModel('customersegmentation/customer')
            ->getCustomerSegmentIds($customerId);
        
        if (empty($segmentIds)) {
            return null;
        }
        
        $segments = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addFieldToFilter('segment_id', ['in' => $segmentIds])
            ->load();
        
        $segmentNames = [];
        foreach ($segments as $segment) {
            $segmentNames[] = $segment->getName();
        }
        
        return implode(',', $segmentNames);
    }
}
```

## Configuration and Settings

### 1. Module Configuration
```xml
<!-- system.xml -->
<config>
    <sections>
        <customer_segmentation translate="label">
            <label>Customer Segmentation</label>
            <tab>customer</tab>
            <groups>
                <integrations translate="label">
                    <label>Integrations</label>
                    <fields>
                        <enable_price_rules translate="label">
                            <label>Enable Price Rule Integration</label>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                        </enable_price_rules>
                        <enable_newsletter translate="label">
                            <label>Enable Newsletter Integration</label>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                        </enable_newsletter>
                        <api_enabled translate="label">
                            <label>Enable API Access</label>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                        </api_enabled>
                    </fields>
                </integrations>
                <plugins translate="label">
                    <label>Plugin Management</label>
                    <fields>
                        <enabled_plugins translate="label">
                            <label>Enabled Plugins</label>
                            <frontend_type>multiselect</frontend_type>
                            <source_model>customersegmentation/system_config_source_plugins</source_model>
                        </enabled_plugins>
                    </fields>
                </plugins>
            </groups>
        </customer_segmentation>
    </sections>
</config>
```

### 2. Developer Configuration
```php
class Maho_CustomerSegmentation_Model_System_Config_Source_Plugins
{
    public function toOptionArray()
    {
        $options = [];
        $plugins = Mage::getModel('customersegmentation/plugin_manager')
            ->getAvailablePlugins();
        
        foreach ($plugins as $id => $plugin) {
            $options[] = [
                'value' => $id,
                'label' => $plugin['label']
            ];
        }
        
        return $options;
    }
}
```

## Migration and Upgrade Path

### 1. Data Migration Utilities
```php
class Maho_CustomerSegmentation_Model_Migration
{
    public function migrateFromMagento2($sourceConfig)
    {
        // Migrate segments from Magento 2 format
        $segments = $this->loadMagento2Segments($sourceConfig);
        
        foreach ($segments as $segmentData) {
            $segment = $this->convertSegmentFormat($segmentData);
            $segment->save();
        }
    }
    
}
```

This integration and extensibility framework ensures that the Customer Segmentation module can grow with business needs and integrate seamlessly with both existing Maho features and third-party systems.