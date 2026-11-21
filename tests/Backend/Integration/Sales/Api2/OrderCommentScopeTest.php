<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function orderCommentScopeOrder(string $email): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')
        ->setStoreId(1)
        ->setIncrementId('COMMENT-SCOPE-' . bin2hex(random_bytes(4)))
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setStatus('processing')
        ->setCustomerIsGuest()
        ->setCustomerEmail($email)
        ->setBaseCurrencyCode('USD')
        ->setOrderCurrencyCode('USD')
        ->setGlobalCurrencyCode('USD')
        ->setBaseToGlobalRate(1)
        ->setBaseToOrderRate(1)
        ->setGrandTotal(10)
        ->setBaseGrandTotal(10)
        ->save();
}

function orderCommentScopeComment(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order_Status_History
{
    return Mage::getModel('sales/order_status_history')
        ->setParentId((int) $order->getId())
        ->setComment('scoped comment')
        ->setStatus('processing')
        ->setIsVisibleOnFront(false)
        ->save();
}

function retrieveOrderComment(int $commentId, ?int $orderId): array
{
    $resource = new class extends Mage_Sales_Model_Api2_Order_Comment_Rest_Admin_V1 {
        public function retrieve(): array
        {
            return $this->_retrieve();
        }
    };
    $request = new Mage_Api2_Model_Request();
    $request->setParam(Mage_Sales_Model_Api2_Order_Comment_Rest::PARAM_COMMENT_ID, $commentId);
    if ($orderId !== null) {
        $request->setParam('order_id', $orderId);
    }
    $resource->setRequest($request);

    return $resource->retrieve();
}

afterEach(function () {
    foreach ($this->orders ?? [] as $order) {
        Mage::getModel('sales/order')->load($order->getId())->delete();
    }
});

it('returns a comment only under its own order context', function () {
    $order = orderCommentScopeOrder('scope-a@example.com');
    $other = orderCommentScopeOrder('scope-b@example.com');
    $this->orders = [$order, $other];
    $comment = orderCommentScopeComment($order);

    expect(retrieveOrderComment((int) $comment->getId(), (int) $order->getId()))
        ->toMatchArray(['comment' => 'scoped comment'])
        ->and(fn() => retrieveOrderComment((int) $comment->getId(), (int) $other->getId()))
        ->toThrow(Mage_Api2_Exception::class);
});
