<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\Revocation\Api\RevocationRequest;
use Maho\Revocation\Api\RevocationRequestProcessor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront form and the API record a revocation declaration through Maho_Revocation_Service_Request (#1348).
 * Each test gives the same customer and the same order to both transports and compares the outcome.
 */

function revocationParitySecurity(int $customerId): Security
{
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private TokenStorage $storage) {}

        #[\Override]
        public function get(string $id): mixed
        {
            return $this->storage;
        }

        #[\Override]
        public function has(string $id): bool
        {
            return $id === 'security.token_storage';
        }
    });
}

function revocationParityCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1)
        ->setEmail('revoke-' . uniqid() . '@example.com')
        ->setFirstname('Max')
        ->setLastname('Mustermann')
        ->save();
    $GLOBALS['revocationParityCreated'][] = $customer;
    return $customer;
}

function revocationParityOrder(Mage_Customer_Model_Customer $customer, string $createdAt = 'now'): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId((string) random_int(900000000, 999999999))
        ->setStoreId(1)
        ->setCustomerId((int) $customer->getId())
        ->setCustomerIsGuest(false)
        ->setCustomerEmail($customer->getEmail())
        ->setCustomerFirstname('Max')
        ->setCustomerLastname('Mustermann')
        ->setBaseToGlobalRate(1)
        ->setBaseToOrderRate(1);
    $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->setStatus('complete');
    $order->save();

    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('sales/order'),
        ['created_at' => Mage::app()->getLocale()->formatDateForDb($createdAt)],
        ['entity_id = ?' => (int) $order->getId()],
    );
    $GLOBALS['revocationParityCreated'][] = $order;
    return Mage::getModel('sales/order')->load($order->getId());
}

/**
 * Post the storefront form as the logged-in $customer, from the "Revoke this contract" link of $order.
 *
 * @return array{accepted: bool, errors: list<string>, request: ?Maho_Revocation_Model_Request}
 */
function revocationViaWeb(Mage_Customer_Model_Customer $customer, Mage_Sales_Model_Order $order): array
{
    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/revocation/submit', 'POST', [
        'frt' => Mage::helper('core')->encrypt((string) (time() - 60)),
        'customer_name' => $customer->getName(),
        'email' => $customer->getEmail(),
        'order_reference' => $order->getIncrementId(),
        'session_order_id' => $order->getId(),
        'reason' => 'Changed my mind',
    ]));
    $request->setRouteName('revocation')->setControllerName('index')->setActionName('submit')->setDispatched(true);
    Mage::app()->setRequest($request);
    new Maho_Revocation_IndexController($request, new Mage_Core_Controller_Response_Http())->submitAction();

    $session = Mage::getSingleton('core/session');
    $errors = array_map(fn(Mage_Core_Model_Message_Abstract $m): string => $m->getText(), $session->getMessages(true)->getErrors());
    $success = $session->getRevocationSuccess(true);
    $model = Mage::getModel('revocation/request')->load(is_array($success) ? (int) $success['request_id'] : 0);
    if ($model->getId()) {
        $GLOBALS['revocationParityCreated'][] = $model;
    }

    return ['accepted' => is_array($success), 'errors' => $errors, 'request' => $model->getId() ? $model : null];
}

/**
 * Post the same declaration to POST /api/rest/v2/customers/me/revocation-requests as $customer.
 *
 * @return array{status: int, message: string, request: ?Maho_Revocation_Model_Request}
 */
