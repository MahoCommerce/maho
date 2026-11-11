<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Post;
use Mage\Newsletter\Api\NewsletterProcessor;
use Mage\Newsletter\Api\NewsletterProvider;
use Mage\Newsletter\Api\NewsletterSubscription;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CaptchaStub;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The footer form, the account page, the unsubscribe link, the admin and the API change a newsletter
 * subscription through Mage_Newsletter_Service_Subscriber (#1348). Each test gives the same input to
 * the transports and compares the outcome.
 */

/**
 * Count the saves of a subscriber. A subscriber gets an email only after a save.
 */
class NewsletterParitySaveCounter
{
    public int $saves = 0;

    public function count(Maho\Event\Observer $observer): void
    {
        $this->saves++;
    }

    public static function install(): self
    {
        $counter = new self();
        Mage::register('_singleton/newsletter_parity_save_counter', $counter, true);
        newsletterParityObserve('newsletter_subscriber_save_after', 'newsletter_parity_save_counter', 'count');
        return $counter;
    }
}

/**
 * Make the singleton $model the only observer of $event, until newsletterParityStopObserving().
 */
function newsletterParityObserve(string $event, string $model, string $method): void
{
    (function () use ($event, $model, $method): void {
        $this->_events['global'][$event] = ['observers' => [
            $model => ['type' => 'singleton', 'model' => $model, 'method' => $method],
        ]];
    })->call(Mage::app());
}

function newsletterParityStopObserving(): void
{
    (function (): void {
        unset($this->_events['global']['newsletter_subscriber_save_after']);
    })->call(Mage::app());
    CaptchaStub::uninstall();
    Mage::unregister('_singleton/newsletter_parity_save_counter');
}

function newsletterParityRequest(string $path, string $method, array $params): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($path, $method, $params));
    $request->setPathInfo($path);
    $request->setRouteName('newsletter');
    $request->setControllerModule('Mage_Newsletter');
    $request->setDispatched(true);
    return $request;
}

/**
 * @return array{accepted: bool, messages: list<string>}
 */
function newsletterParityMessages(string $sessionAlias): array
{
    $success = [];
    $errors = [];
    foreach (Mage::getSingleton($sessionAlias)->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::SUCCESS) {
            $success[] = $message->getText();
        } else {
            $errors[] = $message->getText();
        }
    }
    return ['accepted' => $success !== [] && $errors === [], 'messages' => $errors !== [] ? $errors : $success];
}

/**
 * Post $email to the footer form, as the browser does, with the token of the CAPTCHA widget in $captchaToken.
 *
 * @return array{accepted: bool, messages: list<string>}
 */
function newsletterViaFooter(string $email, ?string $captchaToken = null): array
{
    $request = newsletterParityRequest('/newsletter/subscriber/new', 'POST', array_filter([
        'email' => $email,
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'maho_captcha' => $captchaToken,
    ], fn($value) => $value !== null));
    $request->setControllerName('subscriber');
    $request->setActionName('new');
    new Mage_Newsletter_SubscriberController($request, new Mage_Core_Controller_Response_Http())->newAction();

    return newsletterParityMessages('core/session');
}

/**
 * Post the form of the account page of the customer who is logged in.
 *
 * @return array{accepted: bool, messages: list<string>}
 */
