<?php

/**
 * Maho
 *
 * @package    Mage_GoogleAnalytics
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_GoogleAnalytics_Block_Metapixel extends Mage_Core_Block_Template
{
    protected const CHECKOUT_MODULE_NAME = 'checkout';
    protected const CHECKOUT_CONTROLLER_NAME = 'onepage';

    protected function _isAvailable(): bool
    {
        return Mage::helper('googleanalytics')->isMetaPixelEnabled();
    }

    /**
     * @throws JsonException
     */
    public function _getEnhancedEcommerceData(): string
    {
        $result = [];
        $request = $this->getRequest();
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();
        $helper = Mage::helper('googleanalytics');

        // Meta Pixel doesn't have a standard 'RemoveFromCart' event.
        Mage::getSingleton('core/session')->unsRemovedProductsForAnalytics();

        /**
         * This event signifies that an item was added to a cart for purchase.
         * @link https://developers.facebook.com/docs/meta-pixel/reference#standard-events
         */
        $addedProducts = Mage::getSingleton('core/session')->getAddedProductsForAnalytics();
        if ($addedProducts) {
            foreach ($addedProducts as $_addedProduct) {
                $eventData = [];
                $eventData['value'] = $helper->formatPrice($_addedProduct['price'] * $_addedProduct['qty']);
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['content_name'] = $_addedProduct['name'];
                $eventData['content_type'] = 'product';
                $eventData['contents'] = [
                    [
                        'id' => $_addedProduct['sku'],
                        'quantity' => (int) $_addedProduct['qty'],
                        'item_price' => $helper->formatPrice($_addedProduct['price']),
                    ],
                ];
                $result[] = ['AddToCart', $eventData];
            }
            Mage::getSingleton('core/session')->unsAddedProductsForAnalytics();
        }

        if ($moduleName == 'catalog' && $controllerName == 'product') {
            // This event signifies that some content was shown to the user.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
            $productViewed = Mage::registry('current_product');
            if ($productViewed) {
                $eventData = [];
                $eventData['value'] = $helper->formatPrice($productViewed->getFinalPrice());
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['content_name'] = $productViewed->getName();
                $eventData['content_ids'] = [$productViewed->getSku()];
                $eventData['content_type'] = 'product';
                $eventData['contents'] = [
                    [
                        'id' => $productViewed->getSku(),
                        'quantity' => 1,
                        'item_price' => $helper->formatPrice($productViewed->getFinalPrice()),
                    ],
                ];
                $category = Mage::registry('current_category');
                if ($category) {
                    $eventData['content_category'] = $category->getName();
                }
                $result[] = ['ViewContent', $eventData];
            }
        } elseif ($moduleName == 'catalog' && $controllerName == 'category') {
            // Log this event when the user has been presented with a list of items of a certain category.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
            $layer = Mage::getSingleton('catalog/layer');
            $category = $layer->getCurrentCategory();
            if ($category) {
                $productCollection = clone $layer->getProductCollection();
                $productCollection->addAttributeToSelect(['sku', 'name', 'price', 'special_price', 'final_price']); // Select necessary attributes

                $toolbarBlock = Mage::app()->getLayout()->getBlock('product_list_toolbar');
                if ($toolbarBlock) {
                    $pageSize = $toolbarBlock->getLimit();
                    $currentPage = $toolbarBlock->getCurrentPage();
                    if ($pageSize !== 'all') {
                        $productCollection->setPageSize($pageSize)->setCurPage($currentPage);
                    }
                } else {
                    // Fallback or default page size if toolbar isn't available
                    $productCollection->setPageSize(12)->setCurPage(1);
                }

                if ($productCollection->getSize() > 0) {
                    $contentIds = [];
                    $contents = [];
                    $totalValue = 0.00;

                    foreach ($productCollection as $productViewed) {
                        $productId = $productViewed->getSku();
                        $productPrice = $helper->formatPrice($productViewed->getFinalPrice());
                        $contentIds[] = $productId;
                        $contents[] = [
                            'id' => $productId,
                            'quantity' => 1, // Quantity is typically 1 for a list view item
                            'item_price' => $productPrice,
                        ];
                        $totalValue += $productViewed->getFinalPrice();
                    }

                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($totalValue); // Sum of displayed product prices
                    $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                    ;
                    $eventData['content_name'] = $category->getName();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product_group'; // Use 'product_group' for lists/categories
                    $eventData['contents'] = $contents;
                    $result[] = ['ViewContent', $eventData];
                }
            }
        } elseif ($moduleName == 'checkout' && $controllerName == 'cart') {
            // Meta Pixel Standard Events do not include a specific "ViewCart" event.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
        } elseif ($moduleName == static::CHECKOUT_MODULE_NAME && $controllerName == static::CHECKOUT_CONTROLLER_NAME) {
            /**
             * Meta Pixel Event: InitiateCheckout
             * This event signifies that a user has begun the checkout process.
             * @link https://developers.facebook.com/docs/meta-pixel/reference#standard-events
             */
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if ($quote && $quote->hasItems()) {
                $productCollection = $quote->getAllVisibleItems(); // Use visible items to avoid double counting configurables etc.
                if (!empty($productCollection)) {
                    $contentIds = [];
                    $contents = [];
                    $totalValue = 0.00;
                    $numItems = 0;

                    foreach ($productCollection as $item) {
                        $_product = $item->getProduct();
                        $productId = $_product->getSku();
                        $contentIds[] = $productId;
                        $contents[] = [
                            'id' => $productId,
                            'quantity' => (int) $item->getQty(),
                            'item_price' => $helper->formatPrice($item->getBasePrice()),
                        ];
                        $totalValue += $item->getBaseRowTotal();
                        $numItems += (int) $item->getQty();
                    }

                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($totalValue);
                    $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product';
                    $eventData['num_items'] = $numItems;
                    $eventData['contents'] = $contents;
                    $result[] = ['InitiateCheckout', $eventData];
                }
            }
        }

        // This event signifies when one or more items is purchased by a user.
        // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
        $orderIds = $this->getOrderIds(); // Assuming this method retrieves order IDs from the success page
        if (!empty($orderIds) && is_array($orderIds)) {
            $collection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', ['in' => $orderIds]);

            /** @var Mage_Sales_Model_Order $order */
            foreach ($collection as $order) {
                $contentIds = [];
                $contents = [];
                $numItems = 0;

                // Use visible items to avoid double counting configurables, etc.
                /** @var Mage_Sales_Model_Order_Item $item */
                foreach ($order->getAllVisibleItems() as $item) {
                    $productId = $item->getSku();
                    $contentIds[] = $productId;
                    $contents[] = [
                        'id' => $productId,
                        'quantity' => (int) $item->getQtyOrdered(),
                        'item_price' => $helper->formatPrice($item->getBasePrice()),
                    ];
                    $numItems += (int) $item->getQtyOrdered();
                }

                if (!empty($contents)) {
                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($order->getBaseGrandTotal());
                    $eventData['currency'] = $order->getBaseCurrencyCode();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product';
                    $eventData['contents'] = $contents;
                    $eventData['num_items'] = $numItems;
                    $eventData['order_id'] = $order->getIncrementId();
                    $result[] = ['Purchase', $eventData];
                }
            }
        }

        $metaDataTransport = new \Maho\DataObject();
        $metaDataTransport->setData($result);
        Mage::dispatchEvent('googleanalytics_meta_send_data_before', ['meta_data_transport' => $metaDataTransport]);
        $result = $metaDataTransport->getData();

        $eventStrings = [];
        foreach ($result as $metaEvent) {
            $eventDataJson = json_encode($metaEvent[1], JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);
            $eventDataJsonEscaped = str_replace("'", "\\'", $eventDataJson);
            $eventStrings[] = "fbq('track', '{$metaEvent[0]}', {$eventDataJsonEscaped});";
        }
        return implode("\n", $eventStrings);
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->_isAvailable()) {
            return '';
        }
        return parent::_toHtml();
    }
}
