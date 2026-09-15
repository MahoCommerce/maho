<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->helper = Mage::helper('core');
});

it('escapes single quotes as well as double quotes', function () {
    expect($this->helper->escapeHtml('a\' onmouseover=\'alert(1)'))
        ->toBe('a&#039; onmouseover=&#039;alert(1)');
});

it('escapes single quotes when tags are allowed through', function () {
    expect($this->helper->escapeHtml('<em>x</em> it\'s', ['em']))
        ->toBe('<em>x</em> it&#039;s');
});

it('escapes single quotes in a url', function () {
    expect($this->helper->escapeUrl('/catalog/?a=1\' onmouseover=\'alert(1)'))
        ->toBe('/catalog/?a=1&apos; onmouseover=&apos;alert(1)');
});

it('keeps an escaped value inside a single quoted attribute', function () {
    $html = "<a title='" . $this->helper->escapeHtml('Bob\'s \'; alert(1)') . "'>x</a>";

    expect($html)->toBe('<a title=\'Bob&#039;s &#039;; alert(1)\'>x</a>')
        ->and(substr_count($html, "'"))->toBe(2);
});

it('escapes form element values with both quote styles', function () {
    $element = new Maho\Data\Form\Element\Text();
    $element->setValue('a" b\' c');

    expect($element->getEscapedValue())->toBe('a&quot; b&#039; c');
});