function newsletterViaAccountPage(bool $subscribe): array
{
    $request = newsletterParityRequest('/newsletter/manage/save', 'POST', [
        'is_subscribed' => $subscribe ? '1' : '0',
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);
    $request->setControllerName('manage');
    $request->setActionName('save');
    new Mage_Newsletter_ManageController($request, new Mage_Core_Controller_Response_Http())->saveAction();

    return newsletterParityMessages('customer/session');
}

/**
 * Open the unsubscribe link of an email.
 *
 * @return array{accepted: bool, messages: list<string>}
 */
function newsletterViaLink(int $id, string $code): array
{
    $request = newsletterParityRequest('/newsletter/subscriber/unsubscribe', 'GET', ['id' => $id, 'code' => $code]);
    $request->setControllerName('subscriber');
    $request->setActionName('unsubscribe');
    new Mage_Newsletter_SubscriberController($request, new Mage_Core_Controller_Response_Http())->unsubscribeAction();

    return newsletterParityMessages('core/session');
}

function newsletterParitySecurity(?int $customerId): ?Security
{
    if ($customerId === null) {
        return null;
    }
    $storage = new TokenStorage();
    $user = new ApiUser('customer', ['ROLE_CUSTOMER'], $customerId);
    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));

    return new Security(new class ($storage) implements \Psr\Container\ContainerInterface {
        public function __construct(private readonly TokenStorage $storage) {}

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

/**
 * Give an exception of the API to ApiExceptionListener, as the kernel does.
 *
 * @return array{accepted: false, status: int, messages: list<string>, fields: list<string>, response: ?Response}
 */
function newsletterParityApiError(Throwable $e, SymfonyRequest $request): array
{
    $kernel = new class implements HttpKernelInterface {
        #[\Override]
        public function handle(SymfonyRequest $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };
    $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $e);
    new ApiExceptionListener()->onKernelException($event);
    $response = $event->getResponse();
    $body = json_decode((string) $response?->getContent(), true);
    $details = $body['details']['errors'] ?? [];

    return [
        'accepted' => false,
        'status' => (int) $response?->getStatusCode(),
        'messages' => $details !== [] ? array_column($details, 'message') : [(string) $body['message']],
        'fields' => array_column($details, 'field'),
        'response' => $response,
    ];
}

/**
 * Call POST /api/rest/v2/newsletter/subscribe or /unsubscribe, as a guest or with the token of $customerId.
 * With $graphQl, call the mutation of the same name, with the input in the arguments.
 *
 * @return array{accepted: bool, status: int, messages: list<string>, fields: list<string>, dto?: NewsletterSubscription, response?: ?Response}
 */
function newsletterViaApi(string $operation, ?string $email = null, ?int $customerId = null, ?string $captchaToken = null, bool $graphQl = false): array
{
    $request = SymfonyRequest::create("/api/rest/v2/newsletter/{$operation}", 'POST', server: ['CONTENT_TYPE' => 'application/json']);
    $data = new NewsletterSubscription();
    $context = ['request' => $request];
    if ($graphQl) {
        $context['args']['input'] = array_filter(['email' => $email, 'captchaToken' => $captchaToken], fn($value) => $value !== null);
    } else {
        $data->email = $email;
        $data->captchaToken = $captchaToken;
    }

    try {
        $dto = new NewsletterProcessor(newsletterParitySecurity($customerId))
            ->process($data, $graphQl ? new Mutation(name: $operation) : new Post(name: "{$operation}_rest"), [], $context);
        return ['accepted' => true, 'status' => 200, 'messages' => [(string) $dto->message], 'fields' => [], 'dto' => $dto];
    } catch (Throwable $e) {
        return newsletterParityApiError($e, $request);
    }
}

function newsletterParityEmail(): string
{
    return 'nlparity-' . uniqid() . '@example.com';
}

function newsletterParitySubscriber(string $email): Mage_Newsletter_Model_Subscriber
{
    return Mage::getModel('newsletter/subscriber')->loadByEmail($email);
}

function newsletterParityCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setEmail(newsletterParityEmail())
        ->setFirstname('Jane')
        ->setLastname('Doe')
        ->setPassword('Password123!');
    $customer->save();
    return $customer;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    // A message that an earlier test left in the session is not an outcome of this test
    Mage::getSingleton('core/session')->getMessages(true);
    Mage::getSingleton('customer/session')->getMessages(true);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    // The customer of the test logs in without the confirmation email
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Customer::XML_PATH_IS_CONFIRM, '0');
    Mage::getSingleton('customer/session')->logout();
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG, '1');
    $store->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '0');
    $store->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_SUBSCRIBE_RATE_LIMIT, '10');
    $store->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_UNSUBSCRIBE_RATE_LIMIT, '10');
    // An empty template sends no email
    $store->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRM_EMAIL_TEMPLATE, '');
    $store->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_SUCCESS_EMAIL_TEMPLATE, '');
    $store->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_UNSUBSCRIBE_EMAIL_TEMPLATE, '');

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $this->customer = null;
});

