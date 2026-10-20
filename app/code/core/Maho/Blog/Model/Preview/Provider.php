<?php

/**
 * Live preview of a blog post.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Preview_Provider extends Mage_Cms_Model_Preview_Provider_Abstract
{
    public const FIELDS = [
        'title', 'content', 'short_content', 'publish_date',
        'meta_title', 'meta_description', 'meta_keywords', 'meta_robots',
    ];

    #[\Override]
    public function prepare(array $post, int $storeId): array
    {
        $data = $this->pick($post, self::FIELDS);
        $data = $this->filterDates($data, ['publish_date']);
        $data = $this->sanitize(
            $data,
            ['content', 'short_content'],
            Mage::helper('cms')->getPageTemplateProcessor(),
            applyLinkFilter: true,
        );

        return ['data' => $data, 'warnings' => []];
    }

    /**
     * The draft goes over the saved post, so the fields that the form does not post,
     * such as the image, stay as they are.
     */
    #[\Override]
    public function render(Mage_Core_Controller_Front_Action $action, array $draft): bool
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post');
        if ($draft['entity_id'] !== null) {
            $post->load($draft['entity_id']);
        }
        $post->addData($draft['data']);
        Mage::register('current_blog_post', $post);

        $action->loadLayout(['default', 'blog_index_view']);
        $head = $action->getLayout()->getBlock('head');
        if ($head) {
            $head->setTitle((string) ($post->getMetaTitle() ?: $post->getTitle()));
            if ($post->getMetaDescription()) {
                $head->setDescription((string) $post->getMetaDescription());
            }
            if ($post->getMetaKeywords()) {
                $head->setKeywords((string) $post->getMetaKeywords());
            }
        }
        $action->getLayout()->getBlock('title')?->setTitle((string) $post->getTitle());
        $action->renderLayout();

        return true;
    }

    #[\Override]
    public function isTarget(Mage_Core_Block_Abstract $block, array $draft): bool
    {
        return $block instanceof Maho_Blog_Block_Post_View;
    }
}
