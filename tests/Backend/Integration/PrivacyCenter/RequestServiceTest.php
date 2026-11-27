<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

uses(Tests\MahoBackendTestCase::class);

/**
 * A customer or a guest asks for a copy or for the erasure of the personal data. A guest proves the email with a
 * link. An erasure waits for its cooling-off period, its approval and the end of the open orders, and it keeps the
 * orders and the proof of each accepted agreement.
 */
function privacyCustomer(): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setEmail('privacy-' . uniqid() . '@example.com')
        ->setFirstname('Pri')
        ->setLastname('Vacy')
        ->setPassword('Password123!')
        ->save();
}

/**
 * Give $request a token that the test knows, as the confirmation email does.
 */
function privacyToken(Request $request): string
{
    $token = bin2hex(random_bytes(32));
    $request->setTokenHash(Mage::helper('privacycenter')->hashToken($token))->save();
    return $token;
}

/**
 * An export of $customer that the customer confirmed with the link of the email, so that it can run.
 */
function privacyConfirmedExport(Mage_Customer_Model_Customer $customer): Request
{
    $service = Mage::getService('privacycenter/request');
    $request = $service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));
    return $service->confirm(privacyToken($request));
}

function privacyOrder(Mage_Customer_Model_Customer $customer, string $state): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')
        ->setIncrementId((string) random_int(800000000, 899999999))
        ->setStoreId(1)
        ->setData('state', $state)
        ->setStatus($state)
        ->setCustomerId((int) $customer->getId())
        ->setCustomerEmail($customer->getEmail())
        ->setCustomerIsGuest(false)
        ->setGrandTotal(10.0)
        ->setBaseGrandTotal(10.0)
        ->save();
}

function privacyGuestOrder(string $email, string $state): Mage_Sales_Model_Order
{
    return Mage::getModel('sales/order')
        ->setIncrementId((string) random_int(800000000, 899999999))
        ->setStoreId(1)
        ->setData('state', $state)
        ->setStatus($state)
        ->setCustomerId(null)
        ->setCustomerEmail($email)
        ->setCustomerIsGuest(true)
        ->setGrandTotal(10.0)
        ->setBaseGrandTotal(10.0)
        ->save();
}

function privacyReview(Mage_Customer_Model_Customer $customer): Mage_Review_Model_Review
{
    $review = Mage::getModel('review/review');
    return $review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
        ->setEntityPkValue(1)
        ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
        ->setTitle('Nice')
        ->setDetail('A nice product')
        ->setNickname('Pri')
        ->setCustomerId((int) $customer->getId())
        ->setStoreId(1)
        ->setStores([1])
        ->save();
}

function privacySubject(Mage_Customer_Model_Customer $customer): Maho_PrivacyCenter_Model_Subject
{
    return new Maho_PrivacyCenter_Model_Subject((int) $customer->getId(), (string) $customer->getEmail(), 1);
}

function privacySubscriberRow(int $customerId, string $email): Mage_Newsletter_Model_Subscriber
{
    return Mage::getModel('newsletter/subscriber')
        ->setStoreId(1)
        ->setCustomerId($customerId)
        ->setSubscriberEmail($email)
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->setSubscriberConfirmCode(substr(md5(uniqid('', true)), 0, 32))
        ->save();
}

/**
 * A gift card with a value of 50 and the sender and recipient data in $data.
 *
 * @param array<string, string> $data
 */
function privacyGiftcard(array $data): Maho_Giftcard_Model_Giftcard
{
    $giftcard = Mage::getModel('giftcard/giftcard')
        ->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
        ->setWebsiteIds([1])
        ->setBalance(50.00)
        ->setInitialBalance(50.00)
        ->setMessage('Enjoy');
    foreach ($data as $key => $value) {
        $giftcard->setData($key, $value);
    }
    return $giftcard->save();
}

