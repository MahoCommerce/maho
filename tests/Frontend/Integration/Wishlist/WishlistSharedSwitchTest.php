<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The page of a shared wishlist loads the wishlist through Mage_Wishlist_Service_Wishlist::getByCode() (#1348),
 * so the "Enabled" switch of the wishlist applies to a shared link as it applies to the owner and to the API.
 */

function wishlistSharedController(string $code): Mage_Wishlist_SharedController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/wishlist/shared', 'GET', ['code' => $code]));
    $request->setRouteName('wishlist')->setControllerName('shared')->setActionName('index')->setDispatched(true);
    return new Mage_Wishlist_SharedController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setEmail('wishlist-shared-' . uniqid() . '@example.com')
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $this->customer->save();
    $this->wishlist = Mage::getService('wishlist/wishlist')->getForCustomer((int) $this->customer->getId(), 1);
    $this->wishlist->setShared()->save();
    $this->code = (string) $this->wishlist->getSharingCode();
});

afterEach(function () {
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '1');
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('returns the shared wishlist of a code', function () {
    $loadWishlist = new ReflectionMethod(Mage_Wishlist_SharedController::class, '_getWishlist');

    expect((int) Mage::getService('wishlist/wishlist')->getByCode($this->code, 1)->getId())->toBe((int) $this->wishlist->getId())
        ->and((int) $loadWishlist->invoke(wishlistSharedController($this->code))->getId())->toBe((int) $this->wishlist->getId());
});

it('finds no shared wishlist when the store has no wishlist', function () {
    Mage::app()->getStore()->setConfig(Mage_Wishlist_Service_Wishlist::XML_PATH_ENABLED, '0');
    $loadWishlist = new ReflectionMethod(Mage_Wishlist_SharedController::class, '_getWishlist');

    expect(fn() => Mage::getService('wishlist/wishlist')->getByCode($this->code, 1))
        ->toThrow(Mage_Core_Exception_NoSuchEntity::class, 'The wishlist is not available.')
        ->and($loadWishlist->invoke(wishlistSharedController($this->code)))->toBeFalse();
});

it('finds no wishlist for a code that is unknown, empty, or of a wishlist that is not shared', function () {
    $service = Mage::getService('wishlist/wishlist');
    expect(fn() => $service->getByCode('no-such-code', 1))->toThrow(Mage_Core_Exception_NoSuchEntity::class)
        ->and(fn() => $service->getByCode('', 1))->toThrow(Mage_Core_Exception_NoSuchEntity::class);

    $this->wishlist->setShared(false)->save();
    expect(fn() => $service->getByCode($this->code, 1))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
});