afterEach(function () {
    newsletterParityStopObserving();
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    $this->customer?->delete();
    Mage::unregister('isSecureArea');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('newsletter/subscriber'),
        ['subscriber_email LIKE ?' => 'nlparity-%'],
    );
});

it('subscribes a guest on both transports and gives the same answer', function (string $confirm, int $status, string $footer, string $api) {
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, $confirm);
    $webEmail = newsletterParityEmail();
    $apiEmail = newsletterParityEmail();

    expect(newsletterViaFooter($webEmail))->toBe(['accepted' => true, 'messages' => [$footer]])
        ->and(newsletterViaApi('subscribe', $apiEmail)['messages'])->toBe([$api]);

    foreach ([$webEmail, $apiEmail] as $email) {
        $subscriber = newsletterParitySubscriber($email);
        expect($subscriber->getStatus())->toBe($status)
            ->and($subscriber->getCustomerId())->toBe(0);
    }
})->with([
    'without a confirmation' => ['0', Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED, 'Thank you for your subscription.', 'You have been successfully subscribed to the newsletter.'],
    'with a confirmation' => ['1', Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE, 'Confirmation request has been sent.', 'A confirmation email has been sent. Please check your inbox.'],
]);

it('rejects the same email with the same message on both transports', function (string $email, string $message) {
    $api = newsletterViaApi('subscribe', $email);

    expect(newsletterViaFooter($email))->toBe(['accepted' => false, 'messages' => ["There was a problem with the subscription: {$message}"]])
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe(['email'])
        ->and($api['messages'])->toBe([$message]);
})->with([
    'no email' => ['', 'Email address is required'],
    'an invalid email' => ['not-an-email', 'Invalid email address'],
]);

it('gives a guest the answer of a new address for an address that is already subscribed', function () {
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '1');
    $email = newsletterParityEmail();
    Mage::getModel('newsletter/subscriber')
        ->setEmail($email)
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->save();

    $api = newsletterViaApi('subscribe', $email);
    expect(newsletterViaFooter($email))->toBe(['accepted' => true, 'messages' => ['Confirmation request has been sent.']])
        ->and($api['messages'])->toBe(['A confirmation email has been sent. Please check your inbox.'])
        ->and($api['dto']->customerId)->toBeNull()
        ->and(newsletterParitySubscriber($email)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
});

it('shares the limits of one address between the form and the API', function () {
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_SUBSCRIBE_RATE_LIMIT, '1');
    $email = newsletterParityEmail();

    expect(newsletterViaFooter($email)['accepted'])->toBeTrue();

    $api = newsletterViaApi('subscribe', $email);
    expect($api['status'])->toBe(429)
        ->and($api['response']?->headers->get('Retry-After'))->toBe('3600')
        ->and($api['messages'])->toBe(['Too many requests. Please try again later.']);

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $other = newsletterParityEmail();
    expect(newsletterViaApi('subscribe', $other)['accepted'])->toBeTrue()
        ->and(newsletterViaFooter($other))->toBe([
            'accepted' => false,
            'messages' => ['There was a problem with the subscription: Too many requests. Please try again later.'],
        ]);
});

it('subscribes the address of a customer without a confirmation on every transport', function (string $transport) {
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_CONFIRMATION_FLAG, '1');
    $this->customer = newsletterParityCustomer();
    $customerId = (int) $this->customer->getId();
    if ($transport !== 'api') {
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
    }

    $result = match ($transport) {
        'footer' => newsletterViaFooter((string) $this->customer->getEmail()),
        'account page' => newsletterViaAccountPage(true),
        'api' => newsletterViaApi('subscribe', null, $customerId),
    };

    $subscriber = newsletterParitySubscriber((string) $this->customer->getEmail());
    expect($result['accepted'])->toBeTrue()
        ->and($subscriber->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->and($subscriber->getCustomerId())->toBe($customerId);
    if ($transport === 'api') {
        expect($result['dto']->confirmationRequired)->toBeFalse()
            ->and($result['dto']->customerId)->toBe($customerId);
    }
})->with(['footer', 'account page', 'api']);

