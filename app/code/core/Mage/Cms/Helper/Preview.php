<?php

/**
 * Builds the settings of the Preview pane of the content editor.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Helper_Preview extends Mage_Core_Helper_Abstract
{
    /** The pages that "Show on" lists for a block. */
    public const USAGE_LIMIT = 20;

    #[\Override]
    protected $_moduleName = 'Mage_Cms';

    /** @var array<int, list<int>> */
    private array $pageStoreIds = [];

    /**
     * The editor settings that turn on the Preview pane for $model, or an empty array when
     * the admin user cannot preview this content or no store view shows it.
     *
     * @return array{preview?: array{url: string, entity: string, stores: list<array{id: int, name: string, website: string}>, storeId: int, usages: list<array{id: int, title: string, stores: list<int>}>, lifetime: int}}
     */
    public function getEditorConfig(string $entity, Mage_Core_Model_Abstract $model): array
    {
        /** @var Mage_Cms_Model_Preview $preview */
        $preview = Mage::getSingleton('cms/preview');
        $resource = $preview->getAclResource($entity);
        if ($preview->getProvider($entity) === null || $resource === null
            || !Mage::getSingleton('admin/session')->isAllowed($resource)
        ) {
            return [];
        }

        $storeId = $this->getStoreId($model);
        if ($storeId === null) {
            return [];
        }

        return ['preview' => [
            'url' => Mage::helper('adminhtml')->getUrl('adminhtml/cms_preview/draft'),
            'entity' => $entity,
            'stores' => $this->getStores($model),
            'storeId' => $storeId,
            'usages' => $model instanceof Mage_Cms_Model_Block && $model->getId() ? $this->getBlockUsages($model) : [],
            'lifetime' => Mage_Cms_Model_Preview::LIFETIME,
        ]];
    }

    /**
     * The store view that the editor shows $model in: the default store view when it shows
     * $model, else the first active one that does. Null when no active store view shows it.
     */
    public function getStoreId(Mage_Core_Model_Abstract $model): ?int
    {
        $ids = array_column($this->getStores($model), 'id');
        $defaultId = (int) Mage::app()->getDefaultStoreView()?->getId();
        return in_array($defaultId, $ids, true) ? $defaultId : ($ids[0] ?? null);
    }

    /**
     * The active store views that show $model: the ones it is assigned to, or every one
     * for an assignment to all store views or for a new record. `stores` comes first: on an
     * EAV entity, such as a blog post, `store_id` is the store view of the load.
     *
     * @return list<array{id: int, name: string, website: string}>
     */
    public function getStores(Mage_Core_Model_Abstract $model): array
    {
        $assigned = array_map(intval(...), (array) ($model->getData('stores') ?? $model->getData('store_id') ?? []));
        $all = $assigned === [] || in_array(0, $assigned, true);

        $stores = [];
        foreach (Mage::app()->getStores() as $store) {
            if (!$store->getIsActive() || (!$all && !in_array((int) $store->getId(), $assigned, true))) {
                continue;
            }
            $stores[] = [
                'id' => (int) $store->getId(),
                'name' => (string) $store->getName(),
                'website' => (string) $store->getWebsite()->getName(),
            ];
        }
        return $stores;
    }

    /**
     * The home page of each store view that shows $block, then the pages whose content names
     * $block by id or by identifier in a {{block}} or a {{widget}} directive. A layout shows a
     * block on every page, so a home page is always a place to see it. A disabled page is in
     * the list too: the preview does not need a page that the storefront shows. `stores` holds
     * the store views that show both the page and $block.
     *
     * @return list<array{id: int, title: string, stores: list<int>}>
     */
    public function getBlockUsages(Mage_Cms_Model_Block $block): array
    {
        $storeIds = array_column($this->getStores($block), 'id');
        $pageIds = [];
        foreach ($storeIds as $storeId) {
            $homeId = $this->getPageIdInStore(
                Mage::helper('cms/page')->getConfigPageIdentifier(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $storeId),
                $storeId,
            );
            if ($homeId !== null) {
                $pageIds[$homeId] = $homeId;
            }
        }
        foreach ($this->getPageIdsThatName($block) as $pageId) {
            $pageIds[$pageId] ??= $pageId;
        }
        if ($pageIds === []) {
            return [];
        }

        $pages = Mage::getResourceModel('cms/page_collection')
            ->addFieldToFilter('page_id', ['in' => array_values($pageIds)]);

        $usages = [];
        foreach ($pageIds as $pageId) {
            $page = $pages->getItemById($pageId);
            $assigned = $this->getPageStoreIds($pageId);
            $stores = in_array(Mage_Core_Model_App::ADMIN_STORE_ID, $assigned, true)
                ? $storeIds
                : array_values(array_intersect($storeIds, $assigned));
            if (!$page || $stores === []) {
                continue;
            }
            $title = (string) $page->getTitle();
            $usages[] = [
                'id' => $pageId,
                'title' => $page->getIsActive() ? $title : $this->__('%s (Disabled)', $title),
                'stores' => $stores,
            ];
        }
        return $usages;
    }

    /**
     * The id of the page with $identifier that store view $storeId shows, active or not. A page
     * of the store view wins over a page of all store views, as on the storefront.
     */
    protected function getPageIdInStore(string $identifier, int $storeId): ?int
    {
        $pages = Mage::getResourceModel('cms/page_collection')
            ->addFieldToFilter('identifier', $identifier)
            ->addStoreFilter($storeId);

        $pageId = null;
        foreach ($pages as $page) {
            $pageId = (int) $page->getId();
            if (in_array($storeId, $this->getPageStoreIds($pageId), true)) {
                break;
            }
        }
        return $pageId;
    }

    /**
     * The store views of page $pageId. Store view 0 means all store views.
     *
     * @return list<int>
     */
    protected function getPageStoreIds(int $pageId): array
    {
        return $this->pageStoreIds[$pageId] ??= array_map(
            intval(...),
            Mage::getResourceSingleton('cms/page')->lookupStoreIds($pageId),
        );
    }

    /**
     * The ids of the pages whose content names $block by id or by identifier, by title.
     *
     * @return list<int>
     */
    protected function getPageIdsThatName(Mage_Cms_Model_Block $block): array
    {
        $texts = ['block_id="' . $block->getId() . '"'];
        $identifier = (string) $block->getIdentifier();
        if ($identifier !== '') {
            $texts[] = 'block_id="' . $identifier . '"';
            $texts[] = '{{block id="' . $identifier . '"';
        }

        $helper = Mage::getResourceHelper('core');
        $pages = Mage::getResourceModel('cms/page_collection')
            ->addFieldToFilter('content', array_map(
                static fn(string $text): array => ['like' => $helper->addLikeEscape($text, ['position' => 'any'])],
                $texts,
            ))
            ->setOrder('title', 'ASC')
            ->setPageSize(self::USAGE_LIMIT);

        return array_map(intval(...), array_values($pages->getColumnValues('page_id')));
    }
}
