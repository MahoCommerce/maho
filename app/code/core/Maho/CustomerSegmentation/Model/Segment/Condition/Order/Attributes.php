<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Order_Attributes extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_order_attributes');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Order Information'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'total_qty' => Mage::helper('customersegmentation')->__('Total Items Quantity'),
            'total_amount' => Mage::helper('customersegmentation')->__('Total Amount'),
            'subtotal' => Mage::helper('customersegmentation')->__('Subtotal'),
            'tax_amount' => Mage::helper('customersegmentation')->__('Tax Amount'),
            'shipping_amount' => Mage::helper('customersegmentation')->__('Shipping Amount'),
            'discount_amount' => Mage::helper('customersegmentation')->__('Discount Amount'),
            'grand_total' => Mage::helper('customersegmentation')->__('Grand Total'),
            'status' => Mage::helper('customersegmentation')->__('Order Status'),
            'state' => Mage::helper('customersegmentation')->__('Order State'),
            'created_at' => Mage::helper('customersegmentation')->__('Purchase Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Last Modified'),
            'store_id' => Mage::helper('customersegmentation')->__('Store'),
            'currency_code' => Mage::helper('customersegmentation')->__('Currency'),
            'payment_method' => Mage::helper('customersegmentation')->__('Payment Method'),
            'shipping_method' => Mage::helper('customersegmentation')->__('Shipping Method'),
            'coupon_code' => Mage::helper('customersegmentation')->__('Coupon Code'),
            'days_since_last_order' => Mage::helper('customersegmentation')->__('Days Since Last Order'),
            'number_of_orders' => Mage::helper('customersegmentation')->__('Number of Orders'),
            'average_order_amount' => Mage::helper('customersegmentation')->__('Average Order Amount'),
            'total_ordered_amount' => Mage::helper('customersegmentation')->__('Total Ordered Amount'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'status', 'state', 'store_id', 'currency_code', 'payment_method', 'shipping_method' => 'select',
            'created_at', 'updated_at' => 'date',
            'total_qty', 'total_amount', 'subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'grand_total', 'days_since_last_order', 'number_of_orders', 'average_order_amount', 'total_ordered_amount' => 'numeric',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        $attribute = $this->getAttribute();
        if (!$attribute) {
            return 'text';
        }

        return match ($attribute) {
            'status', 'state', 'store_id', 'currency_code', 'payment_method', 'shipping_method' => 'select',
            'created_at', 'updated_at' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        // Always regenerate options based on current attribute - don't cache
        $options = [];
        switch ($this->getAttribute()) {
            case 'status':
                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                $statuses = Mage::getSingleton('sales/order_config')->getStatuses();
                foreach ($statuses as $key => $value) {
                    $options[] = ['value' => $key, 'label' => $value];
                }
                break;

            case 'state':
                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                $states = Mage::getSingleton('sales/order_config')->getStates();
                foreach ($states as $key => $value) {
                    $options[] = ['value' => $key, 'label' => $value];
                }
                break;

            case 'store_id':
                $options = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm();
                // Add empty option at the beginning if not already present
                if (empty($options) || $options[0]['value'] !== '') {
                    array_unshift($options, ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]);
                }
                break;

            case 'currency_code':
                $options = Mage::getSingleton('directory/currency')->getConfigAllowCurrencies();
                $options = array_map(function ($code) {
                    return ['value' => $code, 'label' => $code];
                }, $options);
                break;

            case 'payment_method':
                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                $payments = Mage::getSingleton('payment/config')->getActiveMethods();
                foreach ($payments as $code => $payment) {
                    $title = Mage::getStoreConfig('payment/' . $code . '/title') ?: $code;
                    $options[] = [
                        'value' => $code,
                        'label' => $title,
                    ];
                }
                break;

            case 'shipping_method':
                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                $carriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
                foreach ($carriers as $code => $carrier) {
                    $title = Mage::getStoreConfig('carriers/' . $code . '/title') ?: $code;
                    $methods = $carrier->getAllowedMethods();
                    if (is_array($methods)) {
                        foreach ($methods as $methodCode => $methodTitle) {
                            $options[] = [
                                'value' => $code . '_' . $methodCode,
                                'label' => $title . ' - ' . $methodTitle,
                            ];
                        }
                    } else {
                        $options[] = [
                            'value' => $code,
                            'label' => $title,
                        ];
                    }
                }
                break;

            default:
                $options = [];
        }

        return $options;
    }

    #[\Override]
    public function getValueElement(): Varien_Data_Form_Element_Abstract
    {
        return parent::getValueElement();
    }

    public function getValueElementChooserUrl(): ?string
    {
        return null;
    }


    #[\Override]
    public function asHtml(): string
    {
        return parent::asHtml();
    }

    #[\Override]
    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }

        $element = parent::getAttributeElement();
        $element->setShowAsText(true);
        return $element;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = $attributeOptions[$attribute] ?? $attribute;

        return $attributeLabel . ' ' . $this->getOperatorName() . ' ' . $this->getValueName();
    }

    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'total_qty', 'total_amount', 'subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'grand_total', 'status', 'state', 'created_at', 'updated_at', 'store_id', 'base_currency_code' => $this->_buildOrderFieldCondition($adapter, $attribute, $operator, $value),
            'payment_method' => $this->_buildPaymentMethodCondition($adapter, $operator, $value),
            'shipping_method' => $this->_buildShippingMethodCondition($adapter, $operator, $value),
            'coupon_code' => $this->_buildCouponCondition($adapter, $operator, $value),
            'days_since_last_order' => $this->_buildDaysSinceLastOrderCondition($adapter, $operator, $value),
            'number_of_orders' => $this->_buildOrderCountCondition($adapter, $operator, $value),
            'average_order_amount' => $this->_buildAverageOrderCondition($adapter, $operator, $value),
            'total_ordered_amount' => $this->_buildTotalOrderedCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function _buildOrderFieldCondition(Varien_Db_Adapter_Interface $adapter, string $field, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, "o.{$field}", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildPaymentMethodCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id'])
            ->join(['p' => $this->_getOrderPaymentTable()], 'o.entity_id = p.parent_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'p.method', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildShippingMethodCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'o.shipping_method', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildCouponCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'o.coupon_code', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildDaysSinceLastOrderCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'last_order' => 'MAX(o.created_at)'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_order)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildOrderCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'order_count' => 'COUNT(*)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'order_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildAverageOrderCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'avg_amount' => 'AVG(o.grand_total)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'avg_amount', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildTotalOrderedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->_getOrderTable()], ['customer_id', 'total_amount' => 'SUM(o.grand_total)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total_amount', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _getOrderPaymentTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order_payment');
    }
}
