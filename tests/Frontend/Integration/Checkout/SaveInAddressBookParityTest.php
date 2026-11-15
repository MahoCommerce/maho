<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Mage\Checkout\Api\CartProcessor;
use Mage\Sales\Api\OrderProcessor;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The Save in Address Book checkbox of the admin order create and the saveInAddressBook field of the API go to the
 * same rule of Mage_Sales_Service_Order::submit() (#1348).
 */

/**
 * @return array<string, mixed> an address of the API with the street $street
 */
function saveInAddressBookApiAddress(string $street): array
{
    return [
        'firstName' => 'Address', 'lastName' => 'Book', 'street' => [$street], 'city' => 'Los Angeles',
        'regionId' => 12, 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ];
}

/**
 * @return array<string, mixed> an address of the admin order form with the street $street
 */
function saveInAddressBookAdminAddress(string $street): array
{
    return [
        'firstname' => 'Address', 'lastname' => 'Book', 'street' => [$street], 'city' => 'Los Angeles',
        'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100',
    ];
}

function saveInAddressBookCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())->setStoreId((int) Mage::app()->getStore()->getId())
        ->setGroupId(1)->setFirstname('Address')->setLastname('Book')
        ->setEmail('pest.save.address.book.' . uniqid() . '@example.com');
    $customer->save();

    return Mage::getModel('customer/customer')->load($customer->getId());
}

/**
 * Return a cart of $customer with one product, or a guest cart when $customer is null.
 */
function saveInAddressBookCart(?Mage_Customer_Model_Customer $customer): Mage_Sales_Model_Quote
{
    $quote = CartParity::cart();
    if ($customer) {
        $quote->setCustomer($customer);
        Mage::getService('checkout/cart')->collectAndSave($quote);
    }

    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

function saveInAddressBookSecurity(int $customerId): Security
{
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $id === 'security.authorization_checker'
                ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                : $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
        }
    });
}

/**
 * Place the order of $quote in the admin order create, with the Save in Address Book checkbox of both addresses
 * when $save is true.
 */
function saveInAddressBookViaAdmin(Mage_Sales_Model_Quote $quote, Mage_Customer_Model_Customer $customer, bool $save): void
{
    $quote->setIsSuperMode(true);
    Mage::unregister('_singleton/adminhtml/session_quote');
    Mage::getSingleton('adminhtml/session_quote')->setStoreId((int) $quote->getStoreId())->setCustomerId((int) $customer->getId());
    $create = Mage::getModel('adminhtml/sales_order_create')->setQuote($quote)->setData('send_confirmation', 0);
    // The form sends the checkbox only when it is checked
    $checkbox = $save ? ['save_in_address_book' => 1] : [];
    $create->setShippingAsBilling(false);
    $create->setBillingAddress(saveInAddressBookAdminAddress('1 Billing St') + $checkbox);
    $create->setShippingAddress(saveInAddressBookAdminAddress('2 Shipping St') + $checkbox);
    $quote->getShippingAddress()->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
    $quote->getPayment()->importData(['method' => 'checkmo']);
    $quote->collectTotals()->save();
    $create->createOrder();
}

