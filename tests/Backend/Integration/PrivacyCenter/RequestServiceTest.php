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
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach ($this->orders as $order) {
        $order->delete();
    }
    $emails = [];
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
    it('starts at once for a logged-in customer, and one export at a time', function () {
        $this->customers[] = $customer = privacyCustomer();

        $request = $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));

        expect($request->getStatus())->toBe(Request::STATUS_PROCESSING)
            ->and(fn() => $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1])))
            ->toThrow(Mage_Core_Exception_Conflict::class);
    });

    it('writes a ZIP with the data of the customer and without its secrets', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));

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
        $request = $this->service->submitForCustomer($owner, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));
        $this->service->export($request);

        expect(fn() => $this->service->openExportForCustomer((int) $request->getId(), (int) $other->getId()))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });

    it('deletes the file when the download link expires', function () {
        $this->customers[] = $customer = privacyCustomer();
        $request = $this->service->submitForCustomer($customer, Request::TYPE_EXPORT, new \Maho\DataObject(['store_id' => 1]));
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
        $review = Mage::getModel('review/review');
        $review->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
            ->setEntityPkValue(1)
            ->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED)
            ->setTitle('Nice')
            ->setDetail('A nice product')
            ->setNickname('Pri')
            ->setCustomerId((int) $customer->getId())
            ->setStoreId(1)
            ->setStores([1])
            ->save();
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
