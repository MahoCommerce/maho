<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Customer\Api\CustomerProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront ("Add guest orders to my account") and POST /customers/create-from-order give a guest order to a
 * customer through Mage_Sales_Service_Order::assignToCustomer() (#1348).
 */

const GUEST_ORDER_ASSIGN_PASSWORD = 'Password123!';

function guestOrderAssignEmail(): string
{
    return 'guest-order-assign-' . bin2hex(random_bytes(4)) . '@example.com';
}

/**
 * Save a guest order of the current store with the email $email.
 */
function guestOrderAssignOrder(string $email): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setIncrementId('GOA' . bin2hex(random_bytes(5)))
        ->setState(Mage_Sales_Model_Order::STATE_NEW)
        ->setCustomerEmail($email)
        ->setCustomerFirstname('Guest')
        ->setCustomerLastname('Buyer')
        ->setCustomerIsGuest(true)
        ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    $order->setBillingAddress(Mage::getModel('sales/order_address')->setData([
        'address_type' => 'billing', 'firstname' => 'Guest', 'lastname' => 'Buyer', 'street' => '1 Main St',
        'city' => 'Los Angeles', 'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100',
    ]));
    $order->save();

    return $order;
}

function guestOrderAssignCustomer(string $email, int $groupId): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setEmail($email)
        ->setFirstname('Guest')
        ->setLastname('Buyer')
        ->setGroupId($groupId)
        ->setPassword(GUEST_ORDER_ASSIGN_PASSWORD);
    $customer->save();

    return $customer;
}

/**
 * Post the "Add guest orders to my account" form as the customer of the session, and return the messages of the
 * session.
 *
 * @return list<string>
 */
function guestOrderAssignViaWeb(): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/sales/order/associateGuestOrders', 'POST'));
    $request->setRouteName('sales')->setControllerName('order')->setActionName('associateGuestOrders')
        ->setControllerModule('Mage_Sales')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Sales_OrderController($request, new Mage_Core_Controller_Response_Http())->associateGuestOrdersAction();

    return array_map(
        fn(Mage_Core_Model_Message_Abstract $message): string => $message->getText(),
        Mage::getSingleton('customer/session')->getMessages(true)->getItems(),
    );
}

/**
 * Call POST /customers/create-from-order with the account token of $order.
 *
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function guestOrderAssignViaApi(Mage_Sales_Model_Order $order): array
{
    $token = Mage::helper('sales/accountToken')->generate((int) $order->getId(), (string) $order->getCustomerEmail());

    return TransportParity::api(fn() => new CustomerProcessor()->process(null, new Post(name: 'create_from_order'), [], [
        'args' => ['input' => ['accountToken' => $token, 'password' => GUEST_ORDER_ASSIGN_PASSWORD]],
    ]));
}

function guestOrderAssignReload(Mage_Sales_Model_Order $order): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')->load($order->getId());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    Mage::getSingleton('customer/session')->getMessages(true);

    $store = Mage::app()->getStore();
    $store->setConfig('customer/create_account/confirm', '0');
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $this->orders = [];
});

afterEach(function () {
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        $order->delete();
    }
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'guest-order-assign-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('gives the guest order the customer and the customer group on the storefront and the API', function () {
    $wholesale = 2;
    $webCustomer = guestOrderAssignCustomer($webEmail = guestOrderAssignEmail(), $wholesale);
    $this->orders = [$webOrder = guestOrderAssignOrder($webEmail), $apiOrder = guestOrderAssignOrder(guestOrderAssignEmail())];

    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($webCustomer);
    $web = guestOrderAssignViaWeb();
    $api = guestOrderAssignViaApi($apiOrder);

    $apiCustomer = Mage::getService('customer/customer')->getByEmail((string) $apiOrder->getCustomerEmail());
    $webOrder = guestOrderAssignReload($webOrder);
    $apiOrder = guestOrderAssignReload($apiOrder);
    expect($web)->toBe(['Successfully associated 1 guest order(s) with your account.'])
        ->and($api['status'])->toBe(200)
        ->and($webOrder->getCustomerId())->toBe((int) $webCustomer->getId())
        ->and($apiOrder->getCustomerId())->toBe((int) $apiCustomer?->getId())
        ->and($webOrder->getCustomerIsGuest())->toBeFalse()
        ->and($apiOrder->getCustomerIsGuest())->toBeFalse()
        ->and($webOrder->getCustomerGroupId())->toBe($wholesale)
        ->and($apiOrder->getCustomerGroupId())->toBe((int) $apiCustomer?->getGroupId())
        ->and($apiOrder->getCustomerGroupId())->not->toBe(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
});

it('refuses an order that has a customer, and an order with another email', function () {
    $customer = guestOrderAssignCustomer($email = guestOrderAssignEmail(), 1);
    $this->orders = [$owned = guestOrderAssignOrder($email), $other = guestOrderAssignOrder(guestOrderAssignEmail())];
    $owner = guestOrderAssignCustomer(guestOrderAssignEmail(), 1);
    $owned->setCustomerId((int) $owner->getId())->save();
    /** @var Mage_Sales_Service_Order $service */
    $service = Mage::getService('sales/order');

    $conflict = TransportParity::api(fn() => $service->assignToCustomer($owned, $customer));
    $otherEmail = TransportParity::api(fn() => $service->assignToCustomer($other, $customer));

    expect($conflict['status'])->toBe(409)
        ->and($conflict['message'])->toBe('This order already belongs to a customer.')
        ->and($otherEmail['status'])->toBe(422)
        ->and($otherEmail['message'])->toBe('This order cannot be added to this account.')
        ->and(guestOrderAssignReload($other)->getCustomerId())->toBeNull();
});

describe('a store that requires a confirmed email', function () {
    it('refuses an unconfirmed customer on the storefront, because the email is the only proof', function () {
        $customer = guestOrderAssignCustomer($email = guestOrderAssignEmail(), 1);
        $this->orders = [$order = guestOrderAssignOrder($email)];
        // A customer who logged in before the store required a confirmed email
        Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
        $customer->setConfirmation('pending-key');
        Mage::app()->getStore()->setConfig('customer/create_account/confirm', '1');

        expect(guestOrderAssignViaWeb())->toBe(['Please confirm your email address before associating guest orders.'])
            ->and(guestOrderAssignReload($order)->getCustomerId())->toBeNull();
    });

    it('gives the order of the account token to the new account on the API, because the token proves the order', function () {
        Mage::app()->getStore()->setConfig('customer/create_account/confirm', '1');
        $this->orders = [$order = guestOrderAssignOrder(guestOrderAssignEmail())];

        $api = guestOrderAssignViaApi($order);

        $customer = Mage::getService('customer/customer')->getByEmail((string) $order->getCustomerEmail());
        expect($api['status'])->toBe(200)
            ->and($customer?->getConfirmation())->not->toBeNull()
            ->and(guestOrderAssignReload($order)->getCustomerId())->toBe((int) $customer?->getId());
    });
});
