<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Maho\Giftcard\Api\GiftCard;
use Maho\Giftcard\Api\GiftCardProcessor;
use Maho\Giftcard\Api\GiftCardProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\TransportParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin pages, the storefront and the API create, change and check gift cards through
 * Maho_Giftcard_Service_Giftcard (#1348).
 */

const GIFTCARD_PARITY_PREFIX = 'PEST-PARITY-';

/**
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftcardParityApi(array $body, ?int $id = null): array
{
    $request = AdminApiParity::jsonRequest($id ? 'PUT' : 'POST', '/api/rest/v2/giftcards' . ($id ? "/{$id}" : ''), $body);
    $dto = new GiftCard();
    foreach ($body as $property => $value) {
        $dto->{$property} = $value;
    }
    return AdminApiParity::api($request, fn() => new GiftCardProcessor(AdminApiParity::adminSecurity())
        ->process($dto, $id ? new Put() : new Post(), $id ? ['id' => $id] : [], ['request' => $request]));
}

/**
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function giftcardParityApiBalance(string $code): array
{
    $request = AdminApiParity::jsonRequest('POST', '/api/graphql');
    return AdminApiParity::api($request, fn() => new GiftCardProvider()
        ->provide(new Query(name: 'checkBalance', class: GiftCard::class), [], ['args' => ['code' => $code]]));
}

/**
 * @return array{success: bool, message: string}
 */
function giftcardParityCartBalance(string $code): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/giftcard/cart/checkBalance', 'POST', ['giftcard_code' => $code]));
    $response = new Mage_Core_Controller_Response_Http();
    new Maho_Giftcard_CartController($request, $response)->checkBalanceAction();
    return json_decode((string) $response->getBody(), true);
}

function giftcardParityCard(string $suffix, array $data = []): Maho_Giftcard_Model_Giftcard
{
    $giftcard = Mage::getModel('giftcard/giftcard')->addData(['code' => GIFTCARD_PARITY_PREFIX . $suffix] + $data + ['balance' => 25.0]);
    $giftcard->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()]);
    return Mage::getService('giftcard/giftcard')->save($giftcard);
}

/**
 * Give the website of the current store the Maximum Balance $value.
 */
function giftcardParityMaxBalance(string $value): void
{
    $website = Mage::app()->getWebsite(Mage::app()->getStore()->getWebsiteId());
    Mage::getConfig()->setNode('websites/' . $website->getCode() . '/' . Maho_Giftcard_Model_Giftcard::XML_PATH_MAX_BALANCE, $value);
    (fn() => $this->_configCache = [])->call($website);
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    foreach (Mage::getModel('giftcard/giftcard')->getCollection()->addFieldToFilter('code', ['like' => GIFTCARD_PARITY_PREFIX . '%']) as $giftcard) {
        $giftcard->delete();
    }
    $this->websiteId = (int) Mage::app()->getStore()->getWebsiteId();
    giftcardParityMaxBalance('10000');
});

afterEach(fn() => giftcardParityMaxBalance('10000'));

