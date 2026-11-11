<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Contacts\Api\ContactFormProcessor;
use Maho\ApiPlatform\EventListener\ApiExceptionListener;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Helpers\CaptchaStub;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront form and the API send a contact message through Mage_Contacts_Service_Message (#1348).
 * Each test gives the same fields to both transports and compares the outcome.
 */

const CONTACT_STORE_EMAIL = 'store-inbox@example.com';

/**
 * Record the emails of the service instead of sending them. Both transports get this instance from Mage::getService().
 */
class ContactParitySpyService extends Mage_Contacts_Service_Message
{
    /** @var list<array{to: string, to_name: ?string, reply_to: string, data: array<string, mixed>}> */
    public array $sent = [];

    public bool $failAutoReply = false;

    #[\Override]
    protected function sendTemplate(
        string $templateId,
        string $to,
        ?string $toName,
        string $replyTo,
        \Maho\DataObject $message,
        int $storeId,
    ): bool {
        if ($this->failAutoReply && $to !== CONTACT_STORE_EMAIL) {
            throw new RuntimeException('The mail server is down.');
        }
        $this->sent[] = ['to' => $to, 'to_name' => $toName, 'reply_to' => $replyTo, 'data' => $message->getData()];
        return true;
    }

    /**
     * @return list<array{to: string, to_name: ?string, reply_to: string, data: array<string, mixed>}>
     */
    public function sentToStore(): array
    {
        return array_values(array_filter($this->sent, fn(array $mail): bool => $mail['to'] === CONTACT_STORE_EMAIL));
    }
}

/**
 * Post $fields to the storefront form, as the browser does. With $dispatch, run preDispatch() before the action.
 *
 * @return array{accepted: bool, errors: list<string>}
 */
function contactViaWeb(array $fields, bool $dispatch = false): array
{
    $fields['form_key'] = Mage::getSingleton('core/session')->getFormKey();
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/contacts/index/post', 'POST', $fields));
    $request->setPathInfo('/contacts/index/post');
    $request->setRouteName('contacts');
    $request->setControllerName('index');
    $request->setActionName('post');
    $request->setControllerModule('Mage_Contacts');
    $request->setDispatched(true);

    $controller = new Mage_Contacts_IndexController($request, new Mage_Core_Controller_Response_Http());
    if ($dispatch) {
        Mage::app()->setRequest($request);
        $controller->dispatch('post');
    } else {
        $controller->postAction();
    }

    $success = [];
    $errors = [];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        if ($message->getType() === Mage_Core_Model_Message::SUCCESS) {
            $success[] = $message->getText();
        } else {
            $errors[] = $message->getText();
        }
    }

    return ['accepted' => $success !== [] && $errors === [], 'errors' => $errors];
}

/**
 * Post $fields as the JSON body of POST /api/rest/v2/contact. ApiExceptionListener turns an exception into the response.
 *
 * @return array{accepted: bool, errors: list<string>, status: int, fields: list<string>, response: ?Response}
 */
function contactViaApi(array $fields): array
{
    $request = SymfonyRequest::create(
        '/api/rest/v2/contact',
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: json_encode($fields, JSON_THROW_ON_ERROR),
    );

    try {
        $dto = new ContactFormProcessor()->process(null, new Post(), [], ['request' => $request]);
        return ['accepted' => $dto->success === true, 'errors' => [], 'status' => 200, 'fields' => [], 'response' => null];
    } catch (Throwable $e) {
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
            'errors' => $details !== [] ? array_column($details, 'message') : [(string) $body['message']],
            'status' => (int) $response?->getStatusCode(),
            'fields' => array_column($details, 'field'),
            'response' => $response,
        ];
    }
}

/**
 * @return array<string, string>
 */
