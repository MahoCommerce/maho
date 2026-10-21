<?php

/**
 * Live preview of a CMS block, alone or on a page that shows it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Model_Preview_Provider_Block extends Mage_Cms_Model_Preview_Provider_Abstract
{
    public const FIELDS = ['title', 'identifier', 'content'];

    /** @var array<int, string> */
    private array $savedIdentifiers = [];

    #[\Override]
    public function prepare(array $post): array
    {
        $data = $this->pick($post, self::FIELDS);
        $data = $this->sanitize($data, ['content'], Mage::helper('cms')->getBlockTemplateProcessor());

        return ['data' => $data, 'warnings' => []];
    }

    /**
     * Without a context page, the block shows alone in the store layout. With one, that
     * saved page renders, and the observer puts the draft in place of the block. The context
     * page can be disabled, but it must belong to the store view of the draft.
     */
    #[\Override]
    public function render(Mage_Core_Controller_Front_Action $action, array $draft): bool
    {
        if ($draft['context_page_id'] !== null) {
            // A load without a store view does not filter by store view or by status
            $page = Mage::getSingleton('cms/page')->load($draft['context_page_id']);
            $stores = array_map(intval(...), (array) $page->getStoreId());
            if (!$page->getId() || !array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $draft['store_id']], $stores)) {
                return false;
            }
            return Mage::helper('cms/page')->renderPageModel($action, $page);
        }

        $action->loadLayout(['default', 'cms_preview_block']);
        $layout = $action->getLayout();
        $layout->getBlock('head')?->setTitle((string) ($draft['data']['title'] ?? ''));
        // The observer fills this block with the draft, as it does on a context page
        $layout->getBlock('content')?->append($layout->createBlock('core/text', 'cms_preview_block'));
        $action->renderLayout();

        return true;
    }

    /**
     * The action of the context page. A block alone has no storefront page.
     */
    #[\Override]
    public function getStorefrontAction(array $draft): ?string
    {
        if ($draft['context_page_id'] === null) {
            return null;
        }
        $identifier = (string) Mage::getModel('cms/page')->load($draft['context_page_id'])->getIdentifier();
        return $this->getCmsPageAction($identifier, $draft['store_id']);
    }

    /**
     * A block matches by id or by identifier: a layout or a {{block}} directive can name
     * it either way. The saved identifier counts too, since the draft can change it.
     */
    #[\Override]
    public function isTarget(Mage_Core_Block_Abstract $block, array $draft): bool
    {
        if ($block->getNameInLayout() === 'cms_preview_block') {
            return true;
        }
        if (!$block instanceof Mage_Cms_Block_Block && !$block instanceof Mage_Cms_Block_Widget_Block) {
            return false;
        }
        $blockId = (string) $block->getBlockId();
        if ($blockId === '') {
            return false;
        }

        $names = [(string) ($draft['data']['identifier'] ?? '')];
        if ($draft['entity_id'] !== null) {
            $names[] = (string) $draft['entity_id'];
            $names[] = $this->savedIdentifier($draft['entity_id']);
        }

        return in_array($blockId, array_filter($names, static fn(string $name): bool => $name !== ''), true);
    }

    /**
     * A static block widget keeps the wrapper of its template around the draft.
     */
    #[\Override]
    public function getTargetHtml(Mage_Core_Block_Abstract $block, string $html, array $draft): string
    {
        $content = (string) Mage::helper('cms')->getBlockTemplateProcessor()
            ->filter((string) ($draft['data']['content'] ?? ''));

        return $block instanceof Mage_Cms_Block_Widget_Block && $block->getTemplate()
            ? $block->setText($content)->renderView()
            : $content;
    }

    private function savedIdentifier(int $blockId): string
    {
        return $this->savedIdentifiers[$blockId] ??= (string) Mage::getModel('cms/block')->load($blockId)->getIdentifier();
    }
}