beforeEach(function () {
    $this->config = [
        Maho_PrivacyCenter_Helper_Data::XML_PATH_ENABLED => '1',
        Maho_PrivacyCenter_Helper_Data::XML_PATH_ALLOW_GUESTS => '1',
        Maho_PrivacyCenter_Helper_Data::XML_PATH_COOLING_OFF_DAYS => '0',
        Maho_PrivacyCenter_Helper_Data::XML_PATH_ERASURE_APPROVAL => '1',
        Maho_PrivacyCenter_Helper_Data::XML_PATH_IP_RATE_LIMIT => '0',
        Maho_PrivacyCenter_Helper_Data::XML_PATH_RECIPIENT_RATE_LIMIT => '0',
        'system/smtp/enabled' => '',
    ];
    foreach ([0, 1] as $storeId) {
        foreach ($this->config as $path => $value) {
            Mage::app()->getStore($storeId)->setConfig($path, $value);
        }
    }
    $this->service = Mage::getService('privacycenter/request');
    $this->customers = [];
    $this->orders = [];
    $this->guestEmails = [];
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        $order->delete();
    }
    $emails = $this->guestEmails;
    foreach ($this->customers as $customer) {
        $emails[] = (string) $customer->getEmail();
        if (Mage::getModel('customer/customer')->load($customer->getId())->getId()) {
            $customer->delete();
        }
    }
    Mage::unregister('isSecureArea');
    foreach (Mage::getResourceModel('privacycenter/request_collection')->addFieldToFilter('email', ['in' => $emails ?: ['-']]) as $request) {
        $this->service->delete($request);
    }
    Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_ENABLED, '0');
});

describe('a request of a guest', function () {
    it('waits for the link of the confirmation email, and saves only the hash of its token', function () {
        $email = 'guest-' . uniqid() . '@example.com';
        $request = $this->service->submit(new \Maho\DataObject(['email' => $email, 'type' => Request::TYPE_EXPORT, 'store_id' => 1]));
        $this->customers[] = Mage::getModel('customer/customer')->setEmail($email);

        expect($request->getStatus())->toBe(Request::STATUS_PENDING)
            ->and($request->getCustomerId())->toBeNull()
            ->and($request->getTokenHash())->toMatch('/^[a-f0-9]{64}$/')
            ->and($request->getTokenExpiresAt())->not->toBeNull();
    });

    it('becomes a request of the customer whose account has the email', function () {
        $this->customers[] = $customer = privacyCustomer();

        $request = $this->service->submit(new \Maho\DataObject(['email' => $customer->getEmail(), 'type' => Request::TYPE_EXPORT, 'store_id' => 1]));

        expect($request->getCustomerId())->toBe((int) $customer->getId());
    });

    it('records nothing when the client fills the honeypot', function () {
        $request = $this->service->submit(new \Maho\DataObject([
            'email' => 'bot@example.com',
            'type' => Request::TYPE_EXPORT,
            'store_id' => 1,
            'form_fields' => [Mage::helper('core')->getHoneypotFieldName() => 'filled'],
        ]));

        expect($request)->toBeNull();
    });

    it('refuses a second request from the same client when the limit is 1', function () {
        Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_IP_RATE_LIMIT, '1');
        Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
        try {
            $this->guestEmails[] = $first = 'privacy-guest-' . uniqid() . '@example.com';
            $this->service->submit(new \Maho\DataObject(['email' => $first, 'type' => Request::TYPE_EXPORT, 'store_id' => 1]));
            $second = 'privacy-guest-' . uniqid() . '@example.com';

            expect(fn() => $this->service->submit(new \Maho\DataObject(['email' => $second, 'type' => Request::TYPE_EXPORT, 'store_id' => 1])))
                ->toThrow(Mage_Core_Exception_TooManyRequests::class);
        } finally {
            Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
        }
    });

    it('refuses a guest when the store takes requests of customers only', function () {
        Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_ALLOW_GUESTS, '0');

        expect(fn() => $this->service->submit(new \Maho\DataObject(['email' => 'guest@example.com', 'type' => Request::TYPE_EXPORT, 'store_id' => 1])))
            ->toThrow(Mage_Core_Exception_Forbidden::class);
    });

    it('is not available when the store turns the Privacy Center off', function () {
        Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_ENABLED, '0');

        expect(fn() => $this->service->submit(new \Maho\DataObject(['email' => 'guest@example.com', 'type' => Request::TYPE_EXPORT, 'store_id' => 1])))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('refuses an unknown type and an invalid email', function () {
        try {
            $this->service->submit(new \Maho\DataObject(['email' => 'not-an-email', 'type' => 'delete-everything', 'store_id' => 1]));
            $fields = [];
        } catch (Mage_Core_Exception_Input $e) {
            $fields = array_column($e->getErrors(), 'field');
        }

        expect($fields)->toBe(['type', 'email']);
    });
});

