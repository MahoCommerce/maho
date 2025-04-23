<?php

class Mage_GoogleAnalytics_Block_Metapixel extends Mage_Core_Block_Template
{
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

        /**
         * This event signifies that an item was removed from a cart.
         *
         * @link https://developers.google.com/tag-platform/gtagjs/reference/events#remove_from_cart
         */
        $removedProducts = Mage::getSingleton('core/session')->getRemovedProductsForAnalytics();
        if ($removedProducts) {
            foreach ($removedProducts as $removedProduct) {
                $eventData = [];
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['value'] = $helper->formatPrice($removedProduct['price'] * $removedProduct['qty']);
                $eventData['contents'] = [];
                $_item = [
                    'id' => $removedProduct['sku'],
                    'name' => $removedProduct['name'],
                    'price' => $helper->formatPrice($removedProduct['price']),
                    'quantity' => (int) $removedProduct['qty'],
                    'item_brand' => $removedProduct['manufacturer'],
                    'item_category' => $removedProduct['category'],
                ];
                $eventData['contents'][] = $_item;
                $result[] = ['remove_from_cart', $eventData];
            }
            Mage::getSingleton('core/session')->unsRemovedProductsForAnalytics();
        }

        /**
         * This event signifies that an item was added to a cart for purchase.
         *
         * @link https://developers.google.com/tag-platform/gtagjs/reference/events#add_to_cart
         */
        $addedProducts = Mage::getSingleton('core/session')->getAddedProductsForAnalytics();
        if ($addedProducts) {
            foreach ($addedProducts as $_addedProduct) {
                $eventData = [];
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['value'] = $helper->formatPrice($_addedProduct['price'] * $_addedProduct['qty']);
                $eventData['contents'] = [];
                $_item = [
                    'id' => $_addedProduct['sku'],
                    'name' => $_addedProduct['name'],
                    'price' => $helper->formatPrice($_addedProduct['price']),
                    'quantity' => (int) $_addedProduct['qty'],
                    'item_brand' => $_addedProduct['manufacturer'],
                    'item_category' => $_addedProduct['category'],
                ];
                $eventData['contents'][] = $_item;
                $result[] = ['add_to_cart', $eventData];
                Mage::getSingleton('core/session')->unsAddedProductsForAnalytics();
            }
        }

        if ($moduleName == 'catalog' && $controllerName == 'product') {
            // This event signifies that some content was shown to the user. Use this event to discover the most popular items viewed.
            // @see https://developers.google.com/tag-platform/gtagjs/reference/events#view_item
            $productViewed = Mage::registry('current_product');
            $category = Mage::registry('current_category') ? Mage::registry('current_category')->getName() : false;
            $eventData = [];
            $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
            $eventData['value'] = $helper->formatPrice($productViewed->getFinalPrice());
            $eventData['contents'] = [];
            $_item = [
                'id' => $productViewed->getSku(),
                'name' => $productViewed->getName(),
                'list_name' => 'Product Detail Page',
                'item_category' => $category,
                'price' => $helper->formatPrice($productViewed->getFinalPrice()),
            ];
            if ($productViewed->getAttributeText('manufacturer')) {
                $_item['item_brand'] = $productViewed->getAttributeText('manufacturer');
            }
            $eventData['contents'][] = $_item;
            $result[] = ['view_item', $eventData];
        } elseif ($moduleName == 'catalog' && $controllerName == 'category') {
            // Log this event when the user has been presented with a list of items of a certain category.
            // @see https://developers.google.com/tag-platform/gtagjs/reference/events#view_item_list
            $layer = Mage::getSingleton('catalog/layer');
            $category = $layer->getCurrentCategory();
            $productCollection = clone $layer->getProductCollection();
            $productCollection->addAttributeToSelect('sku');

            $toolbarBlock = Mage::app()->getLayout()->getBlock('product_list_toolbar');
            $pageSize = $toolbarBlock->getLimit();
            $currentPage = $toolbarBlock->getCurrentPage();
            if ($pageSize !== 'all') {
                $productCollection->setPageSize($pageSize)->setCurPage($currentPage);
            }
            $eventData = [];
            $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
            $eventData['value'] = 0.00;
            $eventData['item_list_id'] = 'category_' . $category->getUrlKey();
            $eventData['item_list_name'] = $category->getName();
            $eventData['contents'] = [];

            $index = 1;
            foreach ($productCollection as $key => $productViewed) {
                $_item = [
                    'id' => $productViewed->getSku(),
                    'index' => $index,
                    'name' => $productViewed->getName(),
                    'price' => $helper->formatPrice($productViewed->getFinalPrice()),
                ];
                if ($productViewed->getAttributeText('manufacturer')) {
                    $_item['item_brand'] = $productViewed->getAttributeText('manufacturer');
                }
                if ($productViewed->getCategory()->getName()) {
                    $_item['item_category'] = $productViewed->getCategory()->getName();
                }
                $eventData['contents'][] = $_item;
                $index++;
                $eventData['value'] += $productViewed->getFinalPrice();
            }
            $eventData['value'] = $helper->formatPrice($eventData['value']);
            $result[] = ['view_item_list', $eventData];
        } elseif ($moduleName == 'checkout' && $controllerName == 'cart') {
            // This event signifies that a user viewed his cart.
            // This event does not exist for Meta Pixel.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
        } elseif ($moduleName == static::CHECKOUT_MODULE_NAME && $controllerName == static::CHECKOUT_CONTROLLER_NAME) {
            // This event signifies that a user has begun a checkout.
            // @see https://developers.google.com/tag-platform/gtagjs/reference/events#begin_checkout
            $productCollection = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();
            if ($productCollection) {
                $eventData = [];
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['value'] = 0.00;
                $eventData['contents'] = [];
                foreach ($productCollection as $productInCart) {
                    if ($productInCart->getParentItem()) {
                        continue;
                    }
                    $_product = $productInCart->getProduct();
                    $_item = [
                        'id' => $_product->getSku(),
                        'name' => $_product->getName(),
                        'price' => $helper->formatPrice($_product->getFinalPrice()),
                        'quantity' => (int) $productInCart->getQty(),
                    ];
                    if ($_product->getAttributeText('manufacturer')) {
                        $_item['item_brand'] = $_product->getAttributeText('manufacturer');
                    }
                    $itemCategory = $helper->getLastCategoryName($_product);
                    if ($itemCategory) {
                        $_item['item_category'] = $itemCategory;
                    }
                    $eventData['contents'][] = $_item;
                    $eventData['value'] += $_product->getFinalPrice();
                }
                $eventData['value'] = $helper->formatPrice($eventData['value']);
                $result[] = ['InitiateCheckout', $eventData];
            }
        }