function contactFields(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'parity-' . uniqid() . '@example.com',
        'telephone' => '555 0100',
        'comment' => 'Do you ship to Canada?',
    ], $overrides);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Contacts_Helper_Data::XML_PATH_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Helper_Data::XML_PATH_API_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_HONEYPOT_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_RATE_LIMIT, '3');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_IP_RATE_LIMIT, '10');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_RECIPIENT_RATE_LIMIT, '3');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_AUTO_REPLY_ENABLED, '0');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_EMAIL_RECIPIENT, CONTACT_STORE_EMAIL);

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    Mage::unregister('_service/contacts/message');
    $this->spy = new ContactParitySpyService();
    Mage::register('_service/contacts/message', $this->spy);
});

it('accepts a valid message on both transports and emails the same data to the store', function () {
    $fields = contactFields();

    expect(contactViaWeb($fields)['accepted'])->toBeTrue()
        ->and(contactViaApi($fields)['accepted'])->toBeTrue();

    $sent = $this->spy->sentToStore();
    expect($sent)->toHaveCount(2)
        ->and($sent[0]['data'])->toBe($sent[1]['data'])
        ->and($sent[0]['reply_to'])->toBe($fields['email'])
        ->and($sent[0]['data'])->toBe([
            'name' => 'Jane Doe',
            'email' => $fields['email'],
            'telephone' => '555 0100',
            'comment' => 'Do you ship to Canada?',
        ]);
});

it('rejects the same fields with the same messages on both transports', function (array $overrides, array $fields) {
    $input = contactFields($overrides);

    $web = contactViaWeb($input);
    $api = contactViaApi($input);

    expect($web['accepted'])->toBeFalse()
        ->and($api['status'])->toBe(422)
        ->and($api['fields'])->toBe($fields)
        ->and($web['errors'])->toBe($api['errors'])
        ->and($this->spy->sent)->toBe([]);
})->with([
    'no name' => [['name' => '  '], ['name']],
    'an invalid email' => [['email' => 'not-an-email'], ['email']],
    'an empty email' => [['email' => ''], ['email']],
    'no comment' => [['comment' => ''], ['comment']],
    'a name that is only markup' => [['name' => '<b></b>'], ['name']],
    'nothing' => [['name' => '', 'email' => '', 'comment' => ''], ['name', 'email', 'comment']],
]);

it('accepts a message that fills the honeypot on both transports and sends nothing', function () {
    $fields = contactFields([Mage::helper('core')->getHoneypotFieldName() => 'I am a bot']);

    expect(contactViaWeb($fields)['accepted'])->toBeTrue()
        ->and(contactViaApi($fields)['accepted'])->toBeTrue()
        ->and($this->spy->sent)->toBe([]);
});

it('shares the limit for one email address between the form and the API', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_RATE_LIMIT, '1');
    $fields = contactFields();

    expect(contactViaWeb($fields)['accepted'])->toBeTrue();

    $api = contactViaApi($fields);
    expect($api['status'])->toBe(429)
        ->and($api['response']?->headers->get('Retry-After'))->toBe('3600')
        ->and($api['errors'])->toBe(['Too many requests. Please try again later.']);

    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $other = contactFields();
    expect(contactViaApi($other)['accepted'])->toBeTrue()
        ->and(contactViaWeb($other))->toBe(['accepted' => false, 'errors' => $api['errors']])
        ->and($this->spy->sentToStore())->toHaveCount(2);
});

it('shares the limit for one client between the form and the API, for any email address', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_IP_RATE_LIMIT, '1');

    expect(contactViaWeb(contactFields())['accepted'])->toBeTrue();

    $api = contactViaApi(contactFields());
    expect($api['status'])->toBe(429)
        ->and($api['response']?->headers->get('Retry-After'))->toBe('3600')
        ->and(contactViaWeb(contactFields()))->toBe(['accepted' => false, 'errors' => $api['errors']])
        ->and($this->spy->sentToStore())->toHaveCount(1);
});