describe('a new card', function (): void {
    it('needs a balance of at most the maximum on the admin page and in the API', function (): void {
        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
            'code' => GIFTCARD_PARITY_PREFIX . 'ADMIN', 'balance' => 20000, 'website_ids' => [$this->websiteId],
        ]);
        $api = giftcardParityApi(['code' => GIFTCARD_PARITY_PREFIX . 'API', 'initialBalance' => 20000.0, 'websiteIds' => [$this->websiteId]]);

        expect($admin['errors'])->toBe(['Gift card balance cannot exceed 10000'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin['errors'])
            ->and($api['fields'])->toBe(['initialBalance']);
    });

    it('reads the highest balance from the setting of the website, on the admin page and in the API', function (): void {
        giftcardParityMaxBalance('100');

        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
            'code' => GIFTCARD_PARITY_PREFIX . 'ADMIN', 'balance' => 200, 'website_ids' => [$this->websiteId],
        ]);
        $api = giftcardParityApi(['code' => GIFTCARD_PARITY_PREFIX . 'API', 'initialBalance' => 200.0, 'websiteIds' => [$this->websiteId]]);

        expect($admin['errors'])->toBe(['Gift card balance cannot exceed 100'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin['errors']);
    });

    it('accepts any balance when the setting is empty or 0', function (string $value): void {
        giftcardParityMaxBalance($value);

        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
            'code' => GIFTCARD_PARITY_PREFIX . 'ADMIN', 'balance' => 20000, 'website_ids' => [$this->websiteId],
        ]);
        $api = giftcardParityApi(['code' => GIFTCARD_PARITY_PREFIX . 'API', 'initialBalance' => 20000.0, 'websiteIds' => [$this->websiteId]]);

        expect($admin['errors'])->toBe([])
            ->and($api['status'])->toBe(200)
            ->and($api['result']->balance)->toBe(20000.0);
    })->with(['empty' => [''], 'zero' => ['0']]);

    it('gives no highest balance to a purchased card', function (): void {
        $giftcard = Mage::getModel('giftcard/giftcard')->setPurchaseOrderId(1);
        $giftcard->setWebsiteIds([$this->websiteId]);

        expect($giftcard->getMaxBalance())->toBeNull();
    });

    it('refuses a code that another card has in another case, on the admin page and in the API', function (): void {
        giftcardParityCard('TAKEN');

        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
            'code' => strtolower(GIFTCARD_PARITY_PREFIX . 'TAKEN'), 'balance' => 10, 'website_ids' => [$this->websiteId],
        ]);
        $api = giftcardParityApi(['code' => strtolower(GIFTCARD_PARITY_PREFIX . 'TAKEN'), 'initialBalance' => 10.0, 'websiteIds' => [$this->websiteId]]);

        $message = "Gift card code '" . strtolower(GIFTCARD_PARITY_PREFIX . 'TAKEN') . "' already exists";
        expect($admin['errors'])->toBe([$message])
            ->and($api['status'])->toBe(409)
            ->and($api['errors'])->toBe([$message]);
    });
});

describe('a card above a lowered highest balance', function (): void {
    it('keeps its balance and saves a change of status on the admin page and in the API', function (): void {
        $adminCard = giftcardParityCard('ABOVE-ADMIN', ['balance' => 500.0]);
        $apiCard = giftcardParityCard('ABOVE-API', ['balance' => 500.0]);
        giftcardParityMaxBalance('100');

        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'massStatus', [
            'giftcard' => [$adminCard->getId()], 'status' => Maho_Giftcard_Model_Giftcard::STATUS_DISABLED,
        ]);
        $api = giftcardParityApi(['status' => Maho_Giftcard_Model_Giftcard::STATUS_DISABLED], (int) $apiCard->getId());

        expect($admin['errors'])->toBe([])
            ->and($api['status'])->toBe(200)
            ->and(Mage::getModel('giftcard/giftcard')->load($adminCard->getId())->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED)
            ->and($api['result']->status)->toBe(Maho_Giftcard_Model_Giftcard::STATUS_DISABLED)
            ->and($api['result']->balance)->toBe(500.0);
    });

    it('refuses a new balance above the setting on the admin page and in the API', function (): void {
        $adminCard = giftcardParityCard('RAISE-ADMIN', ['balance' => 50.0]);
        $apiCard = giftcardParityCard('RAISE-API', ['balance' => 50.0]);
        giftcardParityMaxBalance('100');

        $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
            'id' => $adminCard->getId(), 'giftcard_id' => $adminCard->getId(), 'code' => $adminCard->getCode(),
            'status' => Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE, 'balance' => 200, 'website_ids' => [$this->websiteId],
        ]);
        $api = giftcardParityApi(['balance' => 200.0], (int) $apiCard->getId());

        expect($admin['errors'])->toBe(['Gift card balance cannot exceed 100'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe($admin['errors'])
            ->and($api['fields'])->toBe(['balance'])
            ->and((float) Mage::getModel('giftcard/giftcard')->load($adminCard->getId())->getBalance())->toBe(50.0);
    });

    it('takes a refund above the setting', function (): void {
        $giftcard = giftcardParityCard('REFUND', ['balance' => 90.0]);
        giftcardParityMaxBalance('100');

        $giftcard->refund(50.0);

        expect((float) Mage::getModel('giftcard/giftcard')->load($giftcard->getId())->getBalance())->toBe(140.0);
    });
});

