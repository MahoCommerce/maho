<?php

/**
 * Live preview of a CMS page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Model_Preview_Provider_Page extends Mage_Cms_Model_Preview_Provider_Abstract
{
    /** The fields of the page form that change what the storefront shows. */
    public const FIELDS = [
        'title', 'identifier', 'content_heading', 'content',
        'root_template', 'layout_update_xml',
        'custom_theme', 'custom_theme_from', 'custom_theme_to',
        'custom_root_template', 'custom_layout_update_xml',
        'meta_keywords', 'meta_description', 'meta_robots',
    ];

    #[\Override]
    public function prepare(array $post): array
    {
        $warnings = [];
        $data = $this->pick($post, self::FIELDS);
        $data = Mage::helper('core')->filterDates($data, ['custom_theme_from', 'custom_theme_to']);
        $data = $this->validateLayoutUpdates($data, ['layout_update_xml', 'custom_layout_update_xml'], $warnings);
        $data = $this->sanitize($data, ['content'], Mage::helper('cms')->getPageTemplateProcessor());

        return ['data' => $data, 'warnings' => $warnings];
    }

    /**
     * The page block, the head robots and the breadcrumbs read the page singleton, so
     * the draft takes its place for this request.
     */
    #[\Override]
    public function render(Mage_Core_Controller_Front_Action $action, array $draft): bool
    {
        $page = Mage::getSingleton('cms/page');
        $page->setData($draft['data'])
            ->setId($draft['entity_id'])
            ->setStoreId($draft['store_id']);

        return Mage::helper('cms/page')->renderPageModel($action, $page);
    }

    #[\Override]
    public function getStorefrontAction(array $draft): ?string
    {
        return $this->getCmsPageAction((string) ($draft['data']['identifier'] ?? ''), $draft['store_id']);
    }

    #[\Override]
    public function isTarget(Mage_Core_Block_Abstract $block, array $draft): bool
    {
        return $block instanceof Mage_Cms_Block_Page && $block->getNameInLayout() === 'cms_page';
    }
}
