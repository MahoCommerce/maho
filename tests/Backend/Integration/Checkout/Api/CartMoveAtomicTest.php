<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class CartMoveAtomicQuote extends Mage_Sales_Model_Quote
{
    public static ?int $failQuoteId = null;
    public static ?Throwable $failure = null;

    #[\Override]
    public function save()
    {
        if (self::$failure !== null && self::$failQuoteId === (int) $this->getId()) {
            throw self::$failure;
        }
        return parent::save();
    }
}

it('rolls back the target cart when the source cart save fails', function (Throwable $failure, string $errorMessage): void {
    $config = Mage::getConfig();
    $config->setNode('global/models/sales/rewrite/quote', CartMoveAtomicQuote::class);
    CartMoveAtomicQuote::$failQuoteId = null;

    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setGroupId(1)
        ->setFirstname('Cart')
        ->setLastname('Move')
        ->setEmail('cart-move-atomic-' . uniqid() . '@example.com')
        ->setForceConfirmed(true)
        ->save();

    $target = Mage::getModel('sales/quote')
        ->setStoreId(1)
        ->setCustomer($customer)
        ->setCustomerIsGuest(false)
        ->setIsActive(true)
        ->save();

    $source = Mage::getModel('sales/quote')
        ->setStoreId(1)
        ->setCustomer($customer)
        ->setCustomerIsGuest(false)
        ->setIsActive(false);
    $source->addProduct(loadSimplePricedProduct(), 1);
    $source->save();

    $sourceItem = $source->getAllVisibleItems()[0];
    $productId = (int) $sourceItem->getProductId();
    $sourceId = (int) $source->getId();
    $targetId = (int) $target->getId();
    try {
        CartMoveAtomicQuote::$failQuoteId = $sourceId;
        CartMoveAtomicQuote::$failure = $failure;
        $resource = $source->getResource();

        try {
            (new Mage_Checkout_Model_Cart_Product_Api())->moveToCustomerQuote(
                $sourceId,
                [['product_id' => $productId, 'qty' => 1]],
                1,
            );
            $error = null;
        } catch (Mage_Api_Exception|Error $e) {
            $error = $e->getMessage();
        }

        $reloadedTarget = Mage::getModel('sales/quote')->load($targetId);
        $reloadedSource = Mage::getModel('sales/quote')->load($sourceId);
        $itemProductIds = static fn(Mage_Sales_Model_Quote $quote): array => array_map(
            static fn(Mage_Sales_Model_Quote_Item $item): int => (int) $item->getProductId(),
            $quote->getAllVisibleItems(),
        );

        expect($error)->toBe($errorMessage)
            ->and($resource->getReadConnection()->getTransactionLevel())->toBe(0)
            ->and($itemProductIds($reloadedTarget))->toBe([])
            ->and($itemProductIds($reloadedSource))->toBe([$productId]);
    } finally {
        CartMoveAtomicQuote::$failQuoteId = null;
        CartMoveAtomicQuote::$failure = null;
        $resource = $source->getResource();
        while ($resource->getReadConnection()->getTransactionLevel() > 0) {
            $resource->rollBack();
        }
        $config->setNode('global/models/sales/rewrite/quote', '');
        Mage::getModel('sales/quote')->load($targetId)->delete();
        Mage::getModel('sales/quote')->load($sourceId)->delete();
        $customer->delete();
    }
})->with([
    'exception' => [new RuntimeException('Source quote save failed.'), 'product_move_quote_save_fault'],
    'error' => [new Error('Source quote save failed.'), 'Source quote save failed.'],
]);
