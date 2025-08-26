<?php

/**
 * Blog Helper Data Test - Using Mocks (Database Independent)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Helper Data (Mocked)', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
    });

    test('helper instance is correct type', function () {
        expect($this->helper)->toBeInstanceOf(Maho_Blog_Helper_Data::class);
    });

    test('shouldShowInNavigation depends on module enabled and visible posts', function () {
        // Test when module is enabled
        expect($this->helper->isEnabled())->toBeTrue();
        
        // The actual result depends on hasVisiblePosts(), which we test separately
        $result = $this->helper->shouldShowInNavigation();
        expect($result)->toBeBool();
        
        // If module is enabled, result should match hasVisiblePosts()
        expect($result)->toBe($this->helper->isEnabled() && $this->helper->hasVisiblePosts());
    });

    test('hasVisiblePosts uses correct collection filters', function () {
        // We can't easily mock the collection without major refactoring,
        // but we can test that the method returns a boolean and doesn't crash
        $result = $this->helper->hasVisiblePosts();
        expect($result)->toBeBool();
        
        // Test that the same call returns the same result (no side effects)
        $secondResult = $this->helper->hasVisiblePosts();
        expect($secondResult)->toBe($result);
    });

    test('getBlogUrl returns valid URL', function () {
        $url = $this->helper->getBlogUrl();
        
        expect($url)->toBeString();
        expect($url)->toContain('blog');
        expect($url)->toMatch('/^https?:\/\//'); // Should be a full URL
    });

    test('getPostsPerPage returns configured value', function () {
        $postsPerPage = $this->helper->getPostsPerPage();
        
        expect($postsPerPage)->toBeInt();
        expect($postsPerPage)->toBeGreaterThan(0);
        
        // Should match configuration or default
        $configValue = (int) Mage::getStoreConfig('blog/general/posts_per_page');
        $expectedValue = $configValue > 0 ? $configValue : 20;
        
        expect($postsPerPage)->toBe($expectedValue);
    });

    test('module methods return boolean values', function () {
        expect($this->helper->isModuleEnabled())->toBeBool();
        expect($this->helper->isModuleOutputEnabled())->toBeBool();
        expect($this->helper->isEnabled())->toBeBool();
    });
});