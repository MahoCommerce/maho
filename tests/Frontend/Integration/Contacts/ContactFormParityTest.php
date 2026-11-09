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
 * A CAPTCHA module that refuses every API token, as Maho_Captcha does for a wrong token.
 */
class ContactParityCaptchaHelper extends Maho_ApiPlatform_Helper_Data
{
    #[\Override]
    public function verifyCaptcha(array $data): ?string
    {
        return 'Incorrect CAPTCHA.';
    }
}

/**
 * Post $fields to the storefront form, as the browser does.
 *
 * @return array{accepted: bool, errors: list<string>}
 */
function contactViaWeb(array $fields): array
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
    $controller->postAction();

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
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_API_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_HONEYPOT_ENABLED, '1');
    $store->setConfig(Mage_Contacts_Service_Message::XML_PATH_EMAIL_RATE_LIMIT, '3');
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
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_EMAIL_RATE_LIMIT, '1');
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
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_API_ENABLED, '0');
    $fields = contactFields();

    $api = contactViaApi($fields);
    expect($api['status'])->toBe(404)
        ->and($api['errors'])->toBe(['Contact form is not available'])
        ->and(contactViaWeb($fields)['accepted'])->toBeTrue();
});

it('refuses a message of the web channel when the storefront form is disabled', function () {
    Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_ENABLED, '0');

    expect(fn() => Mage::getService('contacts/message')->send(contactFields(), Mage_Contacts_Model_Channel::Web, (int) Mage::app()->getStore()->getId()))
        ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
});

describe('the abuse checks', function () {
    afterEach(fn() => Mage::unregister('_helper/apiplatform'));

    it('asks the CAPTCHA module of the store on the API, and leaves the form to its predispatch observer', function () {
        Mage::unregister('_helper/apiplatform');
        Mage::register('_helper/apiplatform', new ContactParityCaptchaHelper());
        $fields = contactFields();

        $api = contactViaApi($fields);
        expect($api['status'])->toBe(422)
            ->and($api['fields'])->toBe(['captchaToken'])
            ->and($api['errors'])->toBe(['Incorrect CAPTCHA.'])
            ->and(contactViaWeb($fields)['accepted'])->toBeTrue()
            ->and($this->spy->sentToStore())->toHaveCount(1);
    });

    it('reads one honeypot switch for both transports', function () {
        Mage::app()->getStore()->setConfig(Mage_Contacts_Service_Message::XML_PATH_HONEYPOT_ENABLED, '0');
        $fields = contactFields([Mage::helper('core')->getHoneypotFieldName() => 'I am a bot']);

        expect(contactViaWeb($fields)['accepted'])->toBeTrue()
            ->and(contactViaApi($fields)['accepted'])->toBeTrue()
            ->and($this->spy->sentToStore())->toHaveCount(2);
    });
});
