<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

function rss_comment_create_order(): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId((string) random_int(900000000, 999999999));
    $order->setStoreId(1);
    $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->setStatus('complete');
    $order->setCustomerEmail('rss-' . uniqid() . '@example.com');
    $order->setCustomerIsGuest(1);
    $order->setGrandTotal(10.00);
    $order->setBaseGrandTotal(10.00);
    $order->save();
    return $order;
}

function rss_comment_add(Mage_Sales_Model_Order $order, string $type, string $comment, int $visible): void
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $resource = Mage::getSingleton('core/resource');

    if ($type === 'order') {
        $adapter->insert($resource->getTableName('sales/order_status_history'), [
            'parent_id' => $order->getId(),
            'comment' => $comment,
            'is_visible_on_front' => $visible,
            'is_customer_notified' => 0,
            'created_at' => Mage_Core_Model_Locale::nowUtc(),
        ]);
        return;
    }

    $entityTable = $resource->getTableName('sales/' . $type);
    $adapter->insert($entityTable, [
        'store_id' => $order->getStoreId(),
        'order_id' => $order->getId(),
        'increment_id' => (string) random_int(900000000, 999999999),
        'created_at' => Mage_Core_Model_Locale::nowUtc(),
        'updated_at' => Mage_Core_Model_Locale::nowUtc(),
    ]);
    $entityId = $adapter->lastInsertId($entityTable);
    $adapter->insert($resource->getTableName('sales/' . $type . '_comment'), [
        'parent_id' => $entityId,
        'comment' => $comment,
        'is_visible_on_front' => $visible,
        'is_customer_notified' => 0,
        'created_at' => Mage_Core_Model_Locale::nowUtc(),
    ]);
}

afterEach(function () {
    if (isset($this->order)) {
        $this->order->delete();
    }
    Mage::unregister('current_order');
});

describe('Order status feed comments', function () {
    it('lists only comments that are visible on the storefront', function () {
        $order = rss_comment_create_order();
        $this->order = $order;

        rss_comment_add($order, 'order', 'order visible', 1);
        rss_comment_add($order, 'order', 'order hidden', 0);
        rss_comment_add($order, 'invoice', 'invoice visible', 1);
        rss_comment_add($order, 'invoice', 'invoice hidden', 0);
        rss_comment_add($order, 'shipment', 'shipment visible', 1);
        rss_comment_add($order, 'shipment', 'shipment hidden', 0);
        rss_comment_add($order, 'creditmemo', 'creditmemo visible', 1);
        rss_comment_add($order, 'creditmemo', 'creditmemo hidden', 0);

        $rows = Mage::getResourceModel('rss/order')->getAllCommentCollection($order->getId());
        $comments = array_column($rows, 'comment');
        sort($comments);

        expect($comments)->toBe(['creditmemo visible', 'invoice visible', 'order visible', 'shipment visible']);
    });

    it('escapes comment text in the feed', function () {
        $order = rss_comment_create_order();
        $this->order = $order;
        rss_comment_add($order, 'invoice', '<script>alert(1)</script>', 1);

        Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('/rss/order/status', 'GET', ['data' => 'x']),
        ));
        Mage::register('current_order', $order);

        $xml = Mage::app()->getLayout()->createBlock('rss/order_status')->toHtml();

        expect($xml)->not->toContain('<script>');
        expect($xml)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });
});
