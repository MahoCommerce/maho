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

        $customerPersonalConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Email'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|email',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('First Name'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|firstname',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Last Name'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|lastname',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Gender'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|gender',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Date of Birth'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|dob',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Since'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|created_at',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Customer Group'),
                'value' => 'customersegmentation/segment_condition_customer_attributes|group_id',
            ],
        ];

        $customerAddressConditions = [
            // These would be specific address attributes
            [
                'label' => Mage::helper('customersegmentation')->__('Street Address'),
                'value' => 'customersegmentation/segment_condition_customer_address|street',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('City'),
                'value' => 'customersegmentation/segment_condition_customer_address|city',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Country'),
                'value' => 'customersegmentation/segment_condition_customer_address|country_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('State/Province'),
                'value' => 'customersegmentation/segment_condition_customer_address|region',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Postal Code'),
                'value' => 'customersegmentation/segment_condition_customer_address|postcode',
            ],
        ];

        $cartConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Total'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|total',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Cart Items Count'),
                'value' => 'customersegmentation/segment_condition_cart_attributes|items_count',
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
        usort($cartItemsConditions, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        $viewedProductsConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Product SKU'),
                'value' => 'customersegmentation/segment_condition_product_viewed|sku',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Product Category'),
                'value' => 'customersegmentation/segment_condition_product_viewed|category_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('View Count'),
                'value' => 'customersegmentation/segment_condition_product_viewed|view_count',
            ],
        ];

        $wishlistConditions = [
            [
                'label' => Mage::helper('customersegmentation')->__('Product SKU'),
                'value' => 'customersegmentation/segment_condition_product_wishlist|sku',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Product Category'),
                'value' => 'customersegmentation/segment_condition_product_wishlist|category_id',
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Items Count'),
                'value' => 'customersegmentation/segment_condition_product_wishlist|items_count',
            ],
        ];

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
