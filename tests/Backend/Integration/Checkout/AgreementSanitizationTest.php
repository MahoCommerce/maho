<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The checkout page prints an HTML agreement without escaping it. No filter ran before this
 * change. The checkout ACL is separate from the CMS one, so an admin with only sales rights
 * could put a script tag on every checkout page.
 *
 * With `is_html` off the page escapes the value. The save keeps that value unchanged.
 */
function makeAgreement(string $content, string $checkboxText = 'I agree', int $isHtml = 1): Mage_Checkout_Model_Agreement
{
    return Mage::getModel('checkout/agreement')
        ->setName('Terms ' . uniqid())
        ->setIsActive(1)
        ->setIsHtml($isHtml)
        ->setStores([0])
        ->setContent($content)
        ->setCheckboxText($checkboxText)
        ->save();
}

describe('checkout agreement sanitization', function () {
    it('strips a script tag from html content', function () {
        $agreement = makeAgreement('<p>Terms</p><script>alert(document.cookie)</script>');

        expect($agreement->getContent())->not->toContain('<script');

        $agreement->delete();
    });

    it('strips an event handler from the checkbox text', function () {
        $agreement = makeAgreement('<p>Terms</p>', '<span onclick="alert(1)">I agree</span>');

        expect($agreement->getCheckboxText())->not->toContain('onclick');

        $agreement->delete();
    });

    it('records what it removed so the admin can be told', function () {
        $agreement = makeAgreement('<p>Terms</p><svg viewBox="0 0 24 24"><path d="M4 4"></path></svg>');

        expect($agreement->getData('removed_html'))->toBe(['<svg>']);

        $agreement->delete();
    });

    it('keeps ordinary formatting and links', function () {
        $content = '<h2>Terms</h2><p>Read the <a href="/privacy">privacy policy</a>.</p><ul><li>One</li></ul>';
        $agreement = makeAgreement($content);

        expect($agreement->getContent())->toContain('<h2>Terms</h2>')
            ->and($agreement->getContent())->toContain('href="/privacy"')
            ->and($agreement->getContent())->toContain('<li>One</li>')
            ->and($agreement->getData('removed_html'))->toBe([]);

        $agreement->delete();
    });

    it('opens links in a new tab so checkout is not abandoned', function () {
        $agreement = makeAgreement('<p><a href="/privacy">Privacy</a></p>');

        expect($agreement->getContent())->toContain('target="_blank"')
            ->and($agreement->getContent())->toContain('rel="noopener noreferrer"');

        $agreement->delete();
    });

    it('removes a directive, because nothing resolves one here', function () {
        // The checkout template uses no template processor. A directive would reach the
        // browser as written. Its quotes would then end the HTML attribute around it.
        $agreement = makeAgreement('<p>See {{store url="privacy"}} for details</p>');

        expect($agreement->getContent())->not->toContain('{{store')
            ->and($agreement->getContent())->not->toContain('%7B%7B');

        $agreement->delete();
    });

    it('leaves a plain text agreement exactly as authored', function () {
        // With is_html off the checkout escapes the value. The brackets are text.
        $content = "Use <brackets> freely.\nThey are shown, not parsed.";
        $agreement = makeAgreement($content, 'I agree', 0);

        expect($agreement->getContent())->toBe($content)
            ->and($agreement->getData('removed_html'))->toBeNull();

        $agreement->delete();
    });
});
