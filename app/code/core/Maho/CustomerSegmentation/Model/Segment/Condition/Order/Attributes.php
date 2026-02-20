<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
            'created_at' => Mage::helper('customersegmentation')->__('Purchase Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Last Modified'),
            'store_id' => Mage::helper('customersegmentation')->__('Store'),
            'currency_code' => Mage::helper('customersegmentation')->__('Currency'),
            'payment_method' => Mage::helper('customersegmentation')->__('Payment Method'),
            'shipping_method' => Mage::helper('customersegmentation')->__('Shipping Method'),
            'coupon_code' => Mage::helper('customersegmentation')->__('Coupon Code'),
            'days_since_last_order' => Mage::helper('customersegmentation')->__('Days Since Last Order'),
            'average_order_amount' => Mage::helper('customersegmentation')->__('Average Order Amount'),
            'total_ordered_amount' => Mage::helper('customersegmentation')->__('Total Ordered Amount'),
        ];

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'status', 'store_id', 'currency_code', 'payment_method', 'shipping_method' => 'select',
            'created_at', 'updated_at' => 'date',
            'total_qty', 'total_amount', 'subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'grand_total', 'days_since_last_order', 'average_order_amount', 'total_ordered_amount' => 'numeric',
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
            'status', 'store_id', 'currency_code', 'payment_method', 'shipping_method' => 'select',
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
    public function getValueElement(): \Maho\Data\Form\Element\AbstractElement
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
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Order') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = (is_array($attributeOptions) && isset($attributeOptions[$attribute]) && is_string($attributeOptions[$attribute]))
            ? $attributeOptions[$attribute]
            : (string) $attribute;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return Mage::helper('customersegmentation')->__('Order') . ':' . ' ' . $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }

    #[\Override]
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'total_qty', 'total_amount', 'subtotal', 'tax_amount', 'shipping_amount', 'discount_amount', 'grand_total', 'status', 'created_at', 'updated_at', 'store_id', 'currency_code' => $this->buildOrderFieldCondition($adapter, $attribute, $operator, $value),
            'payment_method' => $this->buildPaymentMethodCondition($adapter, $operator, $value),
            'shipping_method' => $this->buildShippingMethodCondition($adapter, $operator, $value),
            'coupon_code' => $this->buildCouponCondition($adapter, $operator, $value),
            'days_since_last_order' => $this->buildDaysSinceLastOrderCondition($adapter, $operator, $value),
            'average_order_amount' => $this->buildAverageOrderCondition($adapter, $operator, $value),
            'total_ordered_amount' => $this->buildTotalOrderedCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildOrderFieldCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $field, string $operator, mixed $value): string
    {
        // Map attribute names to correct database field names
        $fieldMapping = [
            'currency_code' => 'order_currency_code',
            'total_qty' => 'total_qty_ordered',
            'total_amount' => 'grand_total',
        ];

        $dbField = $fieldMapping[$field] ?? $field;

        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, "o.{$dbField}", $operator, $value));

        $subselectSql = $subselect->__toString();
        Mage::log('Order field condition SQL for ' . $field . ' ' . $operator . ' ' . $value . ': ' . $subselectSql, null, 'customer_segmentation_debug.log');

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildPaymentMethodCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['p' => $this->getOrderPaymentTable()], 'o.entity_id = p.parent_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'p.method', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildShippingMethodCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'o.shipping_method', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCouponCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'o.coupon_code', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceLastOrderCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        $dateDiff = $adapter->getDateDiffSql("'{$currentDate}'", 'MAX(o.created_at)');
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->group('o.customer_id')
            ->having($this->buildSqlCondition($adapter, (string) $dateDiff, $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }


    protected function buildAverageOrderCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->buildSqlCondition($adapter, 'AVG(o.grand_total)', $operator, $this->prepareNumericValue($value)));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildTotalOrderedCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->buildSqlCondition($adapter, 'SUM(o.grand_total)', $operator, $this->prepareNumericValue($value)));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getOrderPaymentTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order_payment');
    }
}
