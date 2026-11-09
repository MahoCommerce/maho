<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GiftMessage
 */

class Mage_GiftMessage_Model_Observer extends \Maho\DataObject
{
    /**
     * Set gift messages to order item on import item
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_quote_item_to_order_item')]
    public function salesEventConvertQuoteItemToOrderItem(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = $observer->getEvent()->getOrderItem();
        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getEvent()->getItem();

        $isAvailable = Mage::helper('giftmessage/message')->getIsMessagesAvailable(
            'item',
            $quoteItem,
            $quoteItem->getStoreId(),
        );

        $orderItem->setGiftMessageId($quoteItem->getGiftMessageId())
            ->setGiftMessageAvailable($isAvailable);
        return $this;
    }

    /**
     * Set gift messages to order from quote address
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_quote_address_to_order')]
    public function salesEventConvertQuoteAddressToOrder(\Maho\Event\Observer $observer)
    {
        if ($observer->getEvent()->getAddress()->getGiftMessageId()) {
            $observer->getEvent()->getOrder()
                ->setGiftMessageId($observer->getEvent()->getAddress()->getGiftMessageId());
        }
        return $this;
    }

    /**
     * Set gift messages to order from quote address
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_quote_to_order')]
    public function salesEventConvertQuoteToOrder(\Maho\Event\Observer $observer)
    {
        $observer->getEvent()->getOrder()
            ->setGiftMessageId($observer->getEvent()->getQuote()->getGiftMessageId());
        return $this;
    }

    /**
     * Save the gift messages of the shipping method step of the checkout through Mage_Checkout_Service_Cart, which
     * the API uses too. The step saves the shipping method, so a message that the store refuses does not stop it.
     *
     * @return $this
     */
    #[Maho\Config\Observer('checkout_controller_onepage_save_shipping_method', area: 'frontend')]
    public function checkoutEventCreateGiftMessage(\Maho\Event\Observer $observer)
    {
        $giftMessages = $observer->getEvent()->getRequest()->getParam('giftmessage');
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        if (!is_array($giftMessages)) {
            return $this;
        }

        /** @var Mage_Checkout_Service_Cart $service */
        $service = Mage::getService('checkout/cart');
        foreach ($giftMessages as $key => $message) {
            if (!is_array($message)) {
                continue;
            }
            $type = $message['type'] ?? 'quote';
            if ($type !== 'quote' && $type !== 'quote_item') {
                continue;
            }
            // The form names the message of an item item_<item ID>, an older form names it <item ID>
            $key = (string) $key;
            $itemId = $type === 'quote_item' ? (int) (str_contains($key, '_') ? substr($key, strpos($key, '_') + 1) : $key) : null;

            try {
                $service->setGiftMessage(
                    $quote,
                    $itemId,
                    (string) ($message['from'] ?? ''),
                    (string) ($message['to'] ?? ''),
                    (string) ($message['message'] ?? ''),
                );
            } catch (Mage_Core_Exception) {
                // The store does not allow this message, or the item is not in the cart
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $this;
    }

    /**
     * Duplicates giftmessage from order to quote on import or reorder
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_order_to_quote', area: 'adminhtml')]
    #[Maho\Config\Observer('sales_convert_order_to_quote', area: 'frontend')]
    public function salesEventOrderToQuote(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();
        // Do not import giftmessage data if order is reordered
        if ($order->getReordered()) {
            return $this;
        }

        if (!Mage::helper('giftmessage/message')->isMessagesAvailable('order', $order, $order->getStore())) {
            return $this;
        }
        $giftMessageId = $order->getGiftMessageId();
        if ($giftMessageId) {
            $giftMessage = Mage::getModel('giftmessage/message')->load($giftMessageId)
                ->setId(null)
                ->save();
            $observer->getEvent()->getQuote()->setGiftMessageId($giftMessage->getId());
        }

        return $this;
    }

    /**
     * Duplicates giftmessage from order item to quote item on import or reorder
     *
     * @return $this
     */
    #[Maho\Config\Observer('sales_convert_order_item_to_quote_item', area: 'adminhtml')]
    public function salesEventOrderItemToQuoteItem(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Sales_Model_Order_Item $orderItem */
        $orderItem = $observer->getEvent()->getOrderItem();
        // Do not import giftmessage data if order is reordered
        $order = $orderItem->getOrder();
        if ($order && $order->getReordered()) {
            return $this;
        }

        $isAvailable = Mage::helper('giftmessage/message')->isMessagesAvailable(
            'order_item',
            $orderItem,
            $orderItem->getStoreId(),
        );
        if (!$isAvailable) {
            return $this;
        }

        /** @var Mage_Sales_Model_Quote_Item $quoteItem */
        $quoteItem = $observer->getEvent()->getQuoteItem();
        if ($giftMessageId = $orderItem->getGiftMessageId()) {
            $giftMessage = Mage::getModel('giftmessage/message')->load($giftMessageId)
                ->setId(null)
                ->save();
            $quoteItem->setGiftMessageId($giftMessage->getId());
        }
        return $this;
    }
}