describe('the confirmation link', function () {
    it('confirms the request once', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $token = privacyToken($request);

        $confirmed = $this->service->confirm($token);

        expect($confirmed->getStatus())->toBe(Request::STATUS_CONFIRMED)
            ->and($confirmed->getTokenHash())->toBeNull()
            ->and($confirmed->getScheduledAt())->not->toBeNull()
            ->and(fn() => $this->service->confirm($token))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('lets only one confirmation use the token, also when two run at the same time', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $hash = Mage::helper('privacycenter')->hashToken(privacyToken($request));
        $resource = $request->getResource();

        expect($resource->consumeToken((int) $request->getId(), $hash))->toBeTrue()
            ->and($resource->consumeToken((int) $request->getId(), $hash))->toBeFalse();
    });

    it('does not work after it expires, and the cron expires the request', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $token = privacyToken($request);
        $request->setTokenExpiresAt(Mage::app()->getLocale()->formatDateForDb(time() - 60))->save();

        expect(fn() => $this->service->confirm($token))->toThrow(Mage_Core_Exception_NoSuchEntity::class)
            ->and($this->service->expirePending())->toBeGreaterThanOrEqual(1)
            ->and($this->service->getById((int) $request->getId())->getStatus())->toBe(Request::STATUS_EXPIRED);
    });
});

