<?php

/**
 * Blog post JSON-LD structured data (schema.org/BlogPosting).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_Jsonld_Article extends Maho_StructuredData_Block_Jsonld_Abstract
{
    protected string $_eventObject = 'article';

    public function getPost(): ?Maho_Blog_Model_Post
    {
        $post = Mage::registry('current_blog_post');
        return $post instanceof Maho_Blog_Model_Post ? $post : null;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function _getEventData(): array
    {
        return ['post' => $this->getPost()];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $post = $this->getPost();
        if (!$post) {
            return [];
        }

        $helper = Mage::helper('structureddata');
        $url = $post->getUrl();

        $data = [
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
            '@type' => 'BlogPosting',
            'headline' => (string) $post->getTitle(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            'url' => $url,
            'publisher' => $helper->getPublisherData(),
        ];

        $description = $this->_getDescription($post);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $image = $post->getImageUrl();
        if ($image) {
            $data['image'] = [$image];
        }

        $published = $this->_formatPublishDate((string) $post->getPublishDate());
        if ($published !== '') {
            $data['datePublished'] = $published;
        }

        $modified = $helper->formatUtcDateTime((string) $post->getData('updated_at'));
        if ($modified !== '') {
            $data['dateModified'] = $modified;
        }

        // Blog posts carry no author field, so attribute authorship to the publisher.
        $data['author'] = ['@type' => 'Organization', 'name' => $helper->getOrganizationName()];

        return $data;
    }

    protected function _getDescription(Maho_Blog_Model_Post $post): string
    {
        $description = (string) ($post->getMetaDescription() ?: $post->getContent());
        return Mage::helper('structureddata')->toPlainText($description);
    }

    /**
     * Blog publish_date is a store-local, date-only column (see Maho_Blog_Model_Resource_Post),
     * so it is emitted verbatim as a plain date with no timezone conversion. Converting it through
     * utcToStore() would shift it by the store offset and could roll it to the wrong calendar day.
     */
    protected function _formatPublishDate(string $value): string
    {
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }

        return substr($value, 0, 10);
    }
}
