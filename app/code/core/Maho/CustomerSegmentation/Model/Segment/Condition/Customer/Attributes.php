<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Customer_Attributes extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    /**
     * Initialize model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_customer_attributes');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Customer Personal Information'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'email' => Mage::helper('customersegmentation')->__('Email'),
            'firstname' => Mage::helper('customersegmentation')->__('First Name'),
            'lastname' => Mage::helper('customersegmentation')->__('Last Name'),
            'gender' => Mage::helper('customersegmentation')->__('Gender'),
            'dob' => Mage::helper('customersegmentation')->__('Date of Birth'),
            'created_at' => Mage::helper('customersegmentation')->__('Customer Since'),
            'group_id' => Mage::helper('customersegmentation')->__('Customer Group'),
            'store_id' => Mage::helper('customersegmentation')->__('Account Created In Store'),
            'website_id' => Mage::helper('customersegmentation')->__('Website'),
            'days_since_registration' => Mage::helper('customersegmentation')->__('Days Since Registration'),
            'days_until_birthday' => Mage::helper('customersegmentation')->__('Days Until Birthday'),
            'lifetime_sales' => Mage::helper('customersegmentation')->__('Lifetime Sales Amount'),
            'number_of_orders' => Mage::helper('customersegmentation')->__('Number of Orders'),
            'average_order_value' => Mage::helper('customersegmentation')->__('Average Order Value'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'gender' => 'select',
            'group_id', 'store_id', 'website_id' => 'multiselect',
            'dob', 'created_at' => 'date',
            'days_since_registration', 'days_until_birthday', 'lifetime_sales', 'number_of_orders', 'average_order_value' => 'numeric',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'gender', 'group_id', 'store_id', 'website_id' => 'select',
            'dob', 'created_at' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getOperatorSelectOptions(): array
    {
        $operators = parent::getOperatorSelectOptions();

        // For selection fields, use simple is/is not operators
        if (in_array($this->getAttribute(), ['gender', 'group_id', 'store_id', 'website_id'])) {
            return [
                ['value' => '==', 'label' => Mage::helper('rule')->__('is')],
                ['value' => '!=', 'label' => Mage::helper('rule')->__('is not')],
            ];
        }

        return $operators;
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                'gender' => Mage::getResourceSingleton('customer/customer')
                    ->getAttribute('gender')
                    ->getSource()
                    ->getAllOptions(),
                'group_id' => Mage::getResourceModel('customer/group_collection')
                    ->toOptionArray(),
                'store_id' => Mage::getSingleton('adminhtml/system_store')
                    ->getStoreValuesForForm(),
                'website_id' => Mage::getSingleton('adminhtml/system_store')
                    ->getWebsiteValuesForForm(),
                default => [],
            };
            $this->setData('value_select_options', $options);
        }
        return $this->getData('value_select_options');
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        switch ($attribute) {
            case 'email':
            case 'created_at':
            case 'group_id':
            case 'store_id':
            case 'website_id':
                $field = 'e.' . $attribute;
                return $this->_buildSqlCondition($adapter, $field, $operator, $value);

            case 'firstname':
            case 'lastname':
            case 'gender':
            case 'dob':
                return $this->_buildAttributeCondition($adapter, $attribute, $operator, $value);

            case 'days_since_registration':
                return $this->_buildDaysSinceCondition($adapter, 'e.created_at', $operator, $value);

            case 'days_until_birthday':
                return $this->_buildDaysUntilBirthdayCondition($adapter, $operator, $value);

            case 'lifetime_sales':
                return $this->_buildLifetimeSalesCondition($adapter, $operator, $value);

            case 'number_of_orders':
                return $this->_buildOrderCountCondition($adapter, $operator, $value);

            case 'average_order_value':
                return $this->_buildAverageOrderCondition($adapter, $operator, $value);
        }

        return false;
    }

    protected function _buildAttributeCondition(Varien_Db_Adapter_Interface $adapter, string $attributeCode, string $operator, mixed $value): string|false
    {
        $attributeData = $this->_getCustomerAttributeTable($attributeCode);
        if (!$attributeData) {
            return false;
        }

        $subselect = $adapter->select()
            ->from(['attr' => $attributeData['table']], ['entity_id'])
            ->where('attr.attribute_id = ?', $attributeData['attribute_id'])
            ->where($this->_buildSqlCondition($adapter, 'attr.value', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildDaysSinceCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $currentDate = Mage::app()->getLocale()->utcDate(null, 'now', false, Mage_Core_Model_Locale::DATETIME_FORMAT);
        return $this->_buildSqlCondition($adapter, "DATEDIFF('{$currentDate}', {$field})", $operator, $value);
    }

    protected function _buildDaysUntilBirthdayCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string|false
    {
        $attributeData = $this->_getCustomerAttributeTable('dob');
        if (!$attributeData) {
            return false;
        }

        $subselect = $adapter->select()
            ->from(['attr' => $attributeData['table']], ['entity_id'])
            ->where('attr.attribute_id = ?', $attributeData['attribute_id'])
            ->where($this->_buildSqlCondition(
                $adapter,
                $this->getBirthdayDiffSql($adapter),
                $operator,
                $value,
            ));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getBirthdayDiffSql(Varien_Db_Adapter_Interface $adapter): string
    {
        $currentDate = Mage::app()->getLocale()->utcDate(null, 'now', false, Mage_Core_Model_Locale::DATETIME_FORMAT);
        return "DATEDIFF(DATE_ADD(attr.value, INTERVAL YEAR('{$currentDate}') - YEAR(attr.value) + IF(DAYOFYEAR('{$currentDate}') > DAYOFYEAR(attr.value), 1, 0) YEAR), '{$currentDate}')";
    }

    protected function _buildLifetimeSalesCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'total' => 'SUM(o.grand_total)'])
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildOrderCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'count' => 'COUNT(*)'])
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildAverageOrderCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'average' => 'AVG(o.grand_total)'])
            ->where('o.state NOT IN (?)', ['canceled', 'closed'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'average', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Customer') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $this->loadAttributeOptions();
        $attributeOptions = $this->getAttributeOption();
        $attributeLabel = is_array($attributeOptions) && isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return Mage::helper('customersegmentation')->__('Customer') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}
