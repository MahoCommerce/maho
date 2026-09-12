<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Encrypted paths in the {{config}} allowlist', function () {
    $path = 'paypal/credentials/client_secret';

    afterEach(function () use ($path) {
        $variable = Mage::getModel('admin/variable')->load($path, 'variable_name');
        if ($variable->getId()) {
            $variable->delete();
        }
    });

    test('the variable model refuses an encrypted path', function () use ($path) {
        $variable = Mage::getModel('admin/variable')
            ->setVariableName($path)
            ->setIsAllowed('1');

        $errors = $variable->validate();

        expect($errors)->toBeArray()
            ->toContain('Encrypted configuration paths cannot be used as variables.');
    });

    test('an allowlisted encrypted path is still not allowed', function () use ($path) {
        Mage::getModel('admin/variable')
            ->setVariableName($path)
            ->setIsAllowed('1')
            ->save();

        $helper = new Mage_Admin_Helper_Variable();

        expect($helper->isPathAllowed($path))->toBeFalse()
            ->and($helper->isPathAllowed('web/unsecure/base_url'))->toBeTrue();
    });

    test('the config directive renders empty for an allowlisted encrypted path', function () use ($path) {
        Mage::getModel('admin/variable')
            ->setVariableName($path)
            ->setIsAllowed('1')
            ->save();
        $secret = 'plain-text-secret-' . bin2hex(random_bytes(4));
        Mage::app()->getStore()->setConfig($path, Mage::helper('core')->encrypt($secret));

        $template = Mage::getModel('core/email_template');
        $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
        $template->setTemplateText('[{{config path="' . $path . '"}}]');

        expect($template->getProcessedTemplate([]))->not->toContain($secret);
    });
});