/**
 * Post $body to /api/rest/v2/carts/{id}/place-order as the customer of $quote.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function saveInAddressBookPlaceViaApi(Mage_Sales_Model_Quote $quote, array $body): array
{
    $id = (int) $quote->getId();
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/carts/{$id}/place-order", $body + [
        'shippingMethod' => 'flatrate_flatrate', 'paymentMethod' => 'checkmo',
    ]);
    $processor = new OrderProcessor(saveInAddressBookSecurity((int) $quote->getCustomerId()));

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'place_customer_order'), ['id' => $id], ['request' => $request]));
}

/**
 * Send $body to PUT /api/rest/v2/carts/{id}/$path, or to /guest-carts/{id}/$path for a guest cart.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function saveInAddressBookAddressViaApi(Mage_Sales_Model_Quote $quote, string $path, array $body): array
{
    $customerId = (int) $quote->getCustomerId();
    $id = $customerId ? (string) $quote->getId() : (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('PUT', ($customerId ? '/api/rest/v2/carts/' : '/api/rest/v2/guest-carts/') . "{$id}/{$path}", $body);
    $processor = new CartProcessor($customerId ? saveInAddressBookSecurity($customerId) : CartParity::guestSecurity());
    $operation = ($customerId ? 'set_my_' : 'set_guest_') . str_replace('-', '_', $path);

    return AdminApiParity::api($request, fn() => $processor->process(null, new Put(name: $operation), ['id' => $id], ['request' => $request]));
}

/**
 * Run the GraphQL mutation $name for $quote with $input, as its customer.
 *
 * @param array<string, mixed> $input
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function saveInAddressBookViaGraphQl(Mage_Sales_Model_Quote $quote, string $name, array $input): array
{
    $request = AdminApiParity::jsonRequest('POST', '/api/graphql');
    $processor = new CartProcessor(saveInAddressBookSecurity((int) $quote->getCustomerId()));

    return AdminApiParity::api($request, fn() => $processor->process(null, new Mutation(name: $name), [], [
        'args' => ['input' => ['cartId' => (string) $quote->getId()] + $input],
    ]));
}

/**
 * @return array{streets: list<string>, billing: ?string, shipping: ?string} the first street line of each address
 *                                                                            of the address book of $customer, and
 *                                                                            of its default addresses
 */
