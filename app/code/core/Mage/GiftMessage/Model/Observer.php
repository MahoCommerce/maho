<?php

/**
 * Maho
 *
 * @package    Mage_GiftMessage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_GiftMessage_Model_Observer extends \Maho\DataObject
{
    /**
     * Set gift messages to order item on import item
     *
     * @return $this
     */
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
    public function salesEventConvertQuoteToOrder(\Maho\Event\Observer $observer)
    {
        $observer->getEvent()->getOrder()
            ->setGiftMessageId($observer->getEvent()->getQuote()->getGiftMessageId());
        return $this;
    }

    /**
     * Operate with gift messages on checkout process
     *
     * @return $this
     */
    public function checkoutEventCreateGiftMessage(\Maho\Event\Observer $observer)
    {
        $giftMessages = $observer->getEvent()->getRequest()->getParam('giftmessage');
        $quote = $observer->getEvent()->getQuote();
        /** @var Mage_Sales_Model_Quote $quote */
        if (is_array($giftMessages)) {
            foreach ($giftMessages as $key => $message) {
                $giftMessage = Mage::getModel('giftmessage/message');

                // Extract the actual entity ID from the prefixed key
                if (str_contains($key, '_')) {
                    $entityId = substr($key, strpos($key, '_') + 1);
                } else {
                    // Fallback for backward compatibility
                    $entityId = $key;
                }

                $entity = match ($message['type']) {
                    'quote' => $quote,
                    'quote_item' => $quote->getItemById($entityId),
                    'quote_address' => $quote->getAddressById($entityId),
                    'quote_address_item' => $quote->getAddressById($message['address'])->getItemById($entityId),
                    default => $quote,
                };

                if ($entity->getGiftMessageId()) {
                    $giftMessage->load($entity->getGiftMessageId());
                }

                if (trim($message['message']) == '') {
                    if ($giftMessage->getId()) {
                        try {
                            $giftMessage->delete();
                            $entity->setGiftMessageId(0)
                                ->save();
                        } catch (Exception $e) {
                        }
                    }
                    continue;
                }

                try {
                    $giftMessage->setSender($message['from'])
                        ->setRecipient($message['to'])
                        ->setMessage($message['message'])
                        ->save();

                    $entity->setGiftMessageId($giftMessage->getId())
                        ->save();
                } catch (Exception $e) {
                }
            }
        }
        return $this;
    }

    /**
     * Duplicates giftmessage from order to quote on import or reorder
     *
     * @return $this
     */
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