describe('an export', function () {
    it('waits for the link of the email of a logged-in customer, and one export at a time', function () {
        $this->customers[] = $customer = privacyCustomer();

        $request = $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));

        expect($request->getStatus())->toBe(Request::STATUS_PENDING)
            ->and(fn() => $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1])))
            ->toThrow(Mage_Core_Exception_Conflict::class)
            ->and($this->service->confirm(privacyToken($request))->getStatus())->toBe(Request::STATUS_PROCESSING);
    });

    it('writes a ZIP with the data of the customer and without its secrets', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = privacyConfirmedExport($customer);

        $this->service->export($request);
        $file = $this->service->openExportForCustomer((int) $request->getId(), (int) $customer->getId());
        $zipFile = (string) tempnam(sys_get_temp_dir(), 'privacy-test');
        file_put_contents($zipFile, stream_get_contents($file['stream']));
        $zip = new ZipArchive();
        $zip->open($zipFile);
        $data = json_decode((string) $zip->getFromName('data.json'), true);
        $html = (string) $zip->getFromName('data.html');
        $zip->close();
        unlink($zipFile);

        expect($request->getStatus())->toBe(Request::STATUS_COMPLETED)
            ->and($request->isExportAvailable())->toBeTrue()
            ->and($data['sections']['profile']['email'])->toBe($customer->getEmail())
            ->and($data['sections']['profile'])->not->toHaveKey('password_hash')
            ->and($data['sections'])->toHaveKeys(['addresses', 'orders', 'reviews', 'wishlist', 'newsletter', 'agreements', 'privacy_requests'])
            ->and($html)->toContain(htmlspecialchars((string) $customer->getEmail()));
    });

    it('gives the file only to its customer', function () {
        $this->customers[] = $owner = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $request = privacyConfirmedExport($owner);
        $this->service->export($request);

        expect(fn() => $this->service->openExportForCustomer((int) $request->getId(), (int) $other->getId()))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('opens the file with the download link, and never lets that link confirm the request', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = privacyConfirmedExport($customer);
        $this->service->export($request);
        $token = privacyToken($request);

        $file = $this->service->openExportByToken($token);
        fclose($file['stream']);

        expect((int) $file['request']->getId())->toBe((int) $request->getId())
            ->and(fn() => $this->service->confirm($token))->toThrow(Mage_Core_Exception_NoSuchEntity::class);

        $request->setTokenExpiresAt(Mage::app()->getLocale()->formatDateForDb(time() - 60))->save();

        expect(fn() => $this->service->openExportByToken($token))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('finds the earlier requests of a guest email in any letter case', function () {
        $this->guestEmails[] = $email = 'privacy-guest-' . uniqid() . '@example.com';
        $this->guestEmails[] = strtoupper($email);
        $earlier = Mage::getModel('privacycenter/request')->setStoreId(1)->setEmail(strtoupper($email))
            ->setType(Request::TYPE_EXPORT)->setStatus(Request::STATUS_COMPLETED)->save();

        $found = $this->service->getSubjectRequests(new Maho_PrivacyCenter_Model_Subject(null, $email, 1));

        expect(array_map(fn(Request $request): int => (int) $request->getId(), $found))->toContain((int) $earlier->getId());
    });

    it('holds the guest orders of the email in any letter case', function () {
        $email = 'privacy-guest-' . uniqid() . '@example.com';
        $this->orders[] = $order = privacyGuestOrder(strtoupper($email), Mage_Sales_Model_Order::STATE_COMPLETE);

        $data = Mage::getModel('privacycenter/export_orders')->export(new Maho_PrivacyCenter_Model_Subject(null, $email, 1));

        expect(array_column($data, 'increment_id'))->toBe([$order->getIncrementId()]);
    });

    it('holds the guest orders of the email of the account, which the confirmation link proves', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->orders[] = $order = privacyGuestOrder((string) $customer->getEmail(), Mage_Sales_Model_Order::STATE_COMPLETE);

        $data = Mage::getModel('privacycenter/export_orders')->export(privacySubject($customer));

        expect(array_column($data, 'increment_id'))->toBe([$order->getIncrementId()]);
    });

    it('holds the guest subscription of the email of the account, and not the one of another customer', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $guestRow = privacySubscriberRow(0, (string) $customer->getEmail());
        $otherRow = privacySubscriberRow((int) $other->getId(), (string) $customer->getEmail());

        $data = Mage::getModel('privacycenter/export_newsletter')->export(privacySubject($customer));
        $guestRow->delete();
        $otherRow->delete();

        expect($data)->toHaveCount(1);
    });

    it('holds the revocation requests of the email and of the orders of the customer, and no other', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->orders[] = $order = privacyOrder($customer, Mage_Sales_Model_Order::STATE_COMPLETE);
        $revocation = fn(?int $orderId, string $reference, string $email) => Mage::getModel('revocation/request')->setData([
            'store_id' => 1, 'order_id' => $orderId, 'order_reference' => $reference, 'customer_name' => 'Pri Vacy',
            'email' => $email, 'received_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ])->save();
        $rows = [
            $revocation((int) $order->getId(), 'of-the-order', 'other-' . uniqid() . '@example.com'),
            $revocation(null, 'of-the-email', strtoupper((string) $customer->getEmail())),
            $revocation(null, 'of-another-person', 'other-' . uniqid() . '@example.com'),
        ];

        $data = Mage::getModel('revocation/privacy_exporter')->export(privacySubject($customer));
        foreach ($rows as $row) {
            $row->delete();
        }
        $references = array_column($data, 'order_reference');
        sort($references);

        expect($references)->toBe(['of-the-email', 'of-the-order']);
    });

    it('holds the gift cards that the customer sent and received, with the balance of a received card only, and never a code', function () {
        $this->customers[] = $customer = privacyCustomer();
        $email = (string) $customer->getEmail();
        $sent = privacyGiftcard(['sender_email' => strtoupper($email), 'recipient_email' => 'friend@example.com']);
        $received = privacyGiftcard(['sender_email' => 'friend@example.com', 'recipient_email' => $email]);

        $data = Mage::getModel('giftcard/privacy_exporter')->export(privacySubject($customer));
        $sent->delete();
        $received->delete();
        $byRecipient = array_column($data, null, 'recipient_email');

        expect($data)->toHaveCount(2)
            ->and($byRecipient['friend@example.com'])->not->toHaveKey('balance')
            ->and($byRecipient[$email])->toHaveKey('balance')
            ->and(json_encode($data))->not->toContain((string) $sent->getCode())
            ->and(json_encode($data))->not->toContain((string) $received->getCode());
    });

    it('deletes the file when the download link expires', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = privacyConfirmedExport($customer);
        $this->service->export($request);
        $path = (string) $request->getExportFile();
        $request->setTokenExpiresAt(Mage::app()->getLocale()->formatDateForDb(time() - 60))->save();

        $this->service->cleanExports();

        expect(Mage::helper('privacycenter')->getStorage()->fileExists($path))->toBeFalse()
            ->and($this->service->getById((int) $request->getId())->getExportFile())->toBeNull();
    });
});

