<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A terms and conditions text keeps each version of its text, so that the store can prove which text a customer
 * accepted. A registration agreement asks the customers who did not accept it, and asks again after a change of the
 * text when the agreement says so.
 */
function acceptanceAgreement(array $data = []): Mage_Checkout_Model_Agreement
{
    return Mage::getModel('checkout/agreement')->setData($data + [
        'name' => 'Pest terms ' . uniqid(),
        'content' => 'First text',
        'checkbox_text' => 'I agree',
        'is_active' => 1,
        'is_html' => 0,
        'stores' => [0],
        'show_on_checkout' => 1,
        'show_on_registration' => 1,
    ])->save();
}

function acceptanceCustomer(): Mage_Customer_Model_Customer
{
    return Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setEmail('acceptance-' . uniqid() . '@example.com')
        ->setFirstname('Terms')
        ->setLastname('Tester')
        ->setPassword('Password123!')
        ->save();
}

/**
 * @return list<int>
 */
function pendingAgreementIds(Mage_Customer_Model_Customer $customer): array
{
    return array_map(
        fn(Mage_Checkout_Model_Agreement $agreement): int => (int) $agreement->getId(),
        Mage::getService('checkout/agreement')->getPendingForCustomer($customer, 1),
    );
}

beforeEach(function () {
    Mage::app()->getStore(0)->setConfig('checkout/options/enable_agreements', '1');
    Mage::app()->getStore(1)->setConfig('checkout/options/enable_agreements', '1');
    Mage::app()->getStore(1)->setConfig('system/smtp/enabled', '');
    $this->agreements = [];
    $this->customers = [];
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach ($this->customers as $customer) {
        $customer->delete();
    }
    foreach ($this->agreements as $agreement) {
        $agreement->delete();
    }
    Mage::unregister('isSecureArea');
    Mage::app()->getStore(0)->setConfig('checkout/options/enable_agreements', '0');
    Mage::app()->getStore(1)->setConfig('checkout/options/enable_agreements', '0');
});

describe('agreement versions', function () {
    it('saves the text of a new agreement as version 1', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();

        $versions = Mage::getResourceModel('checkout/agreement_version_collection')
            ->addFieldToFilter('agreement_id', (int) $agreement->getId());

        expect($agreement->getVersion())->toBe(1)
            ->and($versions->getSize())->toBe(1)
            ->and($versions->getFirstItem()->getContent())->toBe('First text');
    });

    it('adds a version when the text changes, and none when it stays the same', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();
        // The admin form saves the posted fields without a load
        $form = ['agreement_id' => $agreement->getId(), 'name' => $agreement->getName(), 'checkbox_text' => 'I agree', 'is_active' => 1, 'stores' => [0]];

        Mage::getModel('checkout/agreement')->setData($form + ['content' => 'First text'])->save();
        $same = Mage::getModel('checkout/agreement')->load($agreement->getId());
        Mage::getModel('checkout/agreement')->setData($form + ['content' => 'Second text'])->save();
        $changed = Mage::getModel('checkout/agreement')->load($agreement->getId());

        expect($same->getVersion())->toBe(1)
            ->and($changed->getVersion())->toBe(2);
    });

    it('keeps the versions after the agreement is deleted', function () {
        $agreement = acceptanceAgreement();
        $agreementId = (int) $agreement->getId();
        $versionId = Mage::getResourceSingleton('checkout/agreement')->getCurrentVersionIds([$agreementId])[$agreementId];

        $agreement->delete();
        $version = Mage::getModel('checkout/agreement_version')->load($versionId);

        expect($version->getId())->not->toBeNull()
            ->and($version->getAgreementId())->toBeNull()
            ->and($version->getContent())->toBe('First text');
    });

    it('refuses to change a saved acceptance', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();
        $acceptances = Mage::getService('checkout/agreement')->recordAcceptances(
            [(int) $agreement->getId()],
            Mage::getModel('checkout/agreement_acceptance')->setEmail('proof@example.com')->setContext('checkout'),
        );

        expect(fn() => $acceptances[0]->setEmail('other@example.com')->save())->toThrow(Mage_Core_Exception::class);
    });
});

describe('agreement locations', function () {
    it('lists an agreement only at the locations where it shows', function () {
        $this->agreements[] = $checkoutOnly = acceptanceAgreement(['show_on_registration' => 0]);
        $this->agreements[] = $registrationOnly = acceptanceAgreement(['show_on_checkout' => 0]);
        $service = Mage::getService('checkout/agreement');

        $checkoutIds = array_map(fn($agreement) => (int) $agreement->getId(), $service->getActive(1, Mage_Checkout_Model_Agreement::LOCATION_CHECKOUT));
        $registrationIds = array_map(fn($agreement) => (int) $agreement->getId(), $service->getActive(1, Mage_Checkout_Model_Agreement::LOCATION_REGISTRATION));

        expect($checkoutIds)->toContain((int) $checkoutOnly->getId())
            ->and($checkoutIds)->not->toContain((int) $registrationOnly->getId())
            ->and($registrationIds)->toContain((int) $registrationOnly->getId())
            ->and($registrationIds)->not->toContain((int) $checkoutOnly->getId());
    });

    it('lists no agreement when the store does not ask for terms', function () {
        $this->agreements[] = acceptanceAgreement();
        Mage::app()->getStore(1)->setConfig('checkout/options/enable_agreements', '0');

        expect(Mage::getService('checkout/agreement')->getActive(1, Mage_Checkout_Model_Agreement::LOCATION_CHECKOUT))->toBe([]);
    });
});

