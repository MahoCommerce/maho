<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A config value stored encrypted must never reach a template author, whether the field is
 * declared in a system.xml, remapped through config_path, or declared only in a config.xml
 * scope tree.
 */
describe('Encrypted config paths', function () {
    $configXmlOnly = [
        'paypal/credentials/client_id',
        'paypal/credentials/client_secret',
        'carriers/ups/username',
        'carriers/ups/password',
        'carriers/ups/access_license_number',
        'carriers/usps/payment_account_number',
        'carriers/usps/payment_crid',
        'carriers/usps/payment_mid',
    ];

    test('the list includes every path declared encrypted in a config.xml scope tree', function () use ($configXmlOnly) {
        $paths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();

        foreach ($configXmlOnly as $path) {
            expect($paths)->toContain($path);
        }
    });

    test('a system.xml field with a config_path is listed under its real path', function () {
        $paths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();

        expect($paths)->toContain('paypal/credentials/client_secret')
            ->not->toContain('payment/paypal_credentials/client_secret');
    });

    test('a system.xml field without a config_path is still listed', function () {
        $paths = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths();

        expect($paths)->toContain('payment/authorizenet/trans_key')
            ->toContain('system/smtp/password');
    });

    test('the exploded form follows the real path', function () {
        $entries = Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths(true);

        expect($entries)->toContain(['section' => 'paypal', 'group' => 'credentials', 'field' => 'client_secret']);
    });

    test('the core helper reports the same list', function () use ($configXmlOnly) {
        $paths = Mage::helper('core')->getEncryptedConfigPaths();

        foreach ($configXmlOnly as $path) {
            expect($paths)->toContain($path);
        }
    });

    test('the email path validator treats a config.xml-only path as encrypted', function () {
        $validator = new Mage_Adminhtml_Model_Email_PathValidator();

        expect($validator->isValid('paypal/credentials/client_secret'))->toBeTrue()
            ->and($validator->isValid('carriers/ups/password'))->toBeTrue()
            ->and($validator->isValid('general/store_information/name'))->toBeFalse();
    });

    test('a template cannot read an encrypted path through store.getConfig()', function () {
        $secret = 'plain-text-secret-' . bin2hex(random_bytes(4));
        $stored = Mage::helper('core')->encrypt($secret);
        Mage::app()->getStore()->setConfig('paypal/credentials/client_secret', $stored);
        Mage::app()->getStore()->setConfig('carriers/ups/password', $stored);

        $template = Mage::getModel('core/email_template');
        $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
        $template->setTemplateText(
            '[{{var store.getConfig("paypal/credentials/client_secret")}}]'
            . '[{{var store.getConfig("carriers/ups/password")}}]',
        );

        $html = $template->getProcessedTemplate([]);

        expect($html)->not->toContain($secret);
    });
});
