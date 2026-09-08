<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A {{block}} directive names a template, and a template is a file that PHP includes. The
 * name must stay inside the design directory, otherwise a PHP file an attacker managed to
 * write elsewhere on disk (an error report, a custom option upload) becomes executable
 * through any admin-owned template that renders attacker-influenced directives.
 */
describe('Email template block directive stays inside the design directory', function () {
    beforeEach(function () {
        $this->markerDir = Mage::getBaseDir('var') . DS . 'tmp';
        if (!is_dir($this->markerDir)) {
            mkdir($this->markerDir, 0750, true);
        }
        $this->marker = 'BLOCK_INCLUDE_ESCAPED_' . bin2hex(random_bytes(4));
        $this->markerFile = $this->markerDir . DS . 'block_include_marker.phtml';
        file_put_contents($this->markerFile, '<?php echo "' . $this->marker . '"; ?>');
    });

    afterEach(function () {
        @unlink($this->markerFile);
    });

    $render = function (string $directive): string {
        $template = Mage::getModel('core/email_template');
        $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
        $template->setTemplateText('<p>' . $directive . '</p>');

        return $template->getProcessedTemplate(['reason' => 'x']);
    };

    test('a template inside the design directory renders', function () use ($render) {
        $html = $render('{{block type="core/template" template="core/formkey.phtml"}}');

        expect($html)->toContain('name="form_key"');
    });

    test('a parent traversal from the template directory does not include the file', function () use ($render) {
        $path = '../../../../../../var/tmp/block_include_marker.phtml';
        $html = $render('{{block type="core/template" template="' . $path . '"}}');

        expect($html)->not->toContain($this->marker);
    });

    test('an absolute path does not include the file', function () use ($render) {
        $html = $render('{{block type="core/template" template="' . $this->markerFile . '"}}');

        expect($html)->not->toContain($this->marker);
    });

    test('a path relative to the project root does not include the file', function () use ($render) {
        $html = $render('{{block type="core/template" template="var/tmp/block_include_marker.phtml"}}');

        expect($html)->not->toContain($this->marker);
    });

    test('a script_path parameter does not move the view directory', function () use ($render) {
        $html = $render('{{block type="core/template" script_path="' . $this->markerDir . '" template="block_include_marker.phtml"}}');

        expect($html)->not->toContain($this->marker);
    });
});
