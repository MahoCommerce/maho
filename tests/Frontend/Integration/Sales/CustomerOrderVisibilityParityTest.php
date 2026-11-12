<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use Mage\Sales\Api\InvoiceProvider;
use Mage\Sales\Api\OrderProcessor;
use Mage\Sales\Api\OrderProvider;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront and the API customer reads show a customer the same orders, through the rule of
 * Mage_Sales_Service_Order::isVisibleToCustomer() (#1348): an order in a state that the storefront hides, such as
 * Pending Payment, is not in the list, and a read by ID finds no such order. An admin token sees every order.
 */

function orderVisibilityCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setEmail('order-visibility-' . bin2hex(random_bytes(4)) . '@example.com')
        ->setFirstname('Visibility')
        ->setLastname('Customer')
        ->setForceConfirmed()
        ->setPassword('Password123!');
    $customer->save();
    return $customer;
}

function orderVisibilityOrder(int $customerId, string $state): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('VISIBILITY-' . strtoupper(bin2hex(random_bytes(5))))
        ->setCustomerId($customerId)
        ->setCustomerIsGuest(false)
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setGlobalCurrencyCode('USD')->setStoreCurrencyCode('USD')
        ->setSubtotal(0)->setGrandTotal(0)->setTotalQtyOrdered(0)
        ->setData('state', $state)
        ->setData('status', $state);
    $order->addItem(Mage::getModel('sales/order_item')->setData([
        'product_type' => 'simple', 'sku' => 'visibility', 'name' => 'Visibility item', 'qty_ordered' => 1,
    ]));
    $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
    $order->save();
    return $order;
}

function orderVisibilityCustomerSecurity(int $customerId): Security
{
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private readonly TokenStorage $storage) {}

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
 * Return the IDs of the orders on the My Orders page of the logged-in customer.
 *
 * @return list<int>
 */
function orderVisibilityWebList(): array
{
    $controller = orderVisibilityController();
    Mage::app()->getFrontController()->setAction($controller);
    $controller->getLayout()->createBlock('page/html', 'root');

    $ids = array_map(intval(...), new Mage_Sales_Block_Order_History()->getOrders()->getColumnValues('entity_id'));
    sort($ids);
    return $ids;
}

/**
 * Return true when the storefront opens the order $orderId for the logged-in customer.
 */
function orderVisibilityWebView(int $orderId): bool
{
    $controller = orderVisibilityController();

    return (fn(): bool => $this->_loadValidOrder($orderId))->call($controller);
}

function orderVisibilityController(): Mage_Sales_OrderController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/sales/order/view', 'GET'));
    $request->setRouteName('sales')->setControllerName('order')->setActionName('view')->setDispatched(true);
    Mage::app()->setRequest($request);
    Mage::unregister('current_order');

    return new Mage_Sales_OrderController($request, new Mage_Core_Controller_Response_Http());
}

/**
 * Return the IDs of the orders in /customers/me/orders for the security $security.
 *
 * @return list<int>
 */
function orderVisibilityApiList(Security $security): array
{
    $ids = [];
    foreach (new OrderProvider($security)->provide(new GetCollection(name: 'my_orders'), [], ['filters' => []]) as $dto) {
        $ids[] = $dto->id;
    }
    sort($ids);
    return $ids;
}

function orderVisibilityApiView(Security $security, int $orderId): ?int
{
    return new OrderProvider($security)->provide(new Get(), ['id' => $orderId], [])?->id;
}

