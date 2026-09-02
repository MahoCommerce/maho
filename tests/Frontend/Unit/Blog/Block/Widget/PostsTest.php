<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function blogWidgetPost(string $title, int $isActive = 1, ?string $publishDate = '2025-01-01', array $categories = []): Maho_Blog_Model_Post
{
    $post = Mage::getModel('blog/post');
    $post->setTitle($title);
    $post->setContent('Widget test content for ' . $title);
    $post->setIsActive($isActive);
    $post->setPublishDate($publishDate);
    $post->setStores([Mage::app()->getStore()->getId()]);
    if ($categories !== []) {
        $post->setData('categories', $categories);
    }
    $post->save();
    return $post;
}

describe('Blog Recent Posts widget block', function () {
    beforeEach(function () {
        $this->block = new Maho_Blog_Block_Widget_Posts();
        $this->posts = [];
        $this->categories = [];
    });

    afterEach(function () {
        foreach ($this->posts as $post) {
            $post->delete();
        }
        foreach ($this->categories as $category) {
            $category->delete();
        }
    });

    it('exposes sensible defaults', function () {
        expect($this->block->getPostsCount())->toBe(3);
        expect($this->block->getCategoryId())->toBeNull();
        expect($this->block->getTitle())->toBe('');
    });

    it('treats an empty category as "all categories"', function () {
        $this->block->setCategoryId('');
        expect($this->block->getCategoryId())->toBeNull();

        $this->block->setCategoryId('4');
        expect($this->block->getCategoryId())->toBe(4);
    });

    it('includes the category and the count in the cache key', function () {
        $this->block->setCategoryId(4)->setPostsCount(7)->setTitle('From the blog');
        $info = $this->block->getCacheKeyInfo();
        expect($info)->toContain(4);
        expect($info)->toContain(7);
        expect($info)->toContain('From the blog');
    });

    it('lists only published posts, newest first', function () {
        $this->posts[] = blogWidgetPost('Widget Old Post', 1, '2024-01-01');
        $this->posts[] = blogWidgetPost('Widget New Post', 1, '2025-01-01');
        $this->posts[] = blogWidgetPost('Widget Inactive Post', 0, '2025-01-02');
        $this->posts[] = blogWidgetPost('Widget Future Post', 1, '2099-01-01');

        $this->block->setPostsCount(500);
        $titles = array_values(array_map(fn($p) => $p->getTitle(), $this->block->getPosts()->getItems()));

        expect($titles)->toContain('Widget Old Post');
        expect($titles)->toContain('Widget New Post');
        expect($titles)->not->toContain('Widget Inactive Post');
        expect($titles)->not->toContain('Widget Future Post');
        expect(array_search('Widget New Post', $titles, true))
            ->toBeLessThan(array_search('Widget Old Post', $titles, true));
    });

    it('caps the list at the configured count', function () {
        $this->posts[] = blogWidgetPost('Widget Count One', 1, '2025-01-01');
        $this->posts[] = blogWidgetPost('Widget Count Two', 1, '2025-01-01');

        $this->block->setPostsCount(1);
        expect(count($this->block->getPosts()->getItems()))->toBe(1);
    });

    it('restricts the list to one category and its descendants', function () {
        $parent = Mage::getModel('blog/category');
        $parent->setName('Widget Parent Category')->setUrlKey('widget-parent-' . uniqid())->setIsActive(1)->setParentId(0);
        $parent->setStores([Mage::app()->getStore()->getId()]);
        $parent->save();
        $this->categories[] = $parent;

        $child = Mage::getModel('blog/category');
        $child->setName('Widget Child Category')->setUrlKey('widget-child-' . uniqid())->setIsActive(1)->setParentId((int) $parent->getId());
        $child->setStores([Mage::app()->getStore()->getId()]);
        $child->save();
        $this->categories[] = $child;

        $this->posts[] = blogWidgetPost('Widget In Child', 1, '2025-01-01', [(int) $child->getId()]);
        $this->posts[] = blogWidgetPost('Widget Outside', 1, '2025-01-01');

        $this->block->setCategoryId((int) $parent->getId())->setPostsCount(50);
        $titles = array_map(fn($p) => $p->getTitle(), $this->block->getPosts()->getItems());
        expect($titles)->toContain('Widget In Child');
        expect($titles)->not->toContain('Widget Outside');
    });

    it('renders an empty collection for a category that does not exist', function () {
        $this->block->setCategoryId(999999999);
        expect($this->block->getPosts()->getSize())->toBe(0);
    });

    it('renders nothing when the blog output is disabled', function () {
        $this->block->setTemplate('blog/widget/posts.phtml');
        $this->block->setLayout(Mage::app()->getLayout());
        Mage::app()->getStore()->setConfig('advanced/modules_disable_output/Maho_Blog', 1);
        try {
            expect($this->block->toHtml())->toBe('');
        } finally {
            Mage::app()->getStore()->setConfig('advanced/modules_disable_output/Maho_Blog', 0);
        }
    });
});

describe('Blog category source model', function () {
    it('offers an "all categories" option first', function () {
        $options = Mage::getModel('blog/source_category')->toOptionArray();
        expect($options[0]['value'])->toBe('');
        expect($options[0]['label'])->toBeString();
    });
});

describe('Blog post collection filters', function () {
    afterEach(function () {
        foreach ($this->posts ?? [] as $post) {
            $post->delete();
        }
    });

    it('hides inactive and future posts behind addPublishedFilter', function () {
        $this->posts = [
            blogWidgetPost('Filter Visible', 1, '2025-01-01'),
            blogWidgetPost('Filter Inactive', 0, '2025-01-01'),
            blogWidgetPost('Filter Future', 1, '2099-01-01'),
            blogWidgetPost('Filter Undated', 1, null),
        ];

        $titles = array_map(
            fn($p) => $p->getTitle(),
            Mage::getResourceModel('blog/post_collection')
                ->addStoreFilter(Mage::app()->getStore())
                ->addPublishedFilter()
                ->getItems(),
        );
        expect($titles)->toContain('Filter Visible');
        expect($titles)->toContain('Filter Undated');
        expect($titles)->not->toContain('Filter Inactive');
        expect($titles)->not->toContain('Filter Future');
    });
});
