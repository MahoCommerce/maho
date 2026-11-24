<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The invoice gives the names, the emails and the message of a gift card to string parameters, so the cart refuses
 * a value that is not a text. A form can send a list with the name "giftcard_message[]".
 */

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->cart = CartParity::cart(withItem: false);
    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId('giftcard')
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-giftcard-text-' . uniqid())->setName('Pest gift card')->setPrice(0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([1])
        ->setData('giftcard_type', 'fixed')
        ->setData('giftcard_amounts', '25,50')
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $this->product->save();
});

afterEach(function () {
    $this->cart->delete();
    Mage::register('isSecureArea', true, true);
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('refuses a name, an email or a message that is not a text', function (string $field) {
    $buyRequest = new Maho\DataObject([
        'qty' => 1, 'giftcard_amount' => 25, 'giftcard_recipient_name' => 'Friend',
        'giftcard_recipient_email' => 'friend@example.com', $field => ['Hello'],
    ]);

    $error = null;
    try {
        Mage::getService('checkout/cart')->addProduct($this->cart, $this->product, $buyRequest);
    } catch (Mage_Core_Exception_Input $e) {
        $error = $e;
    }

    expect($error?->getErrors())->toBe([['field' => $field, 'message' => 'Please enter a text value.']])
        ->and(Mage::getModel('sales/quote')->loadByIdWithoutStore($this->cart->getId())->getAllItems())->toBe([]);
})->with(['giftcard_recipient_name', 'giftcard_recipient_email', 'giftcard_sender_name', 'giftcard_sender_email', 'giftcard_message']);
