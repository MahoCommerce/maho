<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

class Mage_Rss_Helper_Order extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Rss';

    /**
     * Check whether status notification is allowed
     *
     * @return bool
     */
    public function isStatusNotificationAllow()
    {
        if (Mage::getStoreConfig('rss/order/status_notified')) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve order status history url
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getStatusHistoryRssUrl($order)
    {
        $key = $this->getStatusUrlKey($order);
        if ($key === '') {
            return '';
        }
        return $this->_getUrl(
            'rss/order/status',
            ['_secure' => true, '_query' => ['data' => $key]],
        );
    }

    /**
     * Retrieve order status url key. The key is signed with the order protect code, so it
     * cannot be built from the order ids alone. An order without a protect code gets no key.
     *
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getStatusUrlKey($order)
    {
        $protectCode = (string) $order->getProtectCode();
        if ($protectCode === '') {
            return '';
        }
        $data = [
            'order_id' => (int) $order->getId(),
            'increment_id' => (string) $order->getIncrementId(),
            'customer_id' => $order->getCustomerId() === null ? null : (int) $order->getCustomerId(),
        ];
        $data['signature'] = $this->signStatusUrlData($data, $protectCode);
        return base64_encode(json_encode($data));
    }

    /**
     * Retrieve order instance by specified status url key
     *
     * @param string $key
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrderByStatusUrlKey($key)
    {
        $decoded = base64_decode($key, true);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        if (!is_array($data) || !isset($data['order_id']) || !isset($data['increment_id'])
            || !array_key_exists('customer_id', $data) || !isset($data['signature'])
            || !is_scalar($data['order_id']) || !is_scalar($data['increment_id'])
            || !is_string($data['signature'])
            || ($data['customer_id'] !== null && !is_scalar($data['customer_id']))
        ) {
            return null;
        }

        $orderId = (int) $data['order_id'];
        $incrementId = (string) $data['increment_id'];
        $customerId = $data['customer_id'] === null ? null : (int) $data['customer_id'];

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($orderId);
        $protectCode = (string) $order->getProtectCode();
        if (is_null($order->getId()) || $protectCode === '') {
            return null;
        }

        $expected = $this->signStatusUrlData([
            'order_id' => (int) $order->getId(),
            'increment_id' => (string) $order->getIncrementId(),
            'customer_id' => $order->getCustomerId() === null ? null : (int) $order->getCustomerId(),
        ], $protectCode);

        if (hash_equals($expected, $data['signature'])
            && (string) $order->getIncrementId() === $incrementId
            && ($order->getCustomerId() === null ? null : (int) $order->getCustomerId()) === $customerId
        ) {
            return $order;
        }

        return null;
    }

    /**
     * @param array{order_id: int, increment_id: string, customer_id: int|null} $data
     */
    protected function signStatusUrlData(array $data, string $protectCode): string
    {
        $payload = $data['order_id'] . ':' . $data['increment_id'] . ':' . ($data['customer_id'] ?? '');
        return hash_hmac('sha256', $payload, $protectCode);
    }
}
