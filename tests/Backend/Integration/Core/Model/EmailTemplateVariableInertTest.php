<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A variable value is data, never template text. The filter must emit it verbatim and never
 * rescan it for directives, otherwise a customer name or a payment failure reason becomes
 * a code execution vector.
 */
describe('Email template variable values stay inert', function () {
    $payloads = [
        'block' => '{{block type="core/template" template="page/html/head.phtml"}}',
        'method call' => '{{var this.getTemplateText()}}',
        'config' => '{{config path="web/secure/base_url"}}',
        'template include' => '{{template config_path="design/email/header"}}',
    ];

    foreach ($payloads as $label => $payload) {
        test("a $label directive inside a variable value renders as literal text", function () use ($payload) {
            $template = Mage::getModel('core/email_template');
            $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
            $template->setTemplateText('<p>Reason: {{var reason}}</p>');

            $html = $template->getProcessedTemplate(['reason' => $payload]);

            expect($html)->toContain($payload);
        });
    }

    test('a directive inside the template styles renders as literal text', function () {
        $payload = '{{block type="core/template" template="page/html/head.phtml"}}';
        $template = Mage::getModel('core/email_template');
        $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
        $template->setTemplateText('{{var non_inline_styles}}<p>body</p>');
        $template->setTemplateStyles('body { color: red; } ' . $payload);

        $html = $template->getProcessedTemplate([]);

        expect($html)->toContain($payload);
    });

    test('a directive inside the subject variable renders as literal text', function () {
        $payload = '{{var this.getTemplateText()}}';
        $template = Mage::getModel('core/email_template');
        $template->setTemplateSubject('Order {{var reason}}');

        expect($template->getProcessedTemplateSubject(['reason' => $payload]))->toContain($payload);
    });
});
