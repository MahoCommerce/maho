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
                    </fields>
                </integrations>
            </groups>
        </customer_segmentation>
    </sections>
</config>
```


This integration framework ensures that the Customer Segmentation module integrates seamlessly with existing Maho features.