/**
 * Read the invoices of the order $orderId, as GET /customers/me/orders/{orderId}/invoices does.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function orderVisibilityApiInvoices(Security $security, int $orderId, string $operationName = 'my_order_invoices'): array
{
    $request = AdminApiParity::jsonRequest('GET', "/api/rest/v2/customers/me/orders/{$orderId}/invoices");

    return AdminApiParity::api($request, fn() => new InvoiceProvider($security)
        ->provide(new GetCollection(name: $operationName), ['orderId' => $orderId], ['request' => $request]));
}

/**
 * Cancel the order $orderId with POST /orders/{id}/cancel. With $incrementId, cancel it with the GraphQL cancelOrder
 * mutation instead.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function orderVisibilityApiCancel(Security $security, int $orderId, ?string $incrementId = null): array
{
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/orders/{$orderId}/cancel");
    [$operation, $uriVariables, $context] = $incrementId === null
        ? [new Post(name: 'order_cancel'), ['id' => $orderId], ['request' => $request]]
        : [new Mutation(name: 'cancel'), [], ['request' => $request, 'args' => ['input' => ['incrementId' => $incrementId]]]];

    return AdminApiParity::api($request, fn() => new OrderProcessor($security)->process(null, $operation, $uriVariables, $context));
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

    $this->customer = orderVisibilityCustomer();
    $this->other = orderVisibilityCustomer();
    $customerId = (int) $this->customer->getId();
    $this->visible = orderVisibilityOrder($customerId, Mage_Sales_Model_Order::STATE_COMPLETE);
    $this->hidden = orderVisibilityOrder($customerId, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
    $this->foreign = orderVisibilityOrder((int) $this->other->getId(), Mage_Sales_Model_Order::STATE_COMPLETE);
    $this->security = orderVisibilityCustomerSecurity($customerId);
    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($this->customer);
});

afterEach(function (): void {
    Mage::getSingleton('customer/session')->logout();
    Mage::unregister('current_order');
    Mage::app()->getFrontController()->setAction(null);
    Mage::register('isSecureArea', true, true);
    foreach ([$this->visible, $this->hidden, $this->foreign] as $order) {
        $order->delete();
    }
    $this->customer->delete();
    $this->other->delete();
    Mage::unregister('isSecureArea');
});

it('leaves a Pending Payment order out of the list of the customer on both transports', function (): void {
    expect(orderVisibilityWebList())->toBe([(int) $this->visible->getId()])
        ->and(orderVisibilityApiList($this->security))->toBe([(int) $this->visible->getId()]);
});

it('finds no Pending Payment order by ID, as for an order of another customer, on both transports', function (): void {
    $visibleId = (int) $this->visible->getId();
    $hiddenId = (int) $this->hidden->getId();
    $foreignId = (int) $this->foreign->getId();

    expect(orderVisibilityWebView($visibleId))->toBeTrue()
        ->and(orderVisibilityWebView($hiddenId))->toBeFalse()
        ->and(orderVisibilityWebView($foreignId))->toBeFalse()
        ->and(orderVisibilityApiView($this->security, $visibleId))->toBe($visibleId)
        ->and(orderVisibilityApiView($this->security, $hiddenId))->toBeNull()
        ->and(orderVisibilityApiView($this->security, $foreignId))->toBeNull();
});

it('finds no invoices of a Pending Payment order, as for an order of another customer, in the API', function (): void {
    $hidden = orderVisibilityApiInvoices($this->security, (int) $this->hidden->getId());
    $foreign = orderVisibilityApiInvoices($this->security, (int) $this->foreign->getId());

    expect(orderVisibilityApiInvoices($this->security, (int) $this->visible->getId()))->toMatchArray(['status' => 200, 'result' => []])
        ->and($hidden)->toMatchArray(['status' => 404, 'errors' => ['Order not found']])
        ->and($foreign)->toBe($hidden)
        ->and(orderVisibilityApiInvoices($this->security, (int) $this->hidden->getId(), 'order_invoices'))->toBe($hidden);
});

it('shows a Pending Payment order and its invoices to an admin token', function (): void {
    $hiddenId = (int) $this->hidden->getId();
    $admin = AdminApiParity::adminSecurity();

    expect(orderVisibilityApiView($admin, $hiddenId))->toBe($hiddenId)
        ->and(orderVisibilityApiInvoices($admin, $hiddenId, 'order_invoices'))->toMatchArray(['status' => 200, 'result' => []]);
});

it('finds no Pending Payment order to cancel, as for an order of another customer, in the API', function (): void {
    $hidden = orderVisibilityApiCancel($this->security, (int) $this->hidden->getId());

    expect($hidden)->toMatchArray(['status' => 404, 'errors' => ['Order not found']])
        ->and(orderVisibilityApiCancel($this->security, (int) $this->hidden->getId(), (string) $this->hidden->getIncrementId()))->toBe($hidden)
        ->and(orderVisibilityApiCancel($this->security, (int) $this->foreign->getId()))->toBe($hidden)
        ->and(orderVisibilityApiCancel($this->security, 99999999))->toBe($hidden)
        ->and(Mage::getModel('sales/order')->load($this->hidden->getId())->getState())->toBe(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
});

it('lets an admin token cancel a Pending Payment order', function (): void {
    $admin = orderVisibilityApiCancel(AdminApiParity::adminSecurity(), (int) $this->hidden->getId());

    expect($admin['status'])->toBe(200)
        ->and(Mage::getModel('sales/order')->load($this->hidden->getId())->getState())->toBe(Mage_Sales_Model_Order::STATE_CANCELED);
});
