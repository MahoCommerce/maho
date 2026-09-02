<?php

/**
 * Builds the markdown for the blog index and a blog category page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_BlogList extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    public const EXCERPT_LENGTH = 200;

    /** One document per list, so the posts are capped instead of paged */
    public const POSTS_LIMIT = 100;

    #[\Override]
    public function render(): ?string
    {
        $category = $this->getCategory();
        $title = $category === null ? $this->__('Blog') : (string) $category->getName();
        $description = $category === null ? '' : (string) $category->getMetaDescription();

        /** @var Maho_Blog_Model_Resource_Post_Collection $posts */
        $posts = Mage::getResourceModel('blog/post_collection');
        $posts->addVisibleFilter(Mage::app()->getStore())
            ->addAttributeToSelect('*')
            ->setOrder('publish_date', Maho\Db\Select::SQL_DESC)
            ->addAttributeToSort('created_at', Maho\Db\Select::SQL_DESC)
            ->setPageSize(self::POSTS_LIMIT)
            ->setCurPage(1);
        if ($category !== null) {
            $posts->addCategoryFilter($category);
        }

        $helper = Mage::helper('blog');
        $items = [];
        foreach ($posts as $post) {
            $item = '- ' . $this->link((string) $post->getTitle(), $post->getUrl());
            $date = $post->getPublishDate();
            if ($date !== null && $date !== '') {
                $item .= ' (' . Mage::helper('core')->formatDate($date, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM) . ')';
            }
            $excerpt = $this->text($helper->truncateContent($post, self::EXCERPT_LENGTH));
            if ($excerpt !== '') {
                $item .= ': ' . $excerpt;
            }
            $items[] = $item;
        }

        $more = count($items) < self::POSTS_LIMIT ? 0 : $posts->getSize() - count($items);
        if ($more > 0) {
            $items[] = '- ' . $this->__('and %s more posts', $more);
        }

        $sections = [$this->heading($title, $description)];
        $sections[] = $items === [] ? $this->__('There are no posts yet.') : implode("\n", $items);

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        $tags = $this->getCategory()?->getCacheTags() ?: [];
        $tags[] = Maho_Blog_Model_Post::ENTITY;

        return $tags;
    }

    private function getCategory(): ?Maho_Blog_Model_Category
    {
        $category = Mage::registry('current_blog_category');

        return $category instanceof Maho_Blog_Model_Category && $category->getId() ? $category : null;
    }
}
