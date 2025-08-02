<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag('customer_segmentation/general/enabled', $store);
    }

    public function getRefreshFrequency(mixed $store = null): int
    {
        return (int) Mage::getStoreConfig('customer_segmentation/general/refresh_frequency', $store);
    }

    public function getBatchSize(mixed $store = null): int
    {
        return (int) Mage::getStoreConfig('customer_segmentation/general/batch_size', $store);
    }

    public function isCachingEnabled(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag('customer_segmentation/performance/enable_caching', $store);
    }

    public function getCacheLifetime(mixed $store = null): int
    {
        return (int) Mage::getStoreConfig('customer_segmentation/performance/cache_lifetime', $store);
    }

    public function isPriceRuleIntegrationEnabled(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag('customer_segmentation/integrations/enable_price_rules', $store);
    }

    public function isNewsletterIntegrationEnabled(mixed $store = null): bool
    {
        return Mage::getStoreConfigFlag('customer_segmentation/integrations/enable_newsletter', $store);
    }

    public function getCustomerSegmentIds(int $customerId, ?int $websiteId = null): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        $resource = Mage::getResourceModel('customersegmentation/segment');
        return $resource->getCustomerSegmentIds($customerId, $websiteId);
    }

    public function getCustomerSegments(int $customerId, ?int $websiteId = null): Maho_CustomerSegmentation_Model_Resource_Segment_Collection
    {
        $collection = Mage::getResourceModel('customersegmentation/segment_collection')
            ->addIsActiveFilter()
            ->addCustomerFilter($customerId);

        if ($websiteId !== null) {
            $collection->addWebsiteFilter($websiteId);
        }

        return $collection;
    }

    public function getActiveSegments(int $websiteId): Maho_CustomerSegmentation_Model_Resource_Segment_Collection
    {
        return Mage::getResourceModel('customersegmentation/segment_collection')
            ->addIsActiveFilter()
            ->addWebsiteFilter($websiteId);
    }

    public function formatSegmentList(array|Maho_CustomerSegmentation_Model_Resource_Segment_Collection $segments): string
    {
        if ($segments instanceof Maho_CustomerSegmentation_Model_Resource_Segment_Collection) {
            $segments = $segments->getColumnValues('name');
        }

        if (empty($segments)) {
            return $this->__('None');
        }

        return implode(', ', $segments);
    }

    /**
     * Get condition types for select
     */
    public function getConditionTypes(): array
    {
        return [
            'customer' => [
                'label' => $this->__('Customer Attributes'),
                'value' => [
                    [
                        'label' => $this->__('Personal Information'),
                        'value' => 'customersegmentation/segment_condition_customer_attributes',
                    ],
                    [
                        'label' => $this->__('Address Information'),
                        'value' => 'customersegmentation/segment_condition_customer_address',
                    ],
                    [
                        'label' => $this->__('Newsletter Subscription'),
                        'value' => 'customersegmentation/segment_condition_customer_newsletter',
                    ],
                ],
            ],
            'order' => [
                'label' => $this->__('Order History'),
                'value' => [
                    [
                        'label' => $this->__('Payment Method'),
                        'value' => 'customersegmentation/segment_condition_order_attributes|payment_method',
                    ],
                    [
                        'label' => $this->__('Shipping Method'),
                        'value' => 'customersegmentation/segment_condition_order_attributes|shipping_method',
                    ],
                    [
                        'label' => $this->__('Order Status'),
                        'value' => 'customersegmentation/segment_condition_order_attributes|status',
                    ],
                    [
                        'label' => $this->__('Store'),
                        'value' => 'customersegmentation/segment_condition_order_attributes|store_id',
                    ],
                    [
                        'label' => $this->__('Grand Total'),
                        'value' => 'customersegmentation/segment_condition_order_attributes|grand_total',
                    ],
                ],
            ],
            'cart' => [
                'label' => $this->__('Shopping Cart'),
                'value' => [
                    [
                        'label' => $this->__('Cart Attributes'),
                        'value' => 'customersegmentation/segment_condition_cart_attributes',
                    ],
                    [
                        'label' => $this->__('Cart Items'),
                        'value' => 'customersegmentation/segment_condition_cart_items',
                    ],
                ],
            ],
            'product' => [
                'label' => $this->__('Product Interactions'),
                'value' => [
                    [
                        'label' => $this->__('Viewed Products'),
                        'value' => 'customersegmentation/segment_condition_product_viewed',
                    ],
                    [
                        'label' => $this->__('Wishlist Items'),
                        'value' => 'customersegmentation/segment_condition_product_wishlist',
                    ],
                ],
            ],
        ];
    }
}