it('refuses an unknown status on the admin mass action and in the API, and keeps the balance', function (): void {
    $giftcard = giftcardParityCard('STATUS');

    $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'massStatus', [
        'giftcard' => [$giftcard->getId()], 'status' => 'lost',
    ]);
    $api = giftcardParityApi(['status' => 'lost', 'balance' => 5.0], (int) $giftcard->getId());

    $message = 'Invalid gift card status; allowed: active, used, expired, disabled';
    expect($admin['errors'])->toBe([$message])
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe([$message])
        ->and((float) Mage::getModel('giftcard/giftcard')->load($giftcard->getId())->getBalance())->toBe(25.0);
});

describe('the balance check', function (): void {
    it('gives the same answer for an unknown, an expired and a foreign card on the storefront and in the API', function (): void {
        giftcardParityCard('EXPIRED', ['status' => Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED]);
        $live = giftcardParityCard('LIVE');

        foreach ([GIFTCARD_PARITY_PREFIX . 'NONE', GIFTCARD_PARITY_PREFIX . 'EXPIRED'] as $code) {
            $cart = giftcardParityCartBalance($code);
            $api = giftcardParityApiBalance($code);
            expect($cart)->toBe(['success' => false, 'message' => 'Gift card not found.'])
                ->and($api['status'])->toBe(404)
                ->and($api['errors'])->toBe(['Gift card not found.']);
        }

        expect(giftcardParityCartBalance((string) $live->getCode())['success'])->toBeTrue()
            ->and(giftcardParityApiBalance((string) $live->getCode())['result']->balance)->toBe(25.0);
    });

    it('shares the limit of one client between the storefront and the API', function (): void {
        $limit = (int) Mage::getStoreConfig(Maho_Giftcard_Service_Giftcard::XML_PATH_BALANCE_RATE_LIMIT);
        for ($i = 0; $i < $limit; $i++) {
            $i % 2 === 0 ? giftcardParityCartBalance('PEST-NONE') : giftcardParityApiBalance('PEST-NONE');
        }

        expect(giftcardParityCartBalance('PEST-NONE')['message'])->toBe('Too many attempts. Please wait a moment and try again.')
            ->and(giftcardParityApiBalance('PEST-NONE')['status'])->toBe(429);
    });
});

