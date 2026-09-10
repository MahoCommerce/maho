<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The save sanitizer removes an inline <svg> icon from a static block.
 * Before this change the admin only saw the message "The block has been saved."
 * The resource model now records each removal, and the controller shows a notice.
 */
describe('sanitization notice', function () {
    it('records the elements the sanitizer removed from a block', function () {
        $block = Mage::getModel('cms/block')
            ->setTitle('Icon Block')
            ->setIdentifier('icon-block-' . uniqid())
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<p>Icons</p><svg viewBox="0 0 24 24"><path d="M4 4L20 20"></path></svg>')
            ->save();

        expect($block->getData('removed_html'))->toBe(['<svg>']);

        $block->delete();
    });

    it('records the elements the sanitizer removed from a page', function () {
        $page = Mage::getModel('cms/page')
            ->setTitle('Icon Page')
            ->setIdentifier('icon-page-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p>Icons</p><svg viewBox="0 0 24 24"><circle cx="1" cy="1" r="1"></circle></svg>')
            ->save();

        expect($page->getData('removed_html'))->toContain('<svg>');

        $page->delete();
    });

    it('records nothing for content the sanitizer keeps whole', function () {
        $block = Mage::getModel('cms/block')
            ->setTitle('Plain Block')
            ->setIdentifier('plain-block-' . uniqid())
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<h2>Title</h2><p class="lead">Text with a <a href="/checkout/cart">link</a>.</p>')
            ->save();

        expect($block->getData('removed_html'))->toBe([]);

        $block->delete();
    });

    it('does not write the record to the content column', function () {
        $block = Mage::getModel('cms/block')
            ->setTitle('Reload Block')
            ->setIdentifier('reload-block-' . uniqid())
            ->setIsActive(1)
            ->setStores([0])
            ->setContent('<p>Icons</p><svg viewBox="0 0 24 24"></svg>')
            ->save();

        $loaded = Mage::getModel('cms/block')->load($block->getId());

        expect($loaded->getContent())->toBe('<p>Icons</p>')
            ->and($loaded->getData('removed_html'))->toBeNull();

        $block->delete();
    });
});
