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
    public function prepare(array $post): array
    {
        $data = $this->pick($post, self::FIELDS);
        $data = Mage::helper('core')->filterDates($data, ['publish_date']);
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
        } else {
            $post->setData('category_ids', []);
        }
        $post->addData($draft['data']);
        Mage::helper('blog')->renderPost($action, $post);

        return true;
    }

    #[\Override]
    public function isTarget(Mage_Core_Block_Abstract $block, array $draft): bool
    {
        return $block instanceof Maho_Blog_Block_Post_View;
    }
}