describe('an erasure', function () {
    it('waits for the approval of the staff when the store asks for it', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $this->service->confirm(privacyToken($request));

        expect($this->service->processDueErasures())->toBe(0);

        $this->service->approve($this->service->getById((int) $request->getId()), null);

        expect($this->service->processDueErasures())->toBe(1);
    });

    it('waits for the end of its cooling-off period', function () {
        Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_COOLING_OFF_DAYS, '14');
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $request = $this->service->approve($this->service->confirm(privacyToken($request)), null);

        expect(fn() => $this->service->erase($request))->toThrow(Mage_Core_Exception_Conflict::class);
    });

    it('waits while the customer has an open order, unless the staff forces it', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->orders[] = privacyOrder($customer, Mage_Sales_Model_Order::STATE_PROCESSING);
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $request = $this->service->approve($this->service->confirm(privacyToken($request)), null);

        expect(fn() => $this->service->erase($request))->toThrow(Mage_Core_Exception_Conflict::class);

        $this->service->erase($request, force: true);

        expect($request->getStatus())->toBe(Request::STATUS_COMPLETED);
    });

    it('deletes the account, keeps the orders, and removes the name from the reviews', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->orders[] = $order = privacyOrder($customer, Mage_Sales_Model_Order::STATE_COMPLETE);
        $review = privacyReview($customer);
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $request = $this->service->approve($this->service->confirm(privacyToken($request)), null);

        $this->service->erase($request);
        $keptReview = Mage::getModel('review/review')->load($review->getId());
        $keptOrder = Mage::getModel('sales/order')->load($order->getId());

        expect($request->getStatus())->toBe(Request::STATUS_COMPLETED)
            ->and($request->getCustomerId())->toBeNull()
            ->and($request->getAdminNote())->toContain('customer: ')
            ->and(Mage::getModel('customer/customer')->load($customer->getId())->getId())->toBeNull()
            ->and($keptOrder->getId())->not->toBeNull()
            ->and($keptOrder->getCustomerEmail())->toBe($customer->getEmail())
            ->and($keptReview->getNickname())->toBe('Anonymous')
            ->and($keptReview->getCustomerId())->toBeNull();

        $keptReview->delete();
    });

    it('waits while a guest has an open order in any letter case, and runs when the order is complete', function () {
        $this->guestEmails[] = $email = 'privacy-guest-' . uniqid() . '@example.com';
        $this->orders[] = $order = privacyGuestOrder(strtoupper($email), Mage_Sales_Model_Order::STATE_PROCESSING);
        $request = $this->service->submit(new \Maho\DataObject(['email' => $email, 'type' => Request::TYPE_ERASURE, 'store_id' => 1]));
        $request = $this->service->approve($this->service->confirm(privacyToken($request)), null);

        expect(fn() => $this->service->erase($request))->toThrow(Mage_Core_Exception_Conflict::class);

        $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE)->save();
        $this->service->erase($request);

        expect($request->getStatus())->toBe(Request::STATUS_COMPLETED);
    });

    it('deletes the reviews also outside the admin area, as the API does', function () {
        Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_REVIEW_ACTION, Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_DELETE);
        $this->customers[] = $customer = privacyCustomer();
        $review = privacyReview($customer);
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));
        $request = $this->service->approve($this->service->confirm(privacyToken($request)), null);
        $previousStoreId = (int) Mage::app()->getStore()->getId();
        Mage::app()->setCurrentStore(1);
        // The backend test case opens the secure area for every test, and the API has none
        Mage::unregister('isSecureArea');

        try {
            $this->service->erase($request);
        } finally {
            Mage::register('isSecureArea', true, true);
            Mage::app()->setCurrentStore($previousStoreId);
            Mage::app()->getStore(1)->setConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_REVIEW_ACTION, Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_ANONYMIZE);
        }

        expect($request->getStatus())->toBe(Request::STATUS_COMPLETED)
            ->and(Mage::getModel('review/review')->load($review->getId())->getId())->toBeNull();
    });

    it('keeps the cart of another account that has the same email', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $quote = Mage::getModel('sales/quote')->setStoreId(1)
            ->setData('customer_id', (int) $other->getId())
            ->setData('customer_email', $customer->getEmail())
            ->save();

        Mage::getModel('privacycenter/eraser_quotes')->erase(privacySubject($customer));
        $keptQuoteId = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getId();
        $quote->delete();

        expect($keptQuoteId)->not->toBeNull();
    });

    it('deletes a guest cart of the email in any letter case, also when its customer_id is 0', function () {
        $this->customers[] = $customer = privacyCustomer();
        $quote = Mage::getModel('sales/quote')->setStoreId(1)
            ->setData('customer_email', strtoupper((string) $customer->getEmail()))
            ->save();

        Mage::getModel('privacycenter/eraser_quotes')->erase(privacySubject($customer));
        $keptQuoteId = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId())->getId();
        if ($keptQuoteId) {
            $quote->delete();
        }

        expect($keptQuoteId)->toBeNull();
    });

    it('keeps the subscription of another customer that has the same email', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $row = privacySubscriberRow((int) $other->getId(), (string) $customer->getEmail());

        Mage::getModel('privacycenter/eraser_newsletter')->erase(privacySubject($customer));
        $kept = (bool) Mage::getModel('newsletter/subscriber')->load($row->getId())->getId();
        if ($kept) {
            $row->delete();
        }

        expect($kept)->toBeTrue();
    });

    it('deletes the logged emails that name the customer, and keeps the others', function () {
        $this->customers[] = $customer = privacyCustomer();
        $email = (string) $customer->getEmail();
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('core/email_log');
        $adapter->insert($table, ['subject' => 'Welcome', 'email_to' => 'Other <other@example.com>, Pri Vacy <' . strtoupper($email) . '>', 'email_body' => 'Hi']);
        $namedLogId = (int) $adapter->lastInsertId($table);
        $adapter->insert($table, ['subject' => 'Welcome', 'email_to' => 'x' . $email, 'email_body' => 'Hi']);
        $otherLogId = (int) $adapter->lastInsertId($table);

        Mage::getModel('privacycenter/eraser_logs')->erase(privacySubject($customer));
        $remaining = $adapter->fetchCol($adapter->select()->from($table, ['log_id'])->where('log_id IN (?)', [$namedLogId, $otherLogId]));
        $adapter->delete($table, ['log_id IN (?)' => [$namedLogId, $otherLogId]]);

        expect(array_map(intval(...), $remaining))->toBe([$otherLogId]);
    });

    it('clears the IP address, the birth date and the gender of the orders, and keeps the invoice data', function () {
        $this->customers[] = $customer = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $this->orders[] = $order = privacyOrder($customer, Mage_Sales_Model_Order::STATE_COMPLETE)
            ->setRemoteIp('203.0.113.7')
            ->setXForwardedFor('198.51.100.1')
            ->setCustomerDob('1980-01-02 00:00:00')
            ->setCustomerGender(2)
            ->setCustomerTaxvat('IT01234567890')
            ->save();
        $this->orders[] = $guestOrder = privacyGuestOrder(strtoupper((string) $customer->getEmail()), Mage_Sales_Model_Order::STATE_COMPLETE)
            ->setRemoteIp('203.0.113.8')
            ->save();
        $this->orders[] = $otherOrder = privacyOrder($other, Mage_Sales_Model_Order::STATE_COMPLETE)->setRemoteIp('203.0.113.9')->save();
        $address = Mage::getModel('sales/order_address')->setData([
            'parent_id' => (int) $order->getId(),
            'address_type' => 'billing',
            'customer_id' => (int) $customer->getId(),
            'firstname' => 'Pri',
            'lastname' => 'Vacy',
        ])->save();

        Mage::getModel('privacycenter/eraser_orders')->erase(privacySubject($customer));
        $keptOrder = Mage::getModel('sales/order')->load($order->getId());
        $keptAddress = Mage::getModel('sales/order_address')->load($address->getId());

        expect($keptOrder->getRemoteIp())->toBeNull()
            ->and($keptOrder->getXForwardedFor())->toBeNull()
            ->and($keptOrder->getCustomerDob())->toBeNull()
            ->and($keptOrder->getCustomerGender())->toBeNull()
            ->and($keptOrder->getCustomerTaxvat())->toBe('IT01234567890')
            ->and($keptOrder->getCustomerEmail())->toBe($customer->getEmail())
            ->and(Mage::getModel('sales/order')->load($guestOrder->getId())->getRemoteIp())->toBeNull()
            ->and(Mage::getModel('sales/order')->load($otherOrder->getId())->getRemoteIp())->toBe('203.0.113.9')
            ->and($keptAddress->getCustomerId())->toBeNull()
            ->and($keptAddress->getFirstname())->toBe('Pri');
    });

    it('deletes the gift messages of the carts, and keeps the gift message that an order shares with its cart', function () {
        $this->customers[] = $customer = privacyCustomer();
        $cartOnly = Mage::getModel('giftmessage/message')->setSender('Pri')->setRecipient('Friend')->setMessage('Happy birthday')->save();
        $shared = Mage::getModel('giftmessage/message')->setSender('Pri')->setRecipient('Friend')->setMessage('Merry Christmas')->save();
        foreach ([$cartOnly, $shared] as $message) {
            Mage::getModel('sales/quote')->setStoreId(1)
                ->setData('customer_id', (int) $customer->getId())
                ->setGiftMessageId((int) $message->getId())
                ->save();
        }
        $this->orders[] = privacyOrder($customer, Mage_Sales_Model_Order::STATE_COMPLETE)->setGiftMessageId((int) $shared->getId())->save();

        Mage::getModel('privacycenter/eraser_quotes')->erase(privacySubject($customer));
        $cartOnlyKept = Mage::getModel('giftmessage/message')->load($cartOnly->getId())->getId();
        $sharedKept = Mage::getModel('giftmessage/message')->load($shared->getId())->getId();
        $shared->delete();
        if ($cartOnlyKept) {
            $cartOnly->delete();
        }

        expect($cartOnlyKept)->toBeNull()
            ->and($sharedKept)->not->toBeNull();
    });

    it('removes the name, the email and the message of the customer from the delivered gift cards, and keeps their value', function () {
        $this->customers[] = $customer = privacyCustomer();
        $email = (string) $customer->getEmail();
        $sent = privacyGiftcard(['sender_name' => 'Pri', 'sender_email' => strtoupper($email), 'recipient_name' => 'Friend', 'recipient_email' => 'friend@example.com', 'email_sent_at' => '2026-11-01 10:00:00']);
        $received = privacyGiftcard(['sender_name' => 'Friend', 'sender_email' => 'friend@example.com', 'recipient_name' => 'Pri', 'recipient_email' => $email, 'email_sent_at' => '2026-11-01 10:00:00']);
        $pending = privacyGiftcard(['sender_name' => 'Pri', 'sender_email' => $email, 'recipient_name' => 'Friend', 'recipient_email' => 'friend@example.com']);

        $result = Mage::getModel('giftcard/privacy_eraser')->erase(privacySubject($customer));
        [$keptSent, $keptReceived, $keptPending] = array_map(
            fn(Maho_Giftcard_Model_Giftcard $card): Maho_Giftcard_Model_Giftcard => Mage::getModel('giftcard/giftcard')->load($card->getId()),
            [$sent, $received, $pending],
        );
        foreach ([$sent, $received, $pending] as $card) {
            $card->delete();
        }

        expect($result)->toBe('2 gift cards changed, 1 kept until their email is sent')
            ->and($keptSent->getSenderName())->toBeNull()
            ->and($keptSent->getSenderEmail())->toBeNull()
            ->and($keptSent->getMessage())->toBeNull()
            ->and($keptSent->getRecipientEmail())->toBe('friend@example.com')
            ->and($keptSent->getCode())->toBe($sent->getCode())
            ->and($keptSent->getBalance())->toBe(50.0)
            ->and($keptReceived->getRecipientEmail())->toBeNull()
            ->and($keptReceived->getSenderEmail())->toBe('friend@example.com')
            ->and($keptPending->getSenderEmail())->toBe($email);
    });

    it('clears the PayPal notifications that name the email, and keeps the ones that wait for processing', function () {
        $this->customers[] = $customer = privacyCustomer();
        $email = (string) $customer->getEmail();
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('paypal/webhook_event');
        $event = function (string $status, string $payerEmail) use ($adapter, $table): int {
            $adapter->insert($table, [
                'paypal_event_id' => 'WH-' . uniqid(),
                'event_type' => 'CHECKOUT.ORDER.APPROVED',
                'status' => $status,
                'payload' => json_encode(['resource' => ['payer' => ['email_address' => $payerEmail, 'name' => ['given_name' => 'Pri']]]]),
            ]);
            return (int) $adapter->lastInsertId($table);
        };
        $processed = $event('processed', strtoupper($email));
        $waiting = $event('received', $email);
        $longer = $event('processed', 'x' . $email);

        Mage::getModel('paypal/privacy_eraser')->erase(privacySubject($customer));
        $payloads = $adapter->fetchPairs($adapter->select()->from($table, ['event_id', 'payload'])->where('event_id IN (?)', [$processed, $waiting, $longer]));
        $adapter->delete($table, ['event_id IN (?)' => [$processed, $waiting, $longer]]);

        expect($payloads[$processed])->toBeNull()
            ->and($payloads[$waiting])->not->toBeNull()
            ->and($payloads[$longer])->not->toBeNull();
    });

    it('can be canceled by its customer before it runs', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));

        $canceled = $this->service->cancelForCustomer((int) $request->getId(), (int) $customer->getId());

        expect($canceled->getStatus())->toBe(Request::STATUS_CANCELED)
            ->and(fn() => $this->service->confirm(privacyToken($canceled)))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('cannot be canceled by another customer', function () {
        $this->customers[] = $owner = privacyCustomer();
        $this->customers[] = $other = privacyCustomer();
        $request = $this->service->submitForCustomer($owner, Request::TYPE_ERASURE, new \Maho\DataObject(['store_id' => 1]));

        expect(fn() => $this->service->cancelForCustomer((int) $request->getId(), (int) $other->getId()))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });
});