function saveInAddressBookOf(Mage_Customer_Model_Customer $customer): array
{
    $customer = Mage::getModel('customer/customer')->load($customer->getId());
    $streets = [];
    foreach ($customer->getAddressesCollection() as $address) {
        $streets[] = (string) $address->getStreet(1);
    }
    sort($streets);

    return [
        'streets' => $streets,
        'billing' => $customer->getDefaultBillingAddress() ? (string) $customer->getDefaultBillingAddress()->getStreet(1) : null,
        'shipping' => $customer->getDefaultShippingAddress() ? (string) $customer->getDefaultShippingAddress()->getStreet(1) : null,
    ];
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $_SESSION = [];
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig('sales_email/order/enabled', '0');
    $this->customers = [];
    $this->carts = [];
});

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->clear();
    Mage::register('isSecureArea', true, true);
    foreach ($this->carts as $cart) {
        $order = Mage::getModel('sales/order')->load($cart->getId(), 'quote_id');
        if ($order->getId()) {
            $order->delete();
        }
        $cart->delete();
    }
    foreach ($this->customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('adds both addresses of the order to the address book when the admin checkbox or saveInAddressBook asks for it', function () {
    $this->customers = [$adminCustomer = saveInAddressBookCustomer(), $apiCustomer = saveInAddressBookCustomer()];
    $this->carts = [$adminCart = saveInAddressBookCart($adminCustomer), $apiCart = saveInAddressBookCart($apiCustomer)];

    saveInAddressBookViaAdmin($adminCart, $adminCustomer, true);
    $api = saveInAddressBookPlaceViaApi($apiCart, [
        'billingAddress' => saveInAddressBookApiAddress('1 Billing St') + ['saveInAddressBook' => true],
        'shippingAddress' => saveInAddressBookApiAddress('2 Shipping St') + ['saveInAddressBook' => true],
    ]);

    $expected = ['streets' => ['1 Billing St', '2 Shipping St'], 'billing' => '1 Billing St', 'shipping' => '2 Shipping St'];
    expect($api['errors'])->toBe([])
        ->and(saveInAddressBookOf($adminCustomer))->toBe($expected)
        ->and(saveInAddressBookOf($apiCustomer))->toBe($expected);
});

it('adds no address without the admin checkbox, or with saveInAddressBook false or left out', function () {
    $this->customers = [$adminCustomer = saveInAddressBookCustomer(), $falseCustomer = saveInAddressBookCustomer(), $omittedCustomer = saveInAddressBookCustomer()];
    $this->carts = [
        $adminCart = saveInAddressBookCart($adminCustomer),
        $falseCart = saveInAddressBookCart($falseCustomer),
        $omittedCart = saveInAddressBookCart($omittedCustomer),
    ];

    saveInAddressBookViaAdmin($adminCart, $adminCustomer, false);
    $false = saveInAddressBookPlaceViaApi($falseCart, [
        'billingAddress' => saveInAddressBookApiAddress('1 Billing St') + ['saveInAddressBook' => false],
        'shippingAddress' => saveInAddressBookApiAddress('2 Shipping St') + ['saveInAddressBook' => false],
    ]);
    $omitted = saveInAddressBookPlaceViaApi($omittedCart, [
        'billingAddress' => saveInAddressBookApiAddress('1 Billing St'),
        'shippingAddress' => saveInAddressBookApiAddress('2 Shipping St'),
    ]);

    $expected = ['streets' => [], 'billing' => null, 'shipping' => null];
    expect($false['errors'])->toBe([])
        ->and($omitted['errors'])->toBe([])
        ->and(saveInAddressBookOf($adminCustomer))->toBe($expected)
        ->and(saveInAddressBookOf($falseCustomer))->toBe($expected)
        ->and(saveInAddressBookOf($omittedCustomer))->toBe($expected);
});

it('takes saveInAddressBook on the cart address endpoints of REST and GraphQL', function () {
    $this->customers = [$customer = saveInAddressBookCustomer()];
    $this->carts = [$cart = saveInAddressBookCart($customer)];

    $shipping = saveInAddressBookAddressViaApi($cart, 'shipping-address', saveInAddressBookApiAddress('2 Shipping St') + ['saveInAddressBook' => true]);
    $billing = saveInAddressBookViaGraphQl($cart, 'setBillingAddressOn', saveInAddressBookApiAddress('1 Billing St') + ['saveInAddressBook' => true]);
    $order = saveInAddressBookPlaceViaApi(Mage::getModel('sales/quote')->loadByIdWithoutStore($cart->getId()), []);

    expect($shipping['errors'])->toBe([])
        ->and($billing['errors'])->toBe([])
        ->and($order['errors'])->toBe([])
        ->and(saveInAddressBookOf($customer))->toBe(['streets' => ['1 Billing St', '2 Shipping St'], 'billing' => '1 Billing St', 'shipping' => '2 Shipping St']);
});

it('adds the address once when the billing address copies the shipping address', function () {
    $this->customers = [$customer = saveInAddressBookCustomer()];
    $this->carts = [$cart = saveInAddressBookCart($customer)];

    saveInAddressBookAddressViaApi($cart, 'shipping-address', saveInAddressBookApiAddress('2 Shipping St'));
    $billing = saveInAddressBookAddressViaApi($cart, 'billing-address', ['sameAsShipping' => true, 'saveInAddressBook' => true]);
    $order = saveInAddressBookPlaceViaApi(Mage::getModel('sales/quote')->loadByIdWithoutStore($cart->getId()), []);

    expect($billing['errors'])->toBe([])
        ->and($order['errors'])->toBe([])
        ->and(saveInAddressBookOf($customer))->toBe(['streets' => ['2 Shipping St'], 'billing' => '2 Shipping St', 'shipping' => '2 Shipping St']);
});

it('ignores saveInAddressBook on a guest cart', function () {
    $this->carts = [$cart = saveInAddressBookCart(null)];

    $api = saveInAddressBookAddressViaApi($cart, 'shipping-address', saveInAddressBookApiAddress('2 Shipping St') + ['saveInAddressBook' => true]);

    expect($api['errors'])->toBe([])
        ->and(Mage::getModel('sales/quote')->loadByIdWithoutStore($cart->getId())->getShippingAddress()->getSaveInAddressBook())->toBeFalse();
});
