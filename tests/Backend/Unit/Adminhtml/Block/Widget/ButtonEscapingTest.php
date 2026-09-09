<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('escapes quotes in the button onclick attribute', function () {
    $html = Mage::app()->getLayout()->createBlock('adminhtml/widget_button')
        ->setLabel('Send Email')
        ->setOnClick('if(confirm(\'Send to a" onmouseover="alert(1)\')) { go(); }')
        ->toHtml();

    expect($html)->not->toContain('" onmouseover="alert(1)')
        ->and($html)->toContain('&quot; onmouseover=&quot;alert(1)');
});

it('escapes quotes in the button style and value attributes', function () {
    $html = Mage::app()->getLayout()->createBlock('adminhtml/widget_button')
        ->setLabel('Go')
        ->setStyle('color:red" autofocus onfocus="alert(1)')
        ->setValue('v" onclick="alert(2)')
        ->toHtml();

    expect($html)->not->toContain('onfocus="alert(1)')
        ->and($html)->not->toContain('onclick="alert(2)');
});

it('escapes the concatenated grid column value', function () {
    $column = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column')
        ->setIndex(['firstname', 'lastname'])
        ->setSeparator(' ');
    $renderer = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column_renderer_concat')
        ->setColumn($column);
    $row = new Maho\DataObject(['firstname' => '<img src=x onerror=alert(1)>', 'lastname' => 'Doe']);

    expect($renderer->render($row))->toBe('&lt;img src=x onerror=alert(1)&gt; Doe');
});
