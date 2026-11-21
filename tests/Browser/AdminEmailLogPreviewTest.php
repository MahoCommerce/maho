<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The Email Body pane of System > Tools > Email Log shows the archived email in an iframe.
 * A click on a link in the email loaded the target page into that iframe and hid the email.
 */

const EMAIL_LOG_ADMIN_USER = 'email-log-preview-admin';
const EMAIL_LOG_ADMIN_PASSWORD = 'Password123!';
const EMAIL_LOG_MARKER = 'Email log preview marker';

beforeEach(function () {
    deleteEmailLogFixtures();
    createEmailLogAdmin();
});

// Per test rather than once at the end: afterAll runs after the base tearDown has called
// Mage::reset(), so there is no app left to clean up through
afterEach(function () {
    deleteEmailLogFixtures();
});

function deleteEmailLogFixtures(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(EMAIL_LOG_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }

    Mage::getModel('core/email_log')->getCollection()
        ->addFieldToFilter('subject', EMAIL_LOG_MARKER)
        ->walk('delete');
}

function createEmailLogAdmin(): void
{
    $user = Mage::getModel('admin/user')
        ->setUsername(EMAIL_LOG_ADMIN_USER)
        ->setFirstname('Email')
        ->setLastname('Log')
        ->setEmail('email-log-preview@example.test')
        ->setPassword(EMAIL_LOG_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();

    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();

    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
}

it('keeps the email in the preview when a link in it is clicked', function () {
    $log = Mage::getModel('core/email_log')
        ->setSubject(EMAIL_LOG_MARKER)
        ->setEmailTo('customer@example.test')
        ->setEmailFrom('store@example.test')
        ->setContentType('html')
        ->setEmailBody('<p>' . EMAIL_LOG_MARKER . '</p><a href="' . MahoServer::baseUrl() . '/"><span>View order</span></a>')
        ->setStatus('sent')
        ->save();

    $page = adminLoginAndVisit(
        EMAIL_LOG_ADMIN_USER,
        EMAIL_LOG_ADMIN_PASSWORD,
        '/admin/system_email_log/view/id/' . $log->getId(),
        'iframe[id^="email-body-"]',
    );

    $frame = 'document.querySelector(\'iframe[id^="email-body-"]\')';

    // The load handler sizes the iframe, so a height means that the handler ran
    waitForScript($page, $frame . '.style.height !== ""');

    // A span inside the link, as in a styled button, so the check must look past the click target
    $page->script($frame . '.contentDocument.querySelector("a span").click()');
    usleep(1_000_000);

    expect($page->script('(() => { try { return ' . $frame . '.contentDocument.body.textContent; } catch (e) { return ""; } })()'))
        ->toContain(EMAIL_LOG_MARKER);
});
