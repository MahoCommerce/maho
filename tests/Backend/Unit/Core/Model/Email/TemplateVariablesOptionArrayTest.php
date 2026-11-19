<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The template variables of an email template come from the admin form as JSON. The Insert Variable
 * dialog shows them (GHSA-qrpm-g4h5-qrgr), so a value that is not a list of labels must not break it.
 */
describe('Mage_Core_Model_Email_Template::getVariablesOptionArray()', function () {
    it('returns one option for each variable', function (): void {
        $template = Mage::getModel('core/email_template')
            ->setData('orig_template_variables', '{"var customer.name":"Customer Name","var order.id":"Order ID"}');

        expect($template->getVariablesOptionArray())->toBe([
            ['value' => '{{var customer.name}}', 'label' => 'Customer Name'],
            ['value' => '{{var order.id}}', 'label' => 'Order ID'],
        ]);
    });

    it('skips a label that is not a scalar', function (): void {
        $template = Mage::getModel('core/email_template')
            ->setData('orig_template_variables', '{"var a":"Label A","var b":["x"],"var c":{"y":1}}');

        expect($template->getVariablesOptionArray())->toBe([
            ['value' => '{{var a}}', 'label' => 'Label A'],
        ]);
    });

    it('returns no option when the JSON is not an object', function (string $json): void {
        $template = Mage::getModel('core/email_template')->setData('orig_template_variables', $json);

        expect($template->getVariablesOptionArray(true))->toBe([]);
    })->with([
        'number' => '1',
        'string' => '"text"',
        'true' => 'true',
    ]);
});
