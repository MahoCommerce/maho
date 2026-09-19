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

describe('directive preview resolver', function () {
    it('resolves a media directive to a path inside the media directory', function () {
        expect(Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('{{media url="wysiwyg/logo.png"}}'))
            ->toBe(Mage::getBaseDir('media') . '/wysiwyg/logo.png');
    });

    it('accepts surrounding whitespace around the directive', function () {
        expect(Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath("\n {{media url=\"wysiwyg/logo.png\"}} "))
            ->toBe(Mage::getBaseDir('media') . '/wysiwyg/logo.png');
    });

    it('refuses a directive the preview does not serve', function () {
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('{{config path="web/unsecure/base_url"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('{{block type="core/template" template="x.phtml"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses more than one directive', function () {
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('{{media url="a.png"}}{{media url="b.png"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses text around the directive', function () {
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('x{{media url="a.png"}}'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses plain text and an empty string', function () {
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath(''))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('/etc/passwd'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid directive.');
    });

    it('refuses a media url that leaves the media directory', function () {
        expect(fn() => Mage_Adminhtml_Cms_WysiwygController::resolveDirectivePath('{{media url="../../app/etc/local.xml"}}'))
            ->toThrow(Mage_Core_Exception::class);
    });
});
