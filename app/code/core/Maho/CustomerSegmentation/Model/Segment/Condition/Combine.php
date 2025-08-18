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
                'label' => Mage::helper('customersegmentation')->__('Average Order Value'),
                'value' => 'customersegmentation/segment_condition_customer_clv|average_order_value',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Grand Total'),
                'value' => 'customersegmentation/segment_condition_order_attributes|grand_total',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Lifetime Profit (Sales - Refunds)'),
                'value' => 'customersegmentation/segment_condition_customer_clv|lifetime_profit',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Lifetime Refunds Amount'),
                'value' => 'customersegmentation/segment_condition_customer_clv|lifetime_refunds',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Lifetime Sales Amount'),
                'value' => 'customersegmentation/segment_condition_customer_clv|lifetime_sales',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Number of Orders'),
                'value' => 'customersegmentation/segment_condition_customer_clv|lifetime_orders',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order Status'),
                'value' => 'customersegmentation/segment_condition_order_attributes|status',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Payment Method'),
                'value' => 'customersegmentation/segment_condition_order_attributes|payment_method',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Shipping Method'),
                'value' => 'customersegmentation/segment_condition_order_attributes|shipping_method',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Store'),
                'value' => 'customersegmentation/segment_condition_order_attributes|store_id',
            ],
        ];

        $customerPersonalConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Group'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|group_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Since'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|created_at',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Date of Birth'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|dob',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Email'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|email',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('First Name'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|firstname',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Gender'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|gender',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Last Name'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|lastname',
            ],
        ];

        $customerAddressConditions = [
            // These would be specific address attributes
            [
                'label' => Mage::helper('customersegmentation')->__('City'),
                'value' => 'customersegmentation/segment_condition_customer_address|city',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Country'),
                'value' => 'customersegmentation/segment_condition_customer_address|country_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Postal Code'),
                'value' => 'customersegmentation/segment_condition_customer_address|postcode',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('State/Province'),
                'value' => 'customersegmentation/segment_condition_customer_address|region',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Street Address'),
                'value' => 'customersegmentation/segment_condition_customer_address|street',
            ],
        ];

        $cartConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Applied Promotion Rules'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|applied_rule_ids',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Created Date'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|created_at',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Status'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|is_active',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Updated Date'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|updated_at',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Coupon Code'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|coupon_code',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Grand Total'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|base_grand_total',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Items Count'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|items_count',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Items Quantity'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|items_qty',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Store'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|store_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Subtotal'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|base_subtotal',
            ],
        ];

        // Dynamically load cart items conditions from product EAV attributes
        $cartItemsConditions = [];

        // Load product attributes from EAV
        $productAttributes = Mage::getResourceSingleton('catalog/product')
            ->loadAllAttributes()
            ->getAttributesByCode();

        foreach ($productAttributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            if (!$attribute->isAllowedForRuleCondition()
                || !$attribute->getData('is_used_for_promo_rules')
            ) {
                continue;
            }
            $cartItemsConditions[] = [
                'label' => Mage::helper('customersegmentation')->__('Product: %s', $attribute->getFrontendLabel()),
                'value' => 'customersegmentation/segment_condition_cart_items|product_' . $attribute->getAttributeCode(),
            ];
        }

        // Add cart item specific attributes (from quote_item table)
        $cartItemAttributes = [
            'qty' => Mage::helper('customersegmentation')->__('Quantity in Cart'),
            'price' => Mage::helper('customersegmentation')->__('Price'),
            'base_price' => Mage::helper('customersegmentation')->__('Base Price'),
            'row_total' => Mage::helper('customersegmentation')->__('Row Total'),
            'base_row_total' => Mage::helper('customersegmentation')->__('Base Row Total'),
            'created_at' => Mage::helper('customersegmentation')->__('Added to Cart Date'),
            'updated_at' => Mage::helper('customersegmentation')->__('Last Updated Date'),
        ];

        foreach ($cartItemAttributes as $code => $label) {
            $cartItemsConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_cart_items|' . $code,
            ];
        }

        // Sort all cart items conditions alphabetically
        usort($cartItemsConditions, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        // Dynamically load viewed products conditions from product EAV attributes
        $viewedProductsConditions = [];

        foreach ($productAttributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            if (!$attribute->isAllowedForRuleCondition()
                || !$attribute->getData('is_used_for_promo_rules')
            ) {
                continue;
            }
            $viewedProductsConditions[] = [
                'label' => Mage::helper('customersegmentation')->__('Product: %s', $attribute->getFrontendLabel()),
                'value' => 'customersegmentation/segment_condition_product_viewed|product_' . $attribute->getAttributeCode(),
            ];
        }

        // Add viewed products specific attributes
        $viewedProductsConditions[] = [
            'label' => Mage::helper('customersegmentation')->__('View Count'),
            'value' => 'customersegmentation/segment_condition_product_viewed|view_count',
        ];
        $viewedProductsConditions[] = [
            'label' => Mage::helper('customersegmentation')->__('First Viewed Date'),
            'value' => 'customersegmentation/segment_condition_product_viewed|first_viewed_at',
        ];
        $viewedProductsConditions[] = [
            'label' => Mage::helper('customersegmentation')->__('Last Viewed Date'),
            'value' => 'customersegmentation/segment_condition_product_viewed|last_viewed_at',
        ];

        // Sort viewed products conditions alphabetically
        usort($viewedProductsConditions, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        // Dynamically load wishlist conditions from product EAV attributes
        $wishlistConditions = [];

        foreach ($productAttributes as $attribute) {
            /** @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
            if (!$attribute->isAllowedForRuleCondition()
                || !$attribute->getData('is_used_for_promo_rules')
            ) {
                continue;
            }
            $wishlistConditions[] = [
                'label' => Mage::helper('customersegmentation')->__('Product: %s', $attribute->getFrontendLabel()),
                'value' => 'customersegmentation/segment_condition_product_wishlist|product_' . $attribute->getAttributeCode(),
            ];
        }

        // Add wishlist specific attributes
        $wishlistConditions[] = [
            'label' => Mage::helper('customersegmentation')->__('Items Count'),
            'value' => 'customersegmentation/segment_condition_product_wishlist|items_count',
        ];
        $wishlistConditions[] = [
            'label' => Mage::helper('customersegmentation')->__('Added to Wishlist Date'),
            'value' => 'customersegmentation/segment_condition_product_wishlist|added_at',
        ];

        // Sort wishlist conditions alphabetically
        usort($wishlistConditions, function ($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $conditions = array_merge_recursive($conditions, [
            [
                'label' => Mage::helper('customersegmentation')->__('Conditions Combination'),
                'value' => 'customersegmentation/segment_condition_combine',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Personal Information'),
                'value' => $customerPersonalConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Address'),
                'value' => $customerAddressConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Time-based'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Days Since Last Login'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|days_since_last_login',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Days Since Last Order'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|days_since_last_order',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Days Inactive (No Login or Order)'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|days_inactive',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Days Since First Order'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|days_since_first_order',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Average Days Between Orders'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|order_frequency_days',
                    ],
                    [
                        'label' => Mage::helper('customersegmentation')->__('Days Without Purchase'),
                        'value' => 'customersegmentation/segment_condition_customer_timebased|days_without_purchase',
                    ],
                ],
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Newsletter Subscription'),
                'value' => [
                    [
                        'label' => Mage::helper('customersegmentation')->__('Is Subscribed'),
                        'value' => 'customersegmentation/segment_condition_customer_newsletter|is_subscribed',
                    ],
                ],
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order History'),
                'value' => $orderConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Shopping Cart'),
                'value' => $cartConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Items'),
                'value' => $cartItemsConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Viewed Products'),
                'value' => $viewedProductsConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Wishlist'),
                'value' => $wishlistConditions,
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

        // Invert the logic: TRUE should mean positive match, FALSE should mean negative match
        if (!$this->getValue()) {
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
