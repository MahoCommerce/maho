<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Sales\Api\GraphQL\OrderMutationHandler;
use Mage\Sales\Api\OrderProcessor;
use Mage\Sales\Api\OrderProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The one page checkout and POST /guest-carts/{id}/place-order place the order through
 * Mage_Sales_Service_Order::submit() (#1348). Each test gives the same kind of cart to both transports and compares
 * the outcome.
 */

/**
 * Return a guest cart of the current store with the addresses, the flat rate and the check / money order payment.
 */
function placeOrderCart(): Mage_Sales_Model_Quote
{
    $quote = CartParity::cart();
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = \Mage\Checkout\Api\CartRequest::mapAddress([
        'firstName' => 'Place', 'lastName' => 'Order', 'street' => ['1 Main St'], 'city' => 'Los Angeles',
        'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ]);
    $service->setShippingAddress($quote, $address);
    $service->setBillingAddress($quote, $address + ['email' => 'place.order@example.com']);
    $service->selectShippingMethod($quote, 'flatrate_flatrate');
    $service->selectPaymentMethod($quote, ['method' => 'checkmo']);
    $quote->setCustomerEmail('place.order@example.com')
        ->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST)
        ->save();

    return placeOrderReload($quote);
}

function placeOrderReload(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

/**
 * Post $post to the saveOrder action of the one page checkout, with $quote as the cart of the session.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed> the JSON answer
 */
function placeOrderViaWeb(Mage_Sales_Model_Quote $quote, array $post = []): array
{
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::unregister('_singleton/checkout/type_onepage');
    Mage::getSingleton('checkout/session')->setQuoteId($quote->getId());
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/onepage/saveOrder', 'POST', $post + [
        'payment' => ['method' => 'checkmo'],
    ]));
    $request->setRouteName('checkout')->setControllerName('onepage')->setActionName('saveOrder')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Checkout_OnepageController($request, $response)->saveOrderAction();

    return Mage::helper('core')->jsonDecode((string) $response->getBody());
}

/**
 * Post $body to /api/rest/v2/guest-carts/{id}/place-order, as a guest or with an admin token.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function placeOrderViaApi(Mage_Sales_Model_Quote $quote, array $body = [], bool $asStaff = false): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/place-order", $body);
    $processor = new OrderProcessor($asStaff ? AdminApiParity::adminSecurity() : CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'place_guest_order'), ['id' => $maskedId], ['request' => $request]));
}

function placeOrderOf(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')->load($quote->getId(), 'quote_id');
}

/**
 * Register a subclass of the order service that records the emails instead of sending them.
 */
function placeOrderSpy(): Mage_Sales_Service_Order
{
    $spy = new class extends Mage_Sales_Service_Order {
        /** @var list<string> */
        public array $newOrderEmails = [];

        /** @var list<string> */
        public array $paymentFailedEmails = [];

        #[\Override]
        protected function sendNewOrderEmail(Mage_Sales_Model_Order $order): void
        {
            $this->newOrderEmails[] = (string) $order->getIncrementId();
        }

        #[\Override]
        protected function sendPaymentFailedEmail(Mage_Sales_Model_Quote $quote, string $message): void
        {
            $this->paymentFailedEmails[] = $message;
        }
    };
    Mage::unregister('_service/sales/order');
    Mage::register('_service/sales/order', $spy);

    return $spy;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::unregister('_singleton/checkout/type_onepage');
    Mage::unregister('_helper/checkout');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->spy = placeOrderSpy();
    $this->carts = [placeOrderCart(), placeOrderCart()];
});

afterEach(function () {
    Mage::unregister('_service/sales/order');
    Mage::unregister('_helper/checkout');
    foreach ($this->carts as $cart) {
        $order = placeOrderOf($cart);
        if ($order->getId()) {
            Mage::register('isSecureArea', true, true);
            $order->delete();
            Mage::unregister('isSecureArea');
        }
        $cart->delete();
    }
});