        // This event signifies when one or more items is purchased by a user.
        // @see https://developers.google.com/tag-platform/gtagjs/reference/events?hl=it#purchase
        $orderIds = $this->getOrderIds();
        if (!empty($orderIds) && is_array($orderIds)) {
            $collection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', ['in' => $orderIds]);
            /** @var Mage_Sales_Model_Order $order */
            foreach ($collection as $order) {
                $orderData = [
                    'currency' => $order->getBaseCurrencyCode(),
                    'transaction_id' => $order->getIncrementId(),
                    'value' => $helper->formatPrice($order->getBaseGrandTotal()),
                    'coupon' => strtoupper((string) $order->getCouponCode()),
                    'shipping' => $helper->formatPrice($order->getBaseShippingAmount()),
                    'tax' => $helper->formatPrice($order->getBaseTaxAmount()),
                    'items' => [],
                ];

                /** @var Mage_Sales_Model_Order_Item $item */
                foreach ($order->getAllItems() as $item) {
                    if ($item->getParentItem()) {
                        continue;
                    }
                    $_product = $item->getProduct();
                    $_item = [
                        'id' => $item->getSku(),
                        'name' => $item->getName(),
                        'quantity' => (int) $item->getQtyOrdered(),
                        'price' => $helper->formatPrice($item->getBasePrice()),
                        'discount' => $helper->formatPrice($item->getBaseDiscountAmount()),
                    ];
                    if ($_product->getAttributeText('manufacturer')) {
                        $_item['item_brand'] = $_product->getAttributeText('manufacturer');
                    }
                    $itemCategory = $helper->getLastCategoryName($_product);
                    if ($itemCategory) {
                        $_item['item_category'] = $itemCategory;
                    }
                    $orderData['items'][] = $_item;
                }
                $result[] = ['purchase', $orderData];
            }
        }

        $metaDataTransport = new Varien_Object();
        $metaDataTransport->setData($result);
        Mage::dispatchEvent('googleanalytics_meta_send_data_before', ['meta_data_transport' => $metaDataTransport]);
        $result = $metaDataTransport->getData();

        foreach ($result as $k => $ga4Event) {
            $result[$k] = "fbq('track', '{$ga4Event[0]}', " . json_encode($ga4Event[1], JSON_THROW_ON_ERROR) . ');';
        }
        return implode("\n", $result);
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