describe('the acceptance of a customer', function () {
    it('asks a customer who did not accept a registration agreement', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();
        $this->customers[] = $customer = acceptanceCustomer();

        expect(pendingAgreementIds($customer))->toContain((int) $agreement->getId());
    });

    it('stops asking after the acceptance, and asks again after a change when the agreement says so', function () {
        $this->agreements[] = $agreement = acceptanceAgreement(['requires_reconsent' => 1]);
        $this->customers[] = $customer = acceptanceCustomer();
        $service = Mage::getService('checkout/agreement');

        $service->acceptForCustomer($customer, 1, [(int) $agreement->getId()], Mage::getModel('checkout/agreement_acceptance'));
        $afterAcceptance = pendingAgreementIds($customer);
        $agreement->setContent('Second text')->save();

        expect($afterAcceptance)->not->toContain((int) $agreement->getId())
            ->and(pendingAgreementIds($customer))->toContain((int) $agreement->getId());
    });

    it('does not ask again after a change when the agreement does not say so', function () {
        $this->agreements[] = $agreement = acceptanceAgreement(['requires_reconsent' => 0]);
        $this->customers[] = $customer = acceptanceCustomer();

        Mage::getService('checkout/agreement')->acceptForCustomer($customer, 1, [(int) $agreement->getId()], Mage::getModel('checkout/agreement_acceptance'));
        $agreement->setContent('Second text')->save();

        expect(pendingAgreementIds($customer))->not->toContain((int) $agreement->getId());
    });

    it('refuses an agreement that does not wait for the customer', function () {
        $this->agreements[] = $agreement = acceptanceAgreement(['show_on_registration' => 0]);
        $this->customers[] = $customer = acceptanceCustomer();

        expect(fn() => Mage::getService('checkout/agreement')->acceptForCustomer(
            $customer,
            1,
            [(int) $agreement->getId()],
            Mage::getModel('checkout/agreement_acceptance'),
        ))->toThrow(Mage_Core_Exception_Input::class);
    });

    it('keeps the acceptance when the customer is deleted', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();
        $customer = acceptanceCustomer();
        Mage::getService('checkout/agreement')->acceptForCustomer($customer, 1, [(int) $agreement->getId()], Mage::getModel('checkout/agreement_acceptance'));

        Mage::register('isSecureArea', true, true);
        $customer->delete();
        Mage::unregister('isSecureArea');
        $kept = Mage::getService('checkout/agreement')->getAcceptances(null, $customer->getEmail());

        expect($kept->getSize())->toBe(1)
            ->and($kept->getFirstItem()->getCustomerId())->toBeNull()
            ->and($kept->getFirstItem()->getAgreementName())->toBe($agreement->getName());
    });
});

describe('the registration', function () {
    it('refuses a registration that does not accept a registration agreement', function () {
        $this->agreements[] = acceptanceAgreement();
        $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->setEmail('register-' . uniqid() . '@example.com')
            ->setFirstname('New')->setLastname('Customer')->setPassword('Password123!')->setPasswordConfirmation('Password123!');

        expect(fn() => Mage::getService('customer/customer')->register($customer, 1, []))
            ->toThrow(Mage_Core_Exception_Input::class);
        expect($customer->getId())->toBeNull();
    });

    it('keeps the proof of the registration agreements of a new customer', function () {
        $this->agreements[] = $agreement = acceptanceAgreement();
        $customer = Mage::getModel('customer/customer')->setWebsiteId(1)->setEmail('register-' . uniqid() . '@example.com')
            ->setFirstname('New')->setLastname('Customer')->setPassword('Password123!')->setPasswordConfirmation('Password123!');

        Mage::getService('customer/customer')->register(
            $customer,
            1,
            [],
            agreementIds: [(int) $agreement->getId()],
            client: Mage::getModel('checkout/agreement_acceptance')->setRemoteIp('203.0.113.7'),
        );
        $this->customers[] = $customer;
        $acceptance = Mage::getService('checkout/agreement')->getAcceptances((int) $customer->getId())->getFirstItem();

        expect($acceptance->getContext())->toBe(Mage_Checkout_Model_Agreement_Acceptance::CONTEXT_REGISTRATION)
            ->and($acceptance->getRemoteIp())->toBe('203.0.113.7')
            ->and($acceptance->getAgreementVersion())->toBe(1);
    });
});
