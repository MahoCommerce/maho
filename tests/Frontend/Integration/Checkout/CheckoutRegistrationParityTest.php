<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The one page checkout registers its new customer with Mage_Customer_Service_Customer::register(), and the admin
 * order create creates its new customer with create() (#1348). The order saves the customer, so that a failed order
 * leaves no account.
 */

const CHECKOUT_REGISTRATION_PASSWORD = 'Password123!';

function checkoutRegistrationEmail(): string
{
    return 'checkout-registration-' . bin2hex(random_bytes(4)) . '@example.com';
}

/**
 * Return a cart of the current store with the addresses, and with the flat rate unless $shippingMethod is false.
 */
function checkoutRegistrationCart(string $email, bool $shippingMethod = true): Mage_Sales_Model_Quote
{
    $quote = CartParity::cart();
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = $service->mapAddressInput([
        'firstName' => 'New', 'lastName' => 'Shopper', 'street' => ['1 Main St'], 'city' => 'Los Angeles',
        'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ]);
    $service->setShippingAddress($quote, $address);
    $service->setBillingAddress($quote, $address + ['email' => $email]);
    if ($shippingMethod) {
        $service->selectShippingMethod($quote, 'flatrate_flatrate');
    }
    $service->selectPaymentMethod($quote, ['method' => 'checkmo']);
    $quote->save();

    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

/**
 * Place the order of $quote in the one page checkout, as a shopper who registers with $email.
 *
 * @return array<string, mixed> the JSON answer
 */
function checkoutRegistrationViaWeb(Mage_Sales_Model_Quote $quote, string $email): array
{
    $quote->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER)
        ->setCustomerEmail($email)
        ->setCustomerFirstname('New')
        ->setCustomerLastname('Shopper')
        ->setPasswordHash(Mage::getModel('customer/customer')->encryptPassword(CHECKOUT_REGISTRATION_PASSWORD))
        ->save();
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::unregister('_singleton/checkout/type_onepage');
    Mage::getSingleton('checkout/session')->setQuoteId($quote->getId());
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/onepage/saveOrder', 'POST', [
        'payment' => ['method' => 'checkmo'],
    ]));
    $request->setRouteName('checkout')->setControllerName('onepage')->setActionName('saveOrder')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Checkout_OnepageController($request, $response)->saveOrderAction();

    return Mage::helper('core')->jsonDecode((string) $response->getBody());
}

/**
 * Place the order of $quote in the admin order create, for a new customer with $email.
 */
function checkoutRegistrationViaAdmin(Mage_Sales_Model_Quote $quote, string $email): Mage_Sales_Model_Order
{
    $quote->setIsSuperMode(true);
    Mage::unregister('_singleton/adminhtml/session_quote');
    Mage::getSingleton('adminhtml/session_quote')->setStoreId((int) $quote->getStoreId())->setCustomerId(0)->setCustomerIsGuest(false);

    return Mage::getModel('adminhtml/sales_order_create')->setQuote($quote)
        ->setData('account', ['email' => $email, 'group_id' => 1])
        ->setData('send_confirmation', 0)
        ->createOrder();
}

function checkoutRegistrationCustomer(string $email): ?Mage_Customer_Model_Customer
{
    return Mage::getService('customer/customer')->getByEmail($email);
}

function checkoutRegistrationOrder(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::unregister('_singleton/checkout/type_onepage');
    Mage::unregister('_singleton/customer/session');
    Mage::unregister('_helper/checkout');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig('customer/create_account/confirm', '0');
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $this->spy = new class extends Mage_Customer_Service_Customer {
        /** @var list<array{string, string}> */
        public array $emails = [];

        #[\Override]
        protected function sendNewAccountEmail(Mage_Customer_Model_Customer $customer, string $type, int $storeId, string $backUrl = ''): void
        {
            $this->emails[] = [$type, (string) $customer->getEmail()];
        }
    };
    Mage::unregister('_service/customer/customer');
    Mage::register('_service/customer/customer', $this->spy);
    $this->emails = [checkoutRegistrationEmail(), checkoutRegistrationEmail()];
    $this->carts = [];
});

