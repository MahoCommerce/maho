<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Post Model', function () {
    beforeEach(function () {
        $this->post = Mage::getModel('blog/post');
    });

    test('can create new post instance', function () {
        expect($this->post)->toBeInstanceOf(Maho_Blog_Model_Post::class);
        expect($this->post->getId())->toBeNull();
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

    test('can set and get basic attributes', function () {
        $this->post->setTitle('Test Blog Post');
        $this->post->setContent('This is test content');
        $this->post->setIsActive(1);

        expect($this->post->getTitle())->toBe('Test Blog Post');
        expect($this->post->getContent())->toBe('This is test content');
        expect((int) $this->post->getIsActive())->toBe(1);
    });

    test('can set and get meta attributes', function () {
        $this->post->setMetaTitle('Test Meta Title');
        $this->post->setMetaKeywords('test, blog, post');
        $this->post->setMetaDescription('Test meta description');
        $this->post->setMetaRobots('index,follow');

        expect($this->post->getMetaTitle())->toBe('Test Meta Title');
        expect($this->post->getMetaKeywords())->toBe('test, blog, post');
        expect($this->post->getMetaDescription())->toBe('Test meta description');
        expect($this->post->getMetaRobots())->toBe('index,follow');
    });

    test('can set publish date', function () {
        $publishDate = '2025-01-15';
        $this->post->setPublishDate($publishDate);

        expect($this->post->getPublishDate())->toBe($publishDate);
    });

    test('can handle url key generation', function () {
        $this->post->setTitle('Test Blog Post With Spaces');
        $this->post->setUrlKey('test-blog-post-with-spaces');

        expect($this->post->getUrlKey())->toBe('test-blog-post-with-spaces');
    });

    test('can save and load post', function () {
        $this->post->setTitle('Test Persistence');
        $this->post->setContent('Test content for persistence');
        $this->post->setIsActive(1);
        $this->post->save();

        expect($this->post->getId())->toBeGreaterThan(0);

        $loadedPost = Mage::getModel('blog/post')->load($this->post->getId());
        expect($loadedPost->getTitle())->toBe('Test Persistence');
        expect($loadedPost->getContent())->toBe('Test content for persistence');
        expect((int) $loadedPost->getIsActive())->toBe(1);

        // Cleanup
        $loadedPost->delete();
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
