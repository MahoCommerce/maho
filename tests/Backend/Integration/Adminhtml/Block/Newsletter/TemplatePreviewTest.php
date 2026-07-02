<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The newsletter template preview must resolve template directives into the
 * final HTML AND sanitize that HTML. Sanitizing the raw template first mangles
 * {{...}} directives (broken image); leaving directive content unsanitized is an
 * XSS sink. The fix sanitizes the resolved output.
 */
function renderNewsletterPreview(string $text): string
{
    $request = Mage::app()->getRequest();
    $request->setParam('id', null);
    $request->setParam('type', Mage_Core_Model_Template::TYPE_HTML);
    $request->setParam('text', $text);
    $request->setParam('styles', '');

    return Mage::app()->getLayout()
        ->createBlock('adminhtml/newsletter_template_preview')
        ->toHtml();
}

describe('Mage_Adminhtml_Block_Newsletter_Template_Preview', function () {
    it('resolves a media directive nested in an attribute (broken-image fix)', function () {
        $out = renderNewsletterPreview('<p><img src="{{media url="wysiwyg/logo.png"}}" alt="logo"></p>');

        expect($out)->toContain('media/wysiwyg/logo.png')
            ->and($out)->not->toContain('{{media');
    });

    it('sanitizes markup that was wrapped in a directive (no XSS)', function () {
        $out = renderNewsletterPreview('{{<script>alert(document.cookie)</script>}}');

        expect($out)->not->toContain('<script');
    });
});
