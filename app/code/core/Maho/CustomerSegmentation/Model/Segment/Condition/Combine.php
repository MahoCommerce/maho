<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Combine extends Mage_Rule_Model_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_combine');
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $conditions = parent::getNewChildSelectOptions();

        $orderConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Payment Method'),
                'value' => 'customersegmentation/segment_condition_order_attributes|payment_method',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Shipping Method'),
                'value' => 'customersegmentation/segment_condition_order_attributes|shipping_method',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order Status'),
                'value' => 'customersegmentation/segment_condition_order_attributes|status',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Store'),
                'value' => 'customersegmentation/segment_condition_order_attributes|store_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Grand Total'),
                'value' => 'customersegmentation/segment_condition_order_attributes|grand_total',
            ],
        ];

        // Debug what we're generating
        Mage::log('Order Conditions: ' . print_r($orderConditions, true), null, 'debug.log');

        $conditions = array_merge_recursive($conditions, [
            [
                'label' => Mage::helper('customersegmentation')->__('Conditions Combination'),
                'value' => 'customersegmentation/segment_condition_combine',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Attributes'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Customer Personal Information'),
                        'value' => 'customersegmentation/segment_condition_customer_attributes',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Customer Address'),
                        'value' => 'customersegmentation/segment_condition_customer_address',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Newsletter Subscription'),
                        'value' => 'customersegmentation/segment_condition_customer_newsletter',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Customer Activity'),
                        'value' => 'customersegmentation/segment_condition_customer_activity',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('RFM Analysis'),
                        'value' => 'customersegmentation/segment_condition_customer_rfm',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Review Activity'),
                        'value' => 'customersegmentation/segment_condition_customer_reviews',
                    ],
                ],
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order History'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Order Attributes'),
                        'value' => 'customersegmentation/segment_condition_order_attributes',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Coupon Usage'),
                        'value' => 'customersegmentation/segment_condition_order_coupons',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Return/Refund History'),
                        'value' => 'customersegmentation/segment_condition_order_returns',
                    ],
                ],
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Shopping Cart'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Shopping Cart Information'),
                        'value' => 'customersegmentation/segment_condition_cart_attributes',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Cart Items'),
                        'value' => 'customersegmentation/segment_condition_cart_items',
                    ],
                ],
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Product Interactions'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Viewed Products'),
                        'value' => 'customersegmentation/segment_condition_product_viewed',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Wishlist'),
                        'value' => 'customersegmentation/segment_condition_product_wishlist',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Product Categories Purchased'),
                        'value' => 'customersegmentation/segment_condition_product_categories',
                    ],
                ],
            ],
        ]);

        return $conditions;
    }

    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $conditions = [];
        $aggregator = $this->getAggregator();

        foreach ($this->getConditions() as $condition) {
            if ($condition instanceof Maho_CustomerSegmentation_Model_Segment_Condition_Abstract) {
                $sql = $condition->getConditionsSql($adapter, $websiteId);
                if ($sql) {
                    $conditions[] = '(' . $sql . ')';
                }
            }
        }

        if (empty($conditions)) {
            return false;
        }

        $operator = ($aggregator == 'all') ? ' AND ' : ' OR ';
        $sql = implode($operator, $conditions);

        if ($this->getValue()) {
            $sql = 'NOT (' . $sql . ')';
        }

        return $sql;
    }

    #[\Override]
    public function asHtml(): string
    {
        $html = $this->getTypeElement()->getHtml() .
                Mage::helper('rule')->__(
                    'If %s of these conditions are %s:',
                    $this->getAggregatorElement()->getHtml(),
                    $this->getValueElement()->getHtml(),
                );

        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }

        return $html;
    }
}
