<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * publish_date is a store-local date, so the API's "is it live yet" cutoff has to be
 * store-local too. A UTC cutoff hid a post published today from every store ahead of
 * UTC until UTC caught up - for a store on Europe/London that is every post created
 * between 23:00 and 00:00 UTC in summer.
 */

/**
 * Run $fn with the current store's timezone overridden.
 */
function withBlogStoreTimezone(string $timezone, callable $fn): mixed
{
    $store = Mage::app()->getStore();
    $original = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
    $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $timezone);

    try {
        return $fn();
    } finally {
        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $original);
    }
}

function blogStoreToday(): string
{
    return Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
}

function blogPostIsPublished(Maho_Blog_Model_Post $post): bool
{
    return $post->isPublished((int) Mage::app()->getStore()->getId());
}

describe('blog post publish cutoff', function () {
    // UTC+14: its date runs ahead of UTC's for most of the day, which is what broke
    it('treats a post published today in the store timezone as live', function () {
        $published = withBlogStoreTimezone('Pacific/Kiritimati', function () {
            $post = Mage::getModel('blog/post')->setData('publish_date', blogStoreToday());
            return blogPostIsPublished($post);
        });

        expect($published)->toBeTrue();
    });

    it('keeps a future-dated post out of reach', function () {
        $published = withBlogStoreTimezone('Pacific/Kiritimati', function () {
            $tomorrow = Mage::app()->getLocale()->utcToStore()
                ->modify('+1 day')
                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
            $post = Mage::getModel('blog/post')->setData('publish_date', $tomorrow);
            return blogPostIsPublished($post);
        });

        expect($published)->toBeFalse();
    });

    it('treats a post with no publish date as live', function () {
        expect(blogPostIsPublished(Mage::getModel('blog/post')))->toBeTrue();
    });
});