it('unsubscribes a customer on the API and on the account page, with one limit', function () {
    Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_UNSUBSCRIBE_RATE_LIMIT, '1');
    $this->customer = newsletterParityCustomer();
    $customerId = (int) $this->customer->getId();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    $service = Mage::getService('newsletter/subscriber');
    $service->subscribeCustomer($customerId, (int) Mage::app()->getStore()->getId());

    $api = newsletterViaApi('unsubscribe', null, $customerId);
    expect($api['messages'])->toBe(['You have been successfully unsubscribed from the newsletter.'])
        ->and($api['dto']->status)->toBe('unsubscribed')
        ->and(newsletterParitySubscriber((string) $this->customer->getEmail())->getStatus())
        ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);

    $service->subscribeCustomer($customerId, (int) Mage::app()->getStore()->getId());
    expect(newsletterViaAccountPage(false))->toBe(['accepted' => false, 'messages' => ['Too many requests. Please try again later.']]);

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    expect(newsletterViaAccountPage(false))->toBe(['accepted' => true, 'messages' => ['The subscription has been removed.']])
        ->and(newsletterParitySubscriber((string) $this->customer->getEmail())->getStatus())
        ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
});

describe('a customer who changes the subscription of the account', function () {
    it('does not use the limit of the IP address that guests share', function (string $transport) {
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_SUBSCRIBE_RATE_LIMIT, '1');
        expect(newsletterViaFooter(newsletterParityEmail())['accepted'])->toBeTrue()
            ->and(newsletterViaFooter(newsletterParityEmail())['accepted'])->toBeFalse();

        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);

        $result = $transport === 'api' ? newsletterViaApi('subscribe', null, $customerId) : newsletterViaAccountPage(true);
        expect($result['accepted'])->toBeTrue()
            ->and(newsletterParitySubscriber((string) $this->customer->getEmail())->getStatus())
            ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    })->with(['account page', 'api']);

    it('has one limit for the account page and the API', function () {
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_SUBSCRIBE_RATE_LIMIT, '1');
        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);

        expect(newsletterViaAccountPage(true)['accepted'])->toBeTrue()
            ->and(newsletterViaAccountPage(false)['accepted'])->toBeTrue();

        $api = newsletterViaApi('subscribe', null, $customerId);
        expect($api['status'])->toBe(429)
            ->and($api['response']?->headers->get('Retry-After'))->toBe('3600')
            ->and(newsletterViaAccountPage(true))->toBe(['accepted' => false, 'messages' => $api['messages']]);
    });

    it('saves nothing, sends nothing and counts nothing when the subscription does not change', function (bool $subscribe) {
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_SUBSCRIBE_RATE_LIMIT, '1');
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Service_Subscriber::XML_PATH_UNSUBSCRIBE_RATE_LIMIT, '1');
        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
        expect(newsletterViaAccountPage(true)['accepted'])->toBeTrue();
        if (!$subscribe) {
            expect(newsletterViaAccountPage(false)['accepted'])->toBeTrue();
        }

        $counter = NewsletterParitySaveCounter::install();
        $operation = $subscribe ? 'subscribe' : 'unsubscribe';
        for ($i = 0; $i < 3; $i++) {
            expect(newsletterViaAccountPage($subscribe)['accepted'])->toBeTrue()
                ->and(newsletterViaApi($operation, null, $customerId)['accepted'])->toBeTrue();
        }
        expect($counter->saves)->toBe(0);
    })->with(['subscribed' => true, 'unsubscribed' => false]);
});

it('answers 404 on the API for a customer that does not exist', function (string $operation) {
    $api = newsletterViaApi($operation, null, 999999999);

    expect($api['status'])->toBe(404)
        ->and($api['messages'])->toBe(['Customer not found']);
})->with(['subscribe', 'unsubscribe']);

it('answers 404 for the status of a customer that does not exist', function () {
    $request = SymfonyRequest::create('/api/rest/v2/newsletter/status');
    try {
        new NewsletterProvider(newsletterParitySecurity(999999999))
            ->provide(new Get(name: 'status_rest', class: NewsletterSubscription::class), [], ['request' => $request]);
        $api = ['status' => 200];
    } catch (Throwable $e) {
        $api = newsletterParityApiError($e, $request);
    }

    expect($api['status'])->toBe(404);
});