it('places the order with one new order email, one checkout_submit_all_after and a guest token on both transports', function () {
    [$webCart, $apiCart] = $this->carts;
    $spy = $this->spy;
    $listener = new class ($spy) {
        /** @var list<string> the events, each with the count of new order emails at its time */
        public array $events = [];

        public function __construct(private Mage_Sales_Service_Order $spy) {}

        public function saveOrderAfter(Maho\Event\Observer $observer): void
        {
            $this->record('save_order_after', $observer);
        }

        public function submitAllAfter(Maho\Event\Observer $observer): void
        {
            $this->record('submit_all_after', $observer);
        }

        private function record(string $event, Maho\Event\Observer $observer): void
        {
            $this->events[] = $event . ':' . $observer->getEvent()->getOrder()->getIncrementId() . ':' . count($this->spy->newOrderEmails);
        }
    };
    Mage::register('_singleton/place_order_parity_listener', $listener, true);
    (function (): void {
        $this->_events['global']['checkout_type_onepage_save_order_after']['observers']['place_order_parity_listener']
            = ['type' => 'singleton', 'model' => 'place_order_parity_listener', 'method' => 'saveOrderAfter'];
        $this->_events['global']['checkout_submit_all_after']['observers']['place_order_parity_listener']
            = ['type' => 'singleton', 'model' => 'place_order_parity_listener', 'method' => 'submitAllAfter'];
    })->call(Mage::app());

    try {
        $web = placeOrderViaWeb($webCart);
        $api = placeOrderViaApi($apiCart);
    } finally {
        (function (): void {
            unset(
                $this->_events['global']['checkout_type_onepage_save_order_after']['observers']['place_order_parity_listener'],
                $this->_events['global']['checkout_submit_all_after']['observers']['place_order_parity_listener'],
            );
        })->call(Mage::app());
        Mage::unregister('_singleton/place_order_parity_listener');
    }

    $orders = [placeOrderOf($webCart), placeOrderOf($apiCart)];
    $incrementIds = array_map(fn(Mage_Sales_Model_Order $order): string => (string) $order->getIncrementId(), $orders);
    expect($web['success'] ?? null)->toBeTrue()
        ->and($api['status'])->toBe(200)
        ->and($this->spy->newOrderEmails)->toBe($incrementIds)
        // The one page checkout: its event, then the email, then checkout_submit_all_after. The API has no event of its own.
        ->and($listener->events)->toBe([
            "save_order_after:{$incrementIds[0]}:0",
            "submit_all_after:{$incrementIds[0]}:1",
            "submit_all_after:{$incrementIds[1]}:2",
        ])
        ->and($orders[0]->getData('guest_access_token'))->toBeString()->not->toBeEmpty()
        ->and($orders[1]->getData('guest_access_token'))->toBeString()->not->toBeEmpty()
        ->and(placeOrderReload($webCart)->getIsActive())->toBeFalse()
        ->and(placeOrderReload($apiCart)->getIsActive())->toBeFalse();
});

it('sends no new order email when an observer of checkout_type_onepage_save_order_after stops it', function () {
    $listener = new class {
        public function stopEmail(Maho\Event\Observer $observer): void
        {
            $observer->getEvent()->getOrder()->setCanSendNewEmailFlag(false);
        }
    };
    Mage::register('_singleton/place_order_parity_listener', $listener, true);
    (function (): void {
        $this->_events['global']['checkout_type_onepage_save_order_after']['observers']['place_order_parity_listener']
            = ['type' => 'singleton', 'model' => 'place_order_parity_listener', 'method' => 'stopEmail'];
    })->call(Mage::app());

    try {
        $web = placeOrderViaWeb($this->carts[0]);
    } finally {
        (function (): void {
            unset($this->_events['global']['checkout_type_onepage_save_order_after']['observers']['place_order_parity_listener']);
        })->call(Mage::app());
        Mage::unregister('_singleton/place_order_parity_listener');
    }

    expect($web['success'] ?? null)->toBeTrue()
        ->and(placeOrderOf($this->carts[0])->getId())->not->toBeNull()
        ->and($this->spy->newOrderEmails)->toBe([]);
});

