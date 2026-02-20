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

        // Generate order conditions from condition classes
        $orderConditions = [];

        // Order Attributes
        $orderAttributesCondition = Mage::getModel('customersegmentation/segment_condition_order_attributes');
        $orderAttributesCondition->loadAttributeOptions();
        $orderAttributes = $orderAttributesCondition->getAttributeOption();
        foreach ($orderAttributes as $code => $label) {
            $orderConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_order_attributes|' . $code,
            ];
        }

        // Customer CLV conditions
        $clvCondition = Mage::getModel('customersegmentation/segment_condition_customer_clv');
        $clvCondition->loadAttributeOptions();
        $clvAttributes = $clvCondition->getAttributeOption();
        foreach ($clvAttributes as $code => $label) {
            $orderConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_customer_clv|' . $code,
            ];
        }
        usort($orderConditions, function ($a, $b) {
            return strcasecmp($a['label'], $b['label']);
        });

        // Generate order items conditions from condition class
        $orderItemsConditions = [];
        $orderItemsCondition = Mage::getModel('customersegmentation/segment_condition_order_items');
        $orderItemsCondition->loadAttributeOptions();
        $orderItemsAttributes = $orderItemsCondition->getAttributeOption();
        foreach ($orderItemsAttributes as $code => $label) {
            $orderItemsConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_order_items|' . $code,
            ];
        }

        // Generate customer personal conditions from condition class
        $customerPersonalConditions = [];
        $customerAttributesCondition = Mage::getModel('customersegmentation/segment_condition_customer_attributes');
        $customerAttributesCondition->loadAttributeOptions();
        $customerAttributes = $customerAttributesCondition->getAttributeOption();
        foreach ($customerAttributes as $code => $label) {
            $customerPersonalConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_customer_attributes|' . $code,
            ];
        }

        // Generate customer address conditions from condition class
        $customerAddressConditions = [];
        $customerAddressCondition = Mage::getModel('customersegmentation/segment_condition_customer_address');
        $customerAddressCondition->loadAttributeOptions();
        $addressAttributes = $customerAddressCondition->getAttributeOption();
        foreach ($addressAttributes as $code => $label) {
            $customerAddressConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_customer_address|' . $code,
            ];
        }

        // Generate cart conditions from condition class
        $cartConditions = [];
        $cartAttributesCondition = Mage::getModel('customersegmentation/segment_condition_cart_attributes');
        $cartAttributesCondition->loadAttributeOptions();
        $cartAttributes = $cartAttributesCondition->getAttributeOption();
        foreach ($cartAttributes as $code => $label) {
            $cartConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_cart_attributes|' . $code,
            ];
        }

        // Generate cart items conditions from condition class
        $cartItemsConditions = [];
        $cartItemsCondition = Mage::getModel('customersegmentation/segment_condition_cart_items');
        $cartItemsCondition->loadAttributeOptions();
        $cartItemsAttributes = $cartItemsCondition->getAttributeOption();
        foreach ($cartItemsAttributes as $code => $label) {
            $cartItemsConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_cart_items|' . $code,
            ];
        }

        // Generate viewed products conditions from condition class
        $viewedProductsConditions = [];
        $viewedProductsCondition = Mage::getModel('customersegmentation/segment_condition_product_viewed');
        $viewedProductsCondition->loadAttributeOptions();
        $viewedProductsAttributes = $viewedProductsCondition->getAttributeOption();
        foreach ($viewedProductsAttributes as $code => $label) {
            $viewedProductsConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_product_viewed|' . $code,
            ];
        }

        // Generate wishlist conditions from condition class
        $wishlistConditions = [];
        $wishlistCondition = Mage::getModel('customersegmentation/segment_condition_product_wishlist');
        $wishlistCondition->loadAttributeOptions();
        $wishlistAttributes = $wishlistCondition->getAttributeOption();
        foreach ($wishlistAttributes as $code => $label) {
            $wishlistConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_product_wishlist|' . $code,
            ];
        }

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
                'value' => $this->getTimebasedConditions(),
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Newsletter Subscription'),
                'value' => $this->getNewsletterConditions(),
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order History'),
                'value' => $orderConditions,
            ],
            [
                'label' => Mage::helper('customersegmentation')->__('Order Items'),
                'value' => $orderItemsConditions,
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

    /**
     * Get time-based conditions from condition class
     */
    protected function getTimebasedConditions(): array
    {
        $timebasedConditions = [];
        $timebasedCondition = Mage::getModel('customersegmentation/segment_condition_customer_timebased');
        $timebasedCondition->loadAttributeOptions();
        $timebasedAttributes = $timebasedCondition->getAttributeOption();
        foreach ($timebasedAttributes as $code => $label) {
            $timebasedConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_customer_timebased|' . $code,
            ];
        }
        return $timebasedConditions;
    }

    /**
     * Get newsletter conditions from condition class
     */
    protected function getNewsletterConditions(): array
    {
        $newsletterConditions = [];
        $newsletterCondition = Mage::getModel('customersegmentation/segment_condition_customer_newsletter');
        $newsletterCondition->loadAttributeOptions();
        $newsletterAttributes = $newsletterCondition->getAttributeOption();
        foreach ($newsletterAttributes as $code => $label) {
            $newsletterConditions[] = [
                'label' => $label,
                'value' => 'customersegmentation/segment_condition_customer_newsletter|' . $code,
            ];
        }
        return $newsletterConditions;
    }

    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $conditions = [];
        $aggregator = $this->getAggregator();

        foreach ($this->getConditions() as $condition) {
            if ($condition instanceof Maho_CustomerSegmentation_Model_Segment_Condition_Abstract ||
                $condition instanceof Maho_CustomerSegmentation_Model_Segment_Condition_Combine) {
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