function revocationViaApi(Mage_Customer_Model_Customer $customer, Mage_Sales_Model_Order $order): array
{
    $dto = new RevocationRequest();
    $dto->orderId = (int) $order->getId();
    $dto->reason = 'Changed my mind';
    $processor = new RevocationRequestProcessor(revocationParitySecurity((int) $customer->getId()));
    $result = TransportParity::api(fn() => $processor->process($dto, new Post(name: 'submit_revocation'), [], [
        'request' => SymfonyRequest::create('/api/rest/v2/customers/me/revocation-requests', 'POST'),
    ]));

    $model = $result['result'] instanceof RevocationRequest ? Mage::getModel('revocation/request')->load($result['result']->id) : null;
    if ($model?->getId()) {
        $GLOBALS['revocationParityCreated'][] = $model;
    }
    return ['status' => $result['status'], 'message' => $result['message'], 'request' => $model];
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_ENABLED, '1');
    $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_COOLING_OFF_DAYS, '14');
    $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_IP_RATE_LIMIT, '10');
    $store->setConfig('trans_email/ident_general/email', 'shop@example.com');
    // No MTA on CI runners: the disabled transport reports success without sending.
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $GLOBALS['revocationParityCreated'] = [];
});

afterEach(function () {
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    foreach (array_reverse($GLOBALS['revocationParityCreated']) as $model) {
        $model->delete();
    }
    Mage::unregister('isSecureArea');
});

it('records a verified declaration of the own order on both transports', function () {
    $customer = revocationParityCustomer();
    $order = revocationParityOrder($customer);

    $web = revocationViaWeb($customer, $order);
    $api = revocationViaApi($customer, $order);

    expect($web['accepted'])->toBeTrue()
        ->and($api['status'])->toBe(200);
    foreach ([$web['request'], $api['request']] as $request) {
        expect($request->getVerified())->toBe(1)
            ->and($request->getOrderId())->toBe((int) $order->getId())
            ->and($request->getOrderReference())->toBe($order->getIncrementId())
            ->and($request->getEmail())->toBe($customer->getEmail())
            ->and($request->getStoreId())->toBe(1);
    }
});

it('refuses both transports when revocation is disabled', function () {
    Mage::app()->getStore()->setConfig(Maho_Revocation_Helper_Data::XML_PATH_ENABLED, '0');
    $customer = revocationParityCustomer();
    $order = revocationParityOrder($customer);

    $web = revocationViaWeb($customer, $order);
    $api = revocationViaApi($customer, $order);

    expect($web['accepted'])->toBeFalse()
        ->and($web['request'])->toBeNull()
        ->and($api['status'])->toBe(404)
        ->and($api['request'])->toBeNull();
});

it('counts the submissions of one client on both transports together', function () {
    Mage::app()->getStore()->setConfig(Maho_Revocation_Helper_Data::XML_PATH_IP_RATE_LIMIT, '1');
    $customer = revocationParityCustomer();
    $order = revocationParityOrder($customer);

    expect(revocationViaWeb($customer, $order)['accepted'])->toBeTrue();
    $api = revocationViaApi($customer, $order);
    expect($api['status'])->toBe(429)
        ->and($api['message'])->toBe('Too many requests. Please try again later.');

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    expect(revocationViaApi($customer, $order)['status'])->toBe(200);
    $web = revocationViaWeb($customer, $order);
    expect($web['accepted'])->toBeFalse()
        ->and($web['errors'])->toBe(['Your request could not be processed right now. Please try again later or contact us directly.']);
});

describe('kept differences: the web form takes any declaration, the API takes only an own order in its period', function () {
    it('records an order of another customer as an unverified declaration on the web and refuses it on the API', function () {
        $customer = revocationParityCustomer();
        $order = revocationParityOrder(revocationParityCustomer());

        $web = revocationViaWeb($customer, $order);
        $api = revocationViaApi($customer, $order);

        expect($web['accepted'])->toBeTrue()
            ->and($web['request']->getVerified())->toBe(0)
            ->and($web['request']->getOrderId())->toBeNull()
            ->and($api['status'])->toBe(404)
            ->and($api['message'])->toBe('Order not found');
    });

    it('records an order after its revocation period on the web and refuses it on the API', function () {
        $customer = revocationParityCustomer();
        $order = revocationParityOrder($customer, '-30 days');

        $web = revocationViaWeb($customer, $order);
        $api = revocationViaApi($customer, $order);

        expect($web['accepted'])->toBeTrue()
            ->and($web['request']->getVerified())->toBe(1)
            ->and($api['status'])->toBe(422)
            ->and($api['message'])->toBe('The revocation period for this order has expired');
    });
});
