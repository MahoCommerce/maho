<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * removeTag() used to replace all tags with the string ',', and it never removed the first tag.
 */
describe('Mage_Core_Model_Url_Rewrite::removeTag', function () {
    it('removes a tag and keeps the others', function () {
        $rewrite = Mage::getModel('core/url_rewrite')->setTags(['a', 'b', 'c']);

        $rewrite->removeTag('b');

        expect(array_values($rewrite->getTags()))->toBe(['a', 'c']);
    });

    it('removes the first tag', function () {
        $rewrite = Mage::getModel('core/url_rewrite')->setTags(['a', 'b']);

        $rewrite->removeTag('a');

        expect(array_values($rewrite->getTags()))->toBe(['b']);
    });

    it('removes a key and value tag', function () {
        $rewrite = Mage::getModel('core/url_rewrite')->setTags(['a', 'category=5']);

        $rewrite->removeTag(['category' => '5']);

        expect(array_values($rewrite->getTags()))->toBe(['a']);
    });
});
