<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Post Model', function () {
    beforeEach(function () {
        $this->post = Mage::getModel('blog/post');
    });

    test('has correct static attributes defined', function () {
        $staticAttributes = $this->post->getStaticAttributes();

        expect($staticAttributes)->toContain('title');
        expect($staticAttributes)->toContain('url_key');
        expect($staticAttributes)->toContain('is_active');
        expect($staticAttributes)->toContain('publish_date');
        expect($staticAttributes)->toContain('content');
        expect($staticAttributes)->toContain('meta_title');
        expect($staticAttributes)->toContain('meta_keywords');
        expect($staticAttributes)->toContain('meta_description');
        expect($staticAttributes)->toContain('meta_robots');
    });

    test('sets created and updated timestamps on save', function () {
        $this->post->setTitle('Timestamp Test');
        $this->post->setContent('Testing timestamps');
        $this->post->save();

        expect($this->post->getCreatedAt())->not()->toBeEmpty();
        expect($this->post->getUpdatedAt())->not()->toBeEmpty();

        // Cleanup
        $this->post->delete();
    });
});
