<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class ShippingBridgeCarrierProbe extends Maho_ShippingBridge_Model_Carrier
{
    public function parse(string $responseBody): ?array
    {
        return $this->_parseResponse($responseBody);
    }

    public function payload(Mage_Shipping_Model_Rate_Request $request): array
    {
        return $this->_buildRequestPayload($request);
    }
}

it('skips a method whose code, title or price has the wrong type, and keeps the valid ones', function (): void {
    $methods = (new ShippingBridgeCarrierProbe())->parse(Mage::helper('core')->jsonEncode(['methods' => [
        ['code' => 123, 'title' => 'Numeric code', 'price' => 5],
        ['code' => 'array_title', 'title' => ['Express'], 'price' => 5],
        ['code' => 'text_price', 'title' => 'Text price', 'price' => 'free'],
        ['code' => 'standard', 'title' => 'Standard', 'price' => 5.99],
    ]]));

    expect($methods)->toBe([['code' => 'standard', 'title' => 'Standard', 'price' => 5.99]]);
});

it('sends the name on the shipping address of the quote', function (): void {
    $quote = Mage::getModel('sales/quote');
    $quote->getShippingAddress()->setFirstname('John')->setLastname('Doe');

    $item = Mage::getModel('sales/quote_item');
    $item->setQuote($quote);
    $item->setProduct(Mage::getModel('catalog/product')->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE));

    $request = Mage::getModel('shipping/rate_request');
    $request->setAllItems([$item]);
    $request->setPackageCurrency(Mage::getModel('directory/currency')->load('USD'));

    $payload = (new ShippingBridgeCarrierProbe())->payload($request);

    expect($payload['shipping_address']['firstname'])->toBe('John')
        ->and($payload['shipping_address']['lastname'])->toBe('Doe');
});