it('places a Staff order of the API without the email when the body asks for none', function () {
    $api = placeOrderViaApi($this->carts[1], ['sendConfirmation' => false], asStaff: true);

    expect($api['status'])->toBe(200)
        ->and(placeOrderOf($this->carts[1])->getId())->not->toBeNull()
        ->and($this->spy->newOrderEmails)->toBe([]);
});

it('refuses a line with an error with the same message on both transports', function () {
    [$webCart, $apiCart] = $this->carts;
    $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $webCart->getItemsCollection()->getFirstItem()->getProductId());
    $stock = [$stockItem->getQty(), $stockItem->getIsInStock()];
    $stockItem->setQty(0)->setIsInStock(false)->save();

    try {
        $web = null;
        try {
            Mage::getModel('checkout/type_onepage')->setQuote(placeOrderReload($webCart))->saveOrder([]);
        } catch (Mage_Core_Exception_Input $e) {
            $web = array_column($e->getErrors(), 'message');
        }
        $api = placeOrderViaApi($apiCart);
    } finally {
        $stockItem->setQty($stock[0])->setIsInStock($stock[1])->save();
    }

    expect($web)->not->toBeEmpty()
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe($web)
        ->and(array_unique($api['fields']))->toBe(['items']);
});

it('refuses a payment method that is no longer available, for a caller that selected it before', function () {
    $quote = placeOrderReload($this->carts[0]);
    Mage::app()->getStore()->setConfig('payment/checkmo/active', '0');

    $fields = null;
    try {
        Mage::getService('sales/order')->submit($quote, Mage_Checkout_Model_Actor::Customer);
    } catch (Mage_Core_Exception_Input $e) {
        $fields = array_column($e->getErrors(), 'field');
    } finally {
        Mage::app()->getStore()->setConfig('payment/checkmo/active', '1');
    }

    expect($fields)->toBe(['payment_method'])
        ->and(placeOrderOf($this->carts[0])->getId())->toBeNull();
});

describe('the minimum order amount', function () {
    beforeEach(function () {
        Mage::app()->getStore()->setConfig('sales/minimum_order/active', '1');
        Mage::app()->getStore()->setConfig('sales/minimum_order/amount', '1000000');
        Mage::app()->getStore()->setConfig('sales/minimum_order/error_message', '');
    });

    afterEach(function () {
        Mage::app()->getStore()->setConfig('sales/minimum_order/active', '0');
    });

    it('refuses an order of a Customer with the same message on both transports', function () {
        [$webCart, $apiCart] = $this->carts;

        $web = placeOrderViaWeb($webCart);
        $api = placeOrderViaApi($apiCart);

        expect($web['error'] ?? null)->toBeTrue()
            ->and($web['error_messages'])->toBe('Subtotal must exceed minimum order amount')
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Subtotal must exceed minimum order amount'])
            ->and($api['fields'])->toBe(['subtotal']);
    });

    it('does not apply to Staff, as in the admin order create', function () {
        $api = placeOrderViaApi($this->carts[1], asStaff: true);

        expect($api['status'])->toBe(200)
            ->and(placeOrderOf($this->carts[1])->getId())->not->toBeNull();
    });
});

