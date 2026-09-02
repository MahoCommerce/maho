<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * CMS page/block content is persisted, so it is sanitized on save — masking the template
 * directives it contains so the malicious-code filter cannot mangle them, and resolving those
 * directives only at render time.
 */
describe('CMS page content sanitization', function () {
    it('preserves template directives through save', function () {
        $page = Mage::getModel('cms/page');
        $page->setTitle('Directive Page')
            ->setIdentifier('directive-page-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p><p>{{store url="checkout/cart"}}</p>')
            ->save();

        $loaded = Mage::getModel('cms/page')->load($page->getId());

        expect($loaded->getContent())->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($loaded->getContent())->toContain('{{store url="checkout/cart"}}')
            ->and($loaded->getContent())->not->toContain('%7B%7B');

        $page->delete();
    });

    it('strips malicious markup on save', function () {
        $page = Mage::getModel('cms/page');
        $page->setTitle('XSS Page')
            ->setIdentifier('xss-page-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p onclick="alert(1)">hi</p><script>alert(document.cookie)</script>{{a<script>alert(2)</script>}}')
            ->save();

        $loaded = Mage::getModel('cms/page')->load($page->getId());

        expect($loaded->getContent())->not->toContain('<script')
            ->and($loaded->getContent())->not->toContain('onclick');

        $page->delete();
    });

    it('does not force page links into a new tab', function () {
        // Page content is site navigation; linkFilter() belongs to article-style content only.
        $page = Mage::getModel('cms/page');
        $page->setTitle('Link Page')
            ->setIdentifier('link-page-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<a href="/checkout/cart">Cart</a>')
            ->save();

        $loaded = Mage::getModel('cms/page')->load($page->getId());

        expect($loaded->getContent())->not->toContain('target="_blank"');

        $page->delete();
    });

    it('preserves accordion and tabs markup through save', function () {
        // The WYSIWYG accordion is plain <details> markup, and every part of it carries
        // meaning on the storefront: `name` keeps a tab group exclusive, data-style decides
        // accordion or tabs, and `open` (hand-written only) picks the visible panel.
        $content = '<div data-type="maho-accordion" data-style="tabs">'
            . '<details name="maho-accordion-abc123" open><summary>Description</summary>'
            . '<div data-type="detailsContent"><p>First panel</p></div></details>'
            . '<details name="maho-accordion-abc123"><summary>Reviews</summary>'
            . '<div data-type="detailsContent"><p>Second panel</p></div></details>'
            . '</div>';

        $page = Mage::getModel('cms/page');
        $page->setTitle('Accordion Page')
            ->setIdentifier('accordion-page-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent($content)
            ->save();

        $loaded = Mage::getModel('cms/page')->load($page->getId());

        expect($loaded->getContent())->toContain('data-type="maho-accordion"')
            ->and($loaded->getContent())->toContain('data-style="tabs"')
            ->and($loaded->getContent())->toContain('<summary>Description</summary>')
            ->and($loaded->getContent())->toContain('data-type="detailsContent"')
            ->and($loaded->getContent())->toContain('name="maho-accordion-abc123"')
            ->and($loaded->getContent())->toMatch('/<details[^>]* open/');

        $page->delete();
    });
});

describe('CMS block content sanitization', function () {
    it('preserves template directives through save and resolves them on render', function () {
        $identifier = 'directive-block-' . uniqid();
        $block = Mage::getModel('cms/block');
        $block->setTitle('Directive Block')
            ->setIdentifier($identifier)
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p>')
            ->save();

        $loaded = Mage::getModel('cms/block')->load($block->getId());
        expect($loaded->getContent())->toContain('{{media url="wysiwyg/a.webp"}}');

        // Rendering resolves the preserved directive into a real media URL.
        $html = Mage::app()->getLayout()
            ->createBlock('cms/block')
            ->setBlockId($identifier)
            ->toHtml();

        expect($html)->toContain('media/wysiwyg/a.webp')
            ->and($html)->not->toContain('{{media');

        $block->delete();
    });

    it('keeps the icon directive through save and renders it as inline SVG', function () {
        // Inline <svg> is dropped by the sanitizer, so an icon in content can only arrive as a
        // directive that resolves at render time.
        $identifier = 'icon-block-' . uniqid();
        $block = Mage::getModel('cms/block');
        $block->setTitle('Icon Block')
            ->setIdentifier($identifier)
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<p>{{icon name="truck" size="28" class="text-primary"}}<br><strong>Free shipping</strong></p><p>{{icon name="lock" label="Secure"}}</p>')
            ->save();

        $loaded = Mage::getModel('cms/block')->load($block->getId());
        expect($loaded->getContent())->toContain('{{icon name="truck" size="28" class="text-primary"}}');

        $html = Mage::app()->getLayout()
            ->createBlock('cms/block')
            ->setBlockId($identifier)
            ->toHtml();

        expect($html)->toContain('<svg aria-hidden="true" class="icon text-primary" role="none"')
            ->and($html)->toContain('width="28" height="28"')
            ->and($html)->toContain('data-name="truck"')
            ->and($html)->toContain('<svg aria-label="Secure" class="icon" role="img"')
            ->and($html)->not->toContain('{{icon');

        $block->delete();
    });

    it('resolves directives in the widget block without mangling them', function () {
        // Regression: Mage_Cms_Block_Widget_Block used to run the malicious-code filter over the
        // raw content in the admin area, which URL-encoded the directive into a broken image.
        $identifier = 'directive-widget-' . uniqid();
        $block = Mage::getModel('cms/block');
        $block->setTitle('Directive Widget Block')
            ->setIdentifier($identifier)
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p>')
            ->save();

        $html = Mage::app()->getLayout()
            ->createBlock('cms/widget_block')
            ->setTemplate('cms/widget/static_block/default.phtml')
            ->setData('block_id', $block->getId())
            ->toHtml();

        expect($html)->toContain('media/wysiwyg/a.webp')
            ->and($html)->not->toContain('%7B%7B')
            ->and($html)->not->toContain('{{media');

        $block->delete();
    });

    it('strips malicious markup on save', function () {
        $block = Mage::getModel('cms/block');
        $block->setTitle('XSS Block')
            ->setIdentifier('xss-block-' . uniqid())
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<script>alert(document.cookie)</script>{{a<script>alert(2)</script>}}')
            ->save();

        $loaded = Mage::getModel('cms/block')->load($block->getId());

        expect($loaded->getContent())->not->toContain('<script');

        $block->delete();
    });
});