it('saves neither the status nor the balance when the admin page gives a refused balance', function (): void {
    $giftcard = giftcardParityCard('ATOMIC', ['balance' => 50.0]);
    giftcardParityMaxBalance('100');

    $admin = TransportParity::admin(Maho_Giftcard_Adminhtml_GiftcardController::class, 'save', [
        'id' => $giftcard->getId(), 'giftcard_id' => $giftcard->getId(), 'code' => $giftcard->getCode(),
        'status' => Maho_Giftcard_Model_Giftcard::STATUS_DISABLED, 'balance' => 200, 'website_ids' => [$this->websiteId],
    ]);

    $stored = Mage::getModel('giftcard/giftcard')->load($giftcard->getId());
    expect($admin['errors'])->toBe(['Gift card balance cannot exceed 100'])
        ->and($stored->getStatus())->toBe(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->and((float) $stored->getBalance())->toBe(50.0);
});

it('emails a purchased card through the service, which records the email', function (): void {
    $order = Mage::getModel('sales/order')->setStoreId(1)->setBaseToOrderRate(1.0)
        ->setIncrementId('PEST-GC-' . uniqid())->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD');
    $order->save();
    $orderItem = Mage::getModel('sales/order_item')->setOrderId($order->getId())->setProductType('giftcard')
        ->setSku('PEST-GC')->setName('Gift Card')->setQtyOrdered(1)
        ->setProductOptions(['info_buyRequest' => ['giftcard_amount' => 30, 'giftcard_recipient_email' => 'friend@example.com']]);
    $orderItem->save();
    $invoice = new class extends Mage_Sales_Model_Order_Invoice {
        /** @var list<Mage_Sales_Model_Order_Invoice_Item> */
        public array $items = [];

        #[\Override]
        public function getAllItems()
        {
            return $this->items;
        }
    };
    $invoice->setOrder($order)->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID);
    $invoice->items = [Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)->setQty(1)];

    try {
        Mage::getModel('giftcard/observer')->createGiftcardsOnInvoicePaid(new \Maho\Event\Observer([
            'event' => new \Maho\Event(['invoice' => $invoice]),
        ]));

        $giftcard = Mage::getModel('giftcard/giftcard')->getCollection()->addFieldToFilter('purchase_order_id', $order->getId())->getFirstItem();
        expect($giftcard->getId())->not->toBeNull()
            ->and($giftcard->getEmailSentAt())->not->toBeNull();
    } finally {
        foreach (Mage::getModel('giftcard/giftcard')->getCollection()->addFieldToFilter('purchase_order_id', $order->getId()) as $giftcard) {
            $giftcard->delete();
        }
        $order->delete();
    }
});

/**
 * Post the gift card box of the admin order page for $quote.
 *
 * @return array{success: list<string>, errors: list<string>}
 */
function giftcardParityAdminOrder(Mage_Sales_Model_Quote $quote, string $code, string $action): array
{
    $session = Mage::getSingleton('adminhtml/session_quote');
    $session->getMessages(true);
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/sales_order_create/loadBlock', 'POST', [
        'order' => ['giftcard' => ['code' => $code, 'action' => $action]],
    ]));
    Mage::getModel('giftcard/observer')->processAdminOrderGiftcard(new \Maho\Event\Observer([
        'event' => new \Maho\Event(['request_model' => $request, 'order_create_model' => new \Maho\DataObject(['quote' => $quote])]),
    ]));

    $messages = ['success' => [], 'errors' => []];
    foreach ($session->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

describe('the admin order page', function (): void {
    beforeEach(function (): void {
        $this->quote = Mage::getModel('sales/quote')->setStoreId(1);
        $this->quote->save();
    });

    afterEach(fn() => $this->quote->delete());

    it('applies a card, and removes it when the admin types the code in another case', function (): void {
        $giftcard = giftcardParityCard('ORDER');

        $apply = giftcardParityAdminOrder($this->quote, strtolower((string) $giftcard->getCode()), 'apply');
        expect($apply['errors'])->toBe([])
            ->and(json_decode((string) $this->quote->getGiftcardCodes(), true))->toHaveKey((string) $giftcard->getCode());

        $remove = giftcardParityAdminOrder($this->quote, strtolower((string) $giftcard->getCode()), 'remove');
        expect($remove)->toBe(['success' => ['Gift card was removed.'], 'errors' => []])
            ->and($this->quote->getGiftcardCodes())->toBeNull();
    });

    it('gives the error of the cart service for a card that is not applied', function (): void {
        $remove = giftcardParityAdminOrder($this->quote, GIFTCARD_PARITY_PREFIX . 'NONE', 'remove');

        expect($remove['errors'])->toBe(['Gift card "' . GIFTCARD_PARITY_PREFIX . 'NONE" is not applied to this cart']);
    });
});