it('gives one client more messages than one email address by default', function () {
    $defaults = Mage::getConfig()->getNode('default/system/rate_limit');

    expect((int) $defaults->contact)->toBe(3)
        ->and((int) $defaults->contact_ip)->toBe(10);
});

it('limits the auto-replies to one address across both transports', function () {
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_AUTO_REPLY_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_RECIPIENT_RATE_LIMIT, '1');
    $fields = contactFields();

    contactViaWeb($fields);
    contactViaApi($fields);

    $autoReplies = array_values(array_filter($this->spy->sent, fn(array $mail): bool => $mail['to'] === $fields['email']));
    expect($this->spy->sentToStore())->toHaveCount(2)
        ->and($autoReplies)->toHaveCount(1)
        ->and($autoReplies[0]['to_name'])->toBe('Jane Doe')
        ->and($autoReplies[0]['reply_to'])->toBe(CONTACT_STORE_EMAIL);
});

it('accepts the message when only the auto-reply fails', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_AUTO_REPLY_ENABLED, '1');
    $this->spy->failAutoReply = true;
    $fields = contactFields();

    expect(contactViaWeb($fields)['accepted'])->toBeTrue()
        ->and(contactViaApi($fields)['accepted'])->toBeTrue()
        ->and($this->spy->sentToStore())->toHaveCount(2);
});

it('gives the other fields of a custom form to the email template without markup', function () {
    $fields = contactFields(['subject' => '<b>Order</b> 100000001', 'captchaToken' => 'abc']);

    contactViaWeb($fields);
    contactViaApi($fields);

    $sent = $this->spy->sentToStore();
    expect($sent[0]['data'])->toBe($sent[1]['data'])
        ->and($sent[0]['data']['subject'])->toBe('Order 100000001')
        ->and($sent[0]['data'])->not->toHaveKeys(['form_key', 'captchaToken']);
});

it('answers 404 on the API when its channel is disabled', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Helper_Data::XML_PATH_API_ENABLED, '0');
    $fields = contactFields();

    $api = contactViaApi($fields);
    expect($api['status'])->toBe(404)
        ->and($api['errors'])->toBe(['Contact form is not available'])
        ->and(contactViaWeb($fields)['accepted'])->toBeTrue();
});

it('sends nothing from the storefront form when the form is disabled, and the API still accepts', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Helper_Data::XML_PATH_ENABLED, '0');
    $fields = contactFields();

    expect(contactViaWeb($fields, dispatch: true)['accepted'])->toBeFalse()
        ->and($this->spy->sent)->toBe([])
        ->and(contactViaApi($fields)['accepted'])->toBeTrue();
});

describe('the abuse checks', function () {
    afterEach(fn() => CaptchaStub::uninstall());

    it('refuses on both transports a message that the CAPTCHA module of the store refuses', function () {
        $captcha = CaptchaStub::install();
        $fields = contactFields(['maho_captcha' => 'wrong', 'captchaToken' => 'wrong']);

        $web = contactViaWeb($fields);
        $api = contactViaApi($fields);
        expect($web)->toBe(['accepted' => false, 'errors' => ['Incorrect CAPTCHA.']])
            ->and($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['captchaToken'])
            ->and($api['errors'])->toBe($web['errors'])
            ->and($this->spy->sent)->toBe([])
            ->and($captcha->checks)->toHaveCount(2)
            ->and($captcha->checks[0]['maho_captcha'])->toBe('wrong')
            ->and($captcha->checks[1]['captchaToken'])->toBe('wrong');
    });

    it('reads one honeypot switch for both transports', function () {
        Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_HONEYPOT_ENABLED, '0');
        $fields = contactFields([Mage::helper('core')->getHoneypotFieldName() => 'I am a bot']);

        expect(contactViaWeb($fields)['accepted'])->toBeTrue()
            ->and(contactViaApi($fields)['accepted'])->toBeTrue()
            ->and($this->spy->sentToStore())->toHaveCount(2);
    });
});