it('refuses a wrong code and an unknown ID of an unsubscribe link with the same message', function () {
    $subscriber = Mage::getModel('newsletter/subscriber')
        ->setEmail(newsletterParityEmail())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->setSubscriberConfirmCode('nlparitycode0000000000000000000a');
    $subscriber->save();

    $refused = ['accepted' => false, 'messages' => ['Invalid subscription confirmation code.']];
    expect(newsletterViaLink((int) $subscriber->getId(), 'wrong'))->toBe($refused)
        ->and(newsletterViaLink(999999999, 'wrong'))->toBe($refused)
        ->and(newsletterViaLink((int) $subscriber->getId(), 'nlparitycode0000000000000000000a'))
        ->toBe(['accepted' => true, 'messages' => ['You have been unsubscribed.']]);
});

it('refuses to unsubscribe an unknown ID for the admin and saves nothing', function () {
    $table = Mage::getSingleton('core/resource')->getTableName('newsletter/subscriber');
    $read = Mage::getSingleton('core/resource')->getConnection('core_read');
    $count = (int) $read->fetchOne($read->select()->from($table, [new Maho\Db\Expr('COUNT(*)')]));

    $admin = AdminApiParity::admin(Mage_Adminhtml_Newsletter_SubscriberController::class, 'massUnsubscribe', ['subscriber' => ['999999999']]);
    expect($admin)->toBe(['success' => [], 'errors' => ['This subscriber no longer exists.']])
        ->and((int) $read->fetchOne($read->select()->from($table, [new Maho\Db\Expr('COUNT(*)')])))->toBe($count);
});

it('unsubscribes and deletes a subscriber from the admin grid', function () {
    $subscriber = Mage::getModel('newsletter/subscriber')
        ->setEmail(newsletterParityEmail())
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->setSubscriberConfirmCode('nlparitycode0000000000000000000b');
    $subscriber->save();
    $id = (int) $subscriber->getId();
    $controller = Mage_Adminhtml_Newsletter_SubscriberController::class;

    expect(AdminApiParity::admin($controller, 'massUnsubscribe', ['subscriber' => [(string) $id]])['errors'])->toBe([])
        ->and(Mage::getModel('newsletter/subscriber')->load($id)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED)
        ->and(AdminApiParity::admin($controller, 'massDelete', ['subscriber' => [(string) $id]])['errors'])->toBe([])
        ->and(Mage::getModel('newsletter/subscriber')->load($id)->getId())->toBeNull();
});

describe('the confirmation link', function () {
    /**
     * @return array{type: string, messages: list<string>}
     */
    function newsletterViaConfirmLink(int $id, string $code): array
    {
        $request = newsletterParityRequest('/newsletter/subscriber/confirm', 'GET', ['id' => $id, 'code' => $code]);
        $request->setControllerName('subscriber');
        $request->setActionName('confirm');
        new Mage_Newsletter_SubscriberController($request, new Mage_Core_Controller_Response_Http())->confirmAction();

        $messages = Mage::getSingleton('core/session')->getMessages(true)->getItems();
        return [
            'type' => $messages === [] ? '' : $messages[0]->getType(),
            'messages' => array_map(fn(Mage_Core_Model_Message_Abstract $message): string => $message->getText(), $messages),
        ];
    }

    it('confirms a subscriber with the code of the email, once', function () {
        $subscriber = Mage::getModel('newsletter/subscriber')
            ->setEmail(newsletterParityEmail())
            ->setStoreId((int) Mage::app()->getStore()->getId())
            ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE)
            ->setSubscriberConfirmCode('nlparitycode0000000000000000000c');
        $subscriber->save();
        $id = (int) $subscriber->getId();

        expect(newsletterViaConfirmLink($id, 'wrong'))
            ->toBe(['type' => Mage_Core_Model_Message::ERROR, 'messages' => ['Invalid subscription confirmation code.']])
            ->and(Mage::getModel('newsletter/subscriber')->load($id)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE)
            ->and(newsletterViaConfirmLink(999999999, 'wrong'))
            ->toBe(['type' => Mage_Core_Model_Message::ERROR, 'messages' => ['Invalid subscription ID.']])
            ->and(newsletterViaConfirmLink($id, 'nlparitycode0000000000000000000c'))
            ->toBe(['type' => Mage_Core_Model_Message::SUCCESS, 'messages' => ['Your subscription has been confirmed.']])
            ->and(Mage::getModel('newsletter/subscriber')->load($id)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->and(newsletterViaConfirmLink($id, 'nlparitycode0000000000000000000c'))
            ->toBe(['type' => Mage_Core_Model_Message::NOTICE, 'messages' => ['This email address is already confirmed.']]);
    });

    it('gives the service the same answers as the link', function () {
        $service = Mage::getService('newsletter/subscriber');

        expect(fn() => $service->confirm(999999999, 'wrong'))->toThrow(Mage_Core_Exception_NoSuchEntity::class, 'Invalid subscription ID.');
    });
});

