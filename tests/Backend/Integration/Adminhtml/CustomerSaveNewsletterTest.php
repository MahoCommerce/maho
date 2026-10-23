<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin customer save reads the newsletter checkbox from the Newsletter tab, but that tab is
 * not always rendered (Mage_Newsletter output disabled, customer on the admin website). A save
 * whose form had no newsletter field must leave the subscription alone instead of unsubscribing
 * the customer and sending the unsubscription email.
 */

function customerSaveNewsletterCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setGroupId(1)
        ->setFirstname('Pest')
        ->setLastname('Newsletter')
        ->setEmail(uniqid('customer-save-newsletter-') . '@example.test')
        ->setPassword('pest-password-1234')
        ->setForceConfirmed(true)
        ->setIsSubscribed(true);
    $customer->save();

    return $customer;
}

function customerSaveNewsletterSave(Mage_Customer_Model_Customer $customer, array $extra): void
{
    $params = array_replace_recursive([
        'customer_id' => $customer->getId(),
        'account' => [
            'website_id' => $customer->getWebsiteId(),
            'group_id' => $customer->getGroupId(),
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'email' => $customer->getEmail(),
        ],
    ], $extra);

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/index.php/admin/customer/save', 'POST', $params),
    );
    $request->setRouteName('adminhtml')
        ->setControllerName('customer')
        ->setActionName('save')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    Mage::unregister('current_customer');

    $controller = new Mage_Adminhtml_CustomerController($request, new Mage_Core_Controller_Response_Http());

    try {
        $controller->saveAction();
    } catch (Throwable) {
        // The trailing redirect builds an admin url that needs no assertion here; the save has
        // already run by this point. Any other failure still surfaces through the assertions.
    }
}

function customerSaveNewsletterStatus(Mage_Customer_Model_Customer $customer): int
{
    return (int) Mage::getModel('newsletter/subscriber')->loadByCustomer($customer)->getStatus();
}

beforeEach(function () {
    // No MTA on CI runners: each newsletter email would wait for the SMTP timeout
    foreach ([0, 1] as $storeId) {
        Mage::app()->getStore($storeId)->setConfig('system/smtp/enabled', '');
    }
    $this->user = AdminRoleFixture::login(['admin/customer', 'admin/customer/manage', 'admin/newsletter', 'admin/newsletter/subscriber']);
    $this->customer = customerSaveNewsletterCustomer();
});

afterEach(function () {
    Mage::unregister('current_customer');
    $this->customer->delete();
    AdminRoleFixture::delete($this->user);
});

it('keeps the subscription when the form has no newsletter field', function () {
    expect(customerSaveNewsletterStatus($this->customer))->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);

    customerSaveNewsletterSave($this->customer, ['account' => ['lastname' => 'Saved']]);

    expect(Mage::getModel('customer/customer')->load($this->customer->getId())->getLastname())->toBe('Saved')
        ->and(customerSaveNewsletterStatus($this->customer))->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
});

it('unsubscribes when the newsletter box is unchecked', function () {
    customerSaveNewsletterSave($this->customer, ['subscription' => '0']);

    expect(customerSaveNewsletterStatus($this->customer))->toBe(Mage_Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
});

it('keeps the subscription when the newsletter box is checked', function () {
    customerSaveNewsletterSave($this->customer, ['subscription' => '1']);

    expect(customerSaveNewsletterStatus($this->customer))->toBe(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED);
});

it('renders an unchecked value before the newsletter checkbox', function () {
    Mage::register('current_customer', $this->customer);
    Mage::getDesign()->setArea('adminhtml')->setPackageName('default')->setTheme('default');

    $block = Mage::app()->getLayout()->createBlock('adminhtml/customer_edit_tab_newsletter');
    $html = $block->initForm()->getForm()->getHtml();
    Mage::unregister('subscriber');

    $hidden = strpos($html, 'name="subscription" value="0"');
    $checkbox = strpos($html, 'name="subscription" value="1"');

    expect($hidden)->not->toBeFalse()
        ->and($checkbox)->not->toBeFalse()
        ->and($hidden)->toBeLessThan($checkbox);
});
