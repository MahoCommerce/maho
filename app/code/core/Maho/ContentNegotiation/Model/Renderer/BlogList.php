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

    #[\Override]
    public function render(): ?string
    {
        $layout = Mage::app()->getLayout();
        $block = $layout->getBlock('blog.post.list') ?: $layout->getBlock('blog.category.view');
        if (!$block instanceof Maho_Blog_Block_Post_List && !$block instanceof Maho_Blog_Block_Category_View) {
            return null;
        }

        $category = Mage::registry('current_blog_category');
        $title = $category instanceof Maho_Blog_Model_Category ? (string) $category->getName() : $this->__('Blog');

        $helper = Mage::helper('blog');
        $items = [];
        foreach ($block->getPosts() as $post) {
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

        $sections = [$this->heading($title)];
        $sections[] = $items === [] ? $this->__('There are no posts yet.') : implode("\n", $items);

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        return [Maho_Blog_Model_Post::ENTITY];
    }
}
