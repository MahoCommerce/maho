<?php

/**
 * Builds the markdown for a blog post.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_BlogPost extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    #[\Override]
    public function render(): ?string
    {
        $post = $this->getPost();
        if ($post === null) {
            return null;
        }

        $facts = [];
        $date = $post->getPublishDate();
        if ($date !== null && $date !== '') {
            $facts[] = '- ' . $this->__('Published') . ': ' . Mage::helper('core')->formatDate($date, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
        }
        if ($post->hasImage()) {
            $facts[] = '- ' . $this->__('Image') . ': ' . $post->getImageUrl();
        }
        $facts[] = '- URL: ' . $post->getUrl();

        $sections = [$this->heading((string) $post->getTitle()), implode("\n", $facts)];
        $body = $this->toMarkdown($post->getFilteredContent());
        if ($body !== '') {
            $sections[] = $body;
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        return $this->getPost()?->getCacheTags() ?: [];
    }

    private function getPost(): ?Maho_Blog_Model_Post
    {
        $post = Mage::registry('current_blog_post');

        return $post instanceof Maho_Blog_Model_Post && $post->getId() ? $post : null;
    }
}