describe('the answers that each transport keeps (see #1348)', function () {
    it('refuses a guest when guests may not subscribe, with a register link on the form and a 403 on the API', function () {
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG, '0');
        $email = newsletterParityEmail();

        $footer = newsletterViaFooter($email);
        $api = newsletterViaApi('subscribe', $email);
        expect($footer['accepted'])->toBeFalse()
            ->and($footer['messages'][0])->toContain('subscription for guests is not allowed')
            ->and($api['status'])->toBe(403)
            ->and($api['messages'])->toBe(['Guest subscription is not allowed. Please login first.'])
            ->and(newsletterParitySubscriber($email)->getId())->toBeNull();

        expect(fn() => Mage::getService('newsletter/subscriber')->subscribe($email, (int) Mage::app()->getStore()->getId(), null, []))
            ->toThrow(Mage_Core_Exception_Forbidden::class, 'Guest subscription is not allowed. Please login first.');
    });
});

describe('a customer who gives an address that is not the address of the account', function () {
    it('gets the confirmation request on both transports, also when the store asks no confirmation', function () {
        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
        $webEmail = newsletterParityEmail();
        $apiEmail = newsletterParityEmail();

        $api = newsletterViaApi('subscribe', $apiEmail, $customerId);
        expect(newsletterViaFooter($webEmail))->toBe(['accepted' => true, 'messages' => ['Confirmation request has been sent.']])
            ->and($api['messages'])->toBe(['A confirmation email has been sent. Please check your inbox.'])
            ->and($api['dto']->email)->toBe($apiEmail)
            ->and($api['dto']->customerId)->toBeNull()
            ->and($api['dto']->subscriberId)->toBeNull();

        foreach ([$webEmail, $apiEmail] as $email) {
            $subscriber = newsletterParitySubscriber($email);
            expect($subscriber->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE)
                ->and($subscriber->getCustomerId())->toBe(0);
        }
        expect(newsletterParitySubscriber((string) $this->customer->getEmail())->getId())->toBeNull();
    });

    it('is refused on both transports when guests may not subscribe', function () {
        Mage::app()->getStore()->setConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG, '0');
        $this->customer = newsletterParityCustomer();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
        $email = newsletterParityEmail();
        $message = 'You can subscribe only the email address of your account.';

        $api = newsletterViaApi('subscribe', $email, (int) $this->customer->getId());
        expect(newsletterViaFooter($email))->toBe(['accepted' => false, 'messages' => ["There was a problem with the subscription: {$message}"]])
            ->and($api['status'])->toBe(403)
            ->and($api['messages'])->toBe([$message])
            ->and(newsletterParitySubscriber($email)->getId())->toBeNull();
    });

    it('gets the answer of a new address for an address that is already subscribed', function () {
        $this->customer = newsletterParityCustomer();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
        $email = newsletterParityEmail();
        Mage::getModel('newsletter/subscriber')
            ->setEmail($email)
            ->setStoreId((int) Mage::app()->getStore()->getId())
            ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->save();

        $api = newsletterViaApi('subscribe', $email, (int) $this->customer->getId());
        expect(newsletterViaFooter($email))->toBe(['accepted' => true, 'messages' => ['Confirmation request has been sent.']])
            ->and($api['messages'])->toBe(['A confirmation email has been sent. Please check your inbox.'])
            ->and($api['dto']->subscriberId)->toBeNull()
            ->and(newsletterParitySubscriber($email)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    });

    it('subscribes the address of the account on the API when the body has this address', function () {
        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();

        $api = newsletterViaApi('subscribe', (string) $this->customer->getEmail(), $customerId);
        expect($api['messages'])->toBe(['You have been successfully subscribed to the newsletter.'])
            ->and($api['dto']->customerId)->toBe($customerId)
            ->and(newsletterParitySubscriber((string) $this->customer->getEmail())->getStatus())
            ->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
    });
});

