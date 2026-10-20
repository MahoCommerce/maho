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

    /**
     * The editor settings that turn on the Preview pane for $model, or an empty array when
     * the admin user cannot preview this content or no store view shows it.
     *
     * @return array{preview?: array{url: string, entity: string, stores: list<array{id: int, name: string, website: string}>, storeId: int, usages: list<array{id: int, title: string}>}}
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
     * for an assignment to all store views or for a new record.
     *
     * @return list<array{id: int, name: string, website: string}>
     */
    public function getStores(Mage_Core_Model_Abstract $model): array
    {
        $assigned = array_map(intval(...), (array) ($model->getData('store_id') ?? $model->getData('stores') ?? []));
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
     * The home page, then the pages whose content names $block by id or by identifier in a
     * {{block}} or a {{widget}} directive. A layout shows a block on every page, so the
     * home page is always a place to see it.
     *
     * @return list<array{id: int, title: string}>
     */
    public function getBlockUsages(Mage_Cms_Model_Block $block): array
    {
        $usages = [];
        $home = Mage::getModel('cms/page')->load(
            Mage::helper('cms/page')->getConfigPageIdentifier(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE),
            'identifier',
        );
        if ($home->getId()) {
            $usages[(int) $home->getId()] = ['id' => (int) $home->getId(), 'title' => (string) $home->getTitle()];
        }

        $patterns = ['%block_id="' . $block->getId() . '"%'];
        $identifier = (string) $block->getIdentifier();
        if ($identifier !== '') {
            $patterns[] = '%block_id="' . $identifier . '"%';
            $patterns[] = '%id="' . $identifier . '"%';
        }

        $pages = Mage::getResourceModel('cms/page_collection')
            ->addFieldToFilter('content', array_map(static fn(string $pattern): array => ['like' => $pattern], $patterns))
            ->setOrder('title', 'ASC')
            ->setPageSize(self::USAGE_LIMIT);

        foreach ($pages as $page) {
            $usages[(int) $page->getId()] = ['id' => (int) $page->getId(), 'title' => (string) $page->getTitle()];
        }
        return array_values($usages);
    }
}