describe('the terms and conditions', function () {
    beforeEach(function () {
        $this->agreement = Mage::getModel('checkout/agreement')->setData([
            'name' => 'Pest terms', 'content' => 'Terms', 'checkbox_text' => 'I agree', 'is_active' => 1, 'stores' => [0],
        ])->save();
        Mage::app()->getStore()->setConfig('checkout/options/enable_agreements', '1');
    });

    afterEach(function () {
        Mage::app()->getStore()->setConfig('checkout/options/enable_agreements', '0');
        $this->agreement->delete();
    });

    it('refuses an order without them with the same message on both transports', function () {
        [$webCart, $apiCart] = $this->carts;

        $web = placeOrderViaWeb($webCart);
        $api = placeOrderViaApi($apiCart);

        expect($web['error'] ?? null)->toBeTrue()
            ->and($web['error_messages'])->toBe('Please agree to all the terms and conditions before placing the order.')
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Please agree to all the terms and conditions before placing the order.'])
            ->and($api['fields'])->toBe(['agreementIds']);
    });

    it('places the order when the customer accepts them on both transports', function () {
        [$webCart, $apiCart] = $this->carts;
        $agreementId = (int) $this->agreement->getId();

        $web = placeOrderViaWeb($webCart, ['agreement' => [$agreementId => '1']]);
        $api = placeOrderViaApi($apiCart, ['agreementIds' => [$agreementId]]);

        expect($web['success'] ?? null)->toBeTrue()
            ->and($api['status'])->toBe(200);
    });
});

it('refuses a placement while another connection places the same cart, on both transports', function () {
    $connection = Mage::getSingleton('core/resource')->createUnsharedConnection();
    $locks = array_map(fn(Mage_Sales_Model_Quote $cart): string => 'maho_quote_place_order:' . $cart->getId(), $this->carts);
    foreach ($locks as $lock) {
        expect($connection->getLock($lock))->toBeTrue();
    }

    try {
        $web = placeOrderViaWeb($this->carts[0]);
        $api = placeOrderViaApi($this->carts[1]);
    } finally {
        foreach ($locks as $lock) {
            $connection->releaseLock($lock);
        }
        $connection->closeConnection();
    }

    expect($web['error_messages'] ?? null)->toBe('Order placement is already in progress for this cart.')
        ->and($api['status'])->toBe(409)
        ->and($api['errors'])->toBe(['Order placement is already in progress for this cart.'])
        ->and(placeOrderOf($this->carts[0])->getId())->toBeNull()
        ->and(placeOrderOf($this->carts[1])->getId())->toBeNull();
});

it('refuses a gift card that another order spends during the placement, and sends the payment failed email, on both transports', function () {
    $giftcards = [];
    foreach ($this->carts as $cart) {
        $giftcard = Mage::getModel('giftcard/giftcard')
            ->setCode(Mage::helper('giftcard')->generateCode())
            ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
            ->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()])
            ->setBalance(5.00)
            ->setInitialBalance(5.00);
        $giftcard->save();
        Mage::getService('giftcard/giftcard')->applyToCart($cart, (string) $giftcard->getCode(), actor: Mage_Checkout_Model_Actor::Staff);
        $giftcards[(string) $giftcard->getCode()] = $giftcard;
    }
    // Each transport saves the cart before the placement. The listener spends the card at that moment
    $listener = new class ($giftcards) {
        /** @param array<string, Maho_Giftcard_Model_Giftcard> $giftcards */
        public function __construct(private array $giftcards) {}

        public function spend(Maho\Event\Observer $observer): void
        {
            foreach (array_keys(json_decode((string) $observer->getEvent()->getQuote()->getGiftcardCodes(), true) ?: []) as $code) {
                ($this->giftcards[$code] ?? null)?->setBalance(0)->save();
            }
        }
    };
    Mage::register('_singleton/place_order_parity_spender', $listener, true);
    (function (): void {
        $this->_events['global']['sales_quote_save_after']['observers']['place_order_parity_spender']
            = ['type' => 'singleton', 'model' => 'place_order_parity_spender', 'method' => 'spend'];
    })->call(Mage::app());

    try {
        $web = placeOrderViaWeb($this->carts[0]);
        $api = placeOrderViaApi($this->carts[1]);
    } finally {
        (function (): void {
            unset($this->_events['global']['sales_quote_save_after']['observers']['place_order_parity_spender']);
        })->call(Mage::app());
        Mage::unregister('_singleton/place_order_parity_spender');
        foreach ($giftcards as $giftcard) {
            $giftcard->delete();
        }
    }

    $messages = array_map(fn(string $code): string => "Gift card \"{$code}\" is no longer valid", array_keys($giftcards));
    expect($web['error_messages'] ?? null)->toBe($messages[0])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$messages[1]])
        ->and($this->spy->paymentFailedEmails)->toBe($messages)
        ->and(placeOrderOf($this->carts[0])->getId())->toBeNull()
        ->and(placeOrderOf($this->carts[1])->getId())->toBeNull();
});