describe('the CAPTCHA', function () {
    it('refuses on the footer form and on the API an address with a token that the CAPTCHA module refuses', function (?string $token) {
        $captcha = CaptchaStub::install();
        $webEmail = newsletterParityEmail();
        $apiEmail = newsletterParityEmail();

        $api = newsletterViaApi('subscribe', $apiEmail, captchaToken: $token);
        expect(newsletterViaFooter($webEmail, $token))
            ->toBe(['accepted' => false, 'messages' => ['There was a problem with the subscription: Incorrect CAPTCHA.']])
            ->and($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['captchaToken'])
            ->and($api['messages'])->toBe(['Incorrect CAPTCHA.'])
            ->and(newsletterParitySubscriber($webEmail)->getId())->toBeNull()
            ->and(newsletterParitySubscriber($apiEmail)->getId())->toBeNull()
            ->and($captcha->checks)->toHaveCount(2);
    })->with(['a wrong token' => 'wrong', 'no token' => null]);

    it('subscribes on each transport with a token that the CAPTCHA module accepts', function (string $transport) {
        $captcha = CaptchaStub::install();
        $email = newsletterParityEmail();

        $result = match ($transport) {
            'footer' => newsletterViaFooter($email, CaptchaStub::SOLVED),
            'REST' => newsletterViaApi('subscribe', $email, captchaToken: CaptchaStub::SOLVED),
            'GraphQL' => newsletterViaApi('subscribe', $email, captchaToken: CaptchaStub::SOLVED, graphQl: true),
        };
        expect($result['accepted'])->toBeTrue()
            ->and(newsletterParitySubscriber($email)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->and($captcha->checks)->toHaveCount(1);
    })->with(['footer', 'REST', 'GraphQL']);

    it('asks the CAPTCHA of a customer who gives an address that is not the address of the account', function () {
        CaptchaStub::install();
        $this->customer = newsletterParityCustomer();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);
        $webEmail = newsletterParityEmail();
        $apiEmail = newsletterParityEmail();

        expect(newsletterViaFooter($webEmail)['accepted'])->toBeFalse()
            ->and(newsletterViaApi('subscribe', $apiEmail, (int) $this->customer->getId())['fields'])->toBe(['captchaToken'])
            ->and(newsletterParitySubscriber($webEmail)->getId())->toBeNull()
            ->and(newsletterParitySubscriber($apiEmail)->getId())->toBeNull();
    });

    it('asks no CAPTCHA of a customer who subscribes the address of the account', function (string $transport) {
        $captcha = CaptchaStub::install();
        $this->customer = newsletterParityCustomer();
        $customerId = (int) $this->customer->getId();
        $email = (string) $this->customer->getEmail();
        Mage::getSingleton('customer/session')->setCustomer($this->customer);

        $result = match ($transport) {
            'account page' => newsletterViaAccountPage(true),
            'footer' => newsletterViaFooter($email),
            'api without an email' => newsletterViaApi('subscribe', null, $customerId),
            'api with the email' => newsletterViaApi('subscribe', $email, $customerId),
        };
        expect($result['accepted'])->toBeTrue()
            ->and(newsletterParitySubscriber($email)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->and($captcha->checks)->toBe([]);
    })->with(['account page', 'footer', 'api without an email', 'api with the email']);

    it('asks no CAPTCHA of an admin token, as the admin asks none', function () {
        $captcha = CaptchaStub::install();
        $email = newsletterParityEmail();
        $request = SymfonyRequest::create('/api/rest/v2/newsletter/subscribe', 'POST', server: ['CONTENT_TYPE' => 'application/json']);
        $data = new NewsletterSubscription();
        $data->email = $email;

        new NewsletterProcessor(AdminApiParity::adminSecurity())->process($data, new Post(name: 'subscribe_rest'), [], ['request' => $request]);
        expect(newsletterParitySubscriber($email)->getStatus())->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
            ->and($captcha->checks)->toBe([]);
    });
});
