<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests the directive neutralization the "Validate HTML" action performs before
 * sending CMS markup to the W3C validator. A {{...}} directive inside an attribute
 * must be replaced by a non-empty placeholder so it does not collapse to an empty
 * value (e.g. src="") and produce a bogus "Must be non-empty" validation error.
 */

function blankDirectives(string $html): string
{
    return Mage_Adminhtml_Cms_WysiwygController::blankDirectivesForValidation($html);
}

it('keeps an image src non-empty when it holds a media directive', function () {
    $in = '<a href="/abby.html"><img src="{{media url="wysiwyg/egearD/abby.png"}}" alt="ABBY"></a>';
    expect(blankDirectives($in))
        ->toBe('<a href="/abby.html"><img src="#" alt="ABBY"></a>')
        ->not->toContain('src=""');
});

it('neutralizes a store directive used in an href', function () {
    expect(blankDirectives('<a href="{{store url="customer/account"}}">Account</a>'))
        ->toBe('<a href="#">Account</a>');
});

it('replaces every directive on a line independently', function () {
    expect(blankDirectives('<p>{{store url="foo"}} and {{block type="x"}}</p>'))
        ->toBe('<p># and #</p>');
});

it('leaves markup without directives untouched', function () {
    $in = '<img src="/media/wysiwyg/abby.png" alt="ABBY">';
    expect(blankDirectives($in))->toBe($in);
});

it('does not touch a lone unclosed brace pair', function () {
    expect(blankDirectives('<style>.foo { color: red }</style>'))
        ->toBe('<style>.foo { color: red }</style>');
});

it('handles an empty string', function () {
    expect(blankDirectives(''))->toBe('');
});