/**
 * Place the cart $quote through the placeOrder mutation of the admin GraphQL API.
 *
 * @param array<string, mixed> $variables
 * @return array<string, mixed>
 */
function placeOrderViaAdminGraphQl(Mage_Sales_Model_Quote $quote, array $variables = []): array
{
    $handler = new OrderMutationHandler(new OrderProvider(AdminApiParity::adminSecurity()));

    return $handler->handlePlaceOrder(['cartId' => (int) $quote->getId()] + $variables);
}

describe('the REST API and the admin GraphQL API', function () {
    it('refuse a guest email that is not valid', function () {
        [$staffCart, $apiCart] = $this->carts;

        $api = placeOrderViaApi($apiCart, ['guestEmail' => 'not-an-email']);

        expect($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Invalid email address.'])
            ->and($api['fields'])->toBe(['guestEmail'])
            ->and(fn() => placeOrderViaAdminGraphQl($staffCart, ['guestEmail' => 'not-an-email']))
            ->toThrow(Mage_Core_Exception_Input::class, 'Invalid email address.')
            ->and(placeOrderOf($staffCart)->getId())->toBeNull()
            ->and(placeOrderOf($apiCart)->getId())->toBeNull();
    });

    it('dispatch sales_api_place_order_before once before each order', function () {
        [$staffCart, $apiCart] = $this->carts;
        $listener = new class {
            /** @var list<array{quote: int, payment: ?string, shipping: ?string, ordered: bool}> */
            public array $calls = [];

            public function record(Maho\Event\Observer $observer): void
            {
                $quote = $observer->getEvent()->getQuote();
                $this->calls[] = [
                    'quote' => (int) $quote->getId(),
                    'payment' => $observer->getEvent()->getPaymentMethod(),
                    'shipping' => $observer->getEvent()->getShippingMethod(),
                    'ordered' => (bool) placeOrderOf($quote)->getId(),
                ];
            }
        };
        Mage::register('_singleton/place_order_parity_api_listener', $listener, true);
        (function (): void {
            $this->_events['global']['sales_api_place_order_before']['observers']['place_order_parity_api_listener']
                = ['type' => 'singleton', 'model' => 'place_order_parity_api_listener', 'method' => 'record'];
        })->call(Mage::app());

        try {
            placeOrderViaAdminGraphQl($staffCart);
            $api = placeOrderViaApi($apiCart);
        } finally {
            (function (): void {
                unset($this->_events['global']['sales_api_place_order_before']['observers']['place_order_parity_api_listener']);
            })->call(Mage::app());
            Mage::unregister('_singleton/place_order_parity_api_listener');
        }

        $expected = fn(Mage_Sales_Model_Quote $cart): array => [
            'quote' => (int) $cart->getId(), 'payment' => 'checkmo', 'shipping' => 'flatrate_flatrate', 'ordered' => false,
        ];
        expect($api['status'])->toBe(200)
            ->and($listener->calls)->toBe([$expected($staffCart), $expected($apiCart)])
            ->and(placeOrderOf($staffCart)->getId())->not->toBeNull();
    });
});