afterEach(function () {
    Mage::unregister('_service/customer/customer');
    Mage::unregister('_helper/checkout');
    Mage::getSingleton('adminhtml/session_quote')->clear();
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::register('isSecureArea', true, true);
    foreach ($this->carts as $cart) {
        $order = checkoutRegistrationOrder($cart);
        if ($order->getId()) {
            $order->delete();
        }
        $cart->delete();
    }
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'checkout-registration-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('saves the new customer with the order and sends the welcome email, in the checkout and the admin order create', function () {
    [$webEmail, $adminEmail] = $this->emails;
    $this->carts = [$webCart = checkoutRegistrationCart($webEmail), $adminCart = checkoutRegistrationCart($adminEmail)];

    $web = checkoutRegistrationViaWeb($webCart, $webEmail);
    $adminOrder = checkoutRegistrationViaAdmin($adminCart, $adminEmail);

    $webCustomer = checkoutRegistrationCustomer($webEmail);
    $adminCustomer = checkoutRegistrationCustomer($adminEmail);
    expect($web['success'] ?? null)->toBeTrue()
        ->and(checkoutRegistrationOrder($webCart)->getCustomerId())->toBe((int) $webCustomer?->getId())
        ->and($adminOrder->getCustomerId())->toBe((int) $adminCustomer?->getId())
        ->and($webCustomer?->getId())->not->toBeNull()
        ->and($adminCustomer?->getId())->not->toBeNull()
        ->and(Mage::getSingleton('customer/session')->getCustomerId())->toBe((int) $webCustomer?->getId())
        ->and($this->spy->emails)->toBe([['registered', $webEmail], ['registered', $adminEmail]]);
});

it('refuses a new customer that the customer service refuses, before the order, in the checkout and the admin order create', function () {
    $this->carts = [$webCart = checkoutRegistrationCart('web@example.com'), $adminCart = checkoutRegistrationCart('admin@example.com')];

    $web = checkoutRegistrationViaWeb($webCart, 'not-an-email');
    $adminError = '';
    try {
        checkoutRegistrationViaAdmin($adminCart, 'not-an-email');
    } catch (Mage_Core_Exception_Input $e) {
        $adminError = $e->getMessage();
    }

    expect($web['error_messages'] ?? null)->toBe('Invalid email address "not-an-email".')
        ->and($adminError)->toBe('Invalid email address "not-an-email".')
        ->and(checkoutRegistrationOrder($webCart)->getId())->toBeNull()
        ->and(checkoutRegistrationOrder($adminCart)->getId())->toBeNull()
        ->and($this->spy->emails)->toBe([]);
});

it('leaves no account and sends no email when the order fails, in the checkout and the admin order create', function () {
    [$webEmail, $adminEmail] = $this->emails;
    $this->carts = [
        $webCart = checkoutRegistrationCart($webEmail, shippingMethod: false),
        $adminCart = checkoutRegistrationCart($adminEmail, shippingMethod: false),
    ];

    $web = checkoutRegistrationViaWeb($webCart, $webEmail);
    $adminError = '';
    try {
        checkoutRegistrationViaAdmin($adminCart, $adminEmail);
    } catch (Mage_Core_Exception $e) {
        $adminError = $e->getMessage();
    }

    expect($web['error_messages'] ?? null)->toBe('Please specify a shipping method.')
        ->and($adminError)->toBe('Please specify a shipping method.')
        ->and(checkoutRegistrationCustomer($webEmail))->toBeNull()
        ->and(checkoutRegistrationCustomer($adminEmail))->toBeNull()
        ->and($this->spy->emails)->toBe([]);
});

describe('a store that requires a confirmed email', function () {
    it('sends the confirmation email to the shopper, and creates a confirmed account for the admin', function () {
        Mage::app()->getStore()->setConfig('customer/create_account/confirm', '1');
        [$webEmail, $adminEmail] = $this->emails;
        $this->carts = [$webCart = checkoutRegistrationCart($webEmail), $adminCart = checkoutRegistrationCart($adminEmail)];

        $web = checkoutRegistrationViaWeb($webCart, $webEmail);
        checkoutRegistrationViaAdmin($adminCart, $adminEmail);

        expect($web['success'] ?? null)->toBeTrue()
            ->and(checkoutRegistrationCustomer($webEmail)?->getConfirmation())->not->toBeNull()
            ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse()
            ->and(checkoutRegistrationCustomer($adminEmail)?->getConfirmation())->toBeNull()
            ->and($this->spy->emails)->toBe([['confirmation', $webEmail], ['registered', $adminEmail]]);
    });
});
