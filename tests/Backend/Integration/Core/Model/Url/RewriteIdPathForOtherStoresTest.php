<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The store switch and the hreflang links load the page of the current rewrite in another store view by this ID path.
 */
describe('Mage_Core_Model_Url_Rewrite::getIdPathForOtherStores', function () {
    it('gives the ID path of the category to a custom rewrite of a category', function () {
        $rewrite = Mage::getModel('core/url_rewrite')->setIsSystem(false)->setCategoryId(5)->setIdPath('17630_123456');

        expect($rewrite->getIdPathForOtherStores())->toBe('category/5');
    });

    it('gives the ID path of the product and the category to a custom rewrite of a product in a category', function () {
        $categoryId = (int) Mage::getModel('catalog/category')->getCollection()
            ->addFieldToFilter('level', ['gt' => 1])
            ->setPageSize(1)
            ->getFirstItem()
            ->getId();
        $rewrite = Mage::getModel('core/url_rewrite')->setIsSystem(false)->setProductId(7)->setCategoryId($categoryId)->setIdPath('17630_1');

        expect($rewrite->getIdPathForOtherStores())->toBe('product/7/' . $categoryId);
    });

    it('gives the ID path of the product alone to a custom rewrite of a product in a root category or in no category', function () {
        $rootId = (int) Mage::app()->getDefaultStoreView()->getRootCategoryId();
        $inRoot = Mage::getModel('core/url_rewrite')->setIsSystem(false)->setProductId(7)->setCategoryId($rootId)->setIdPath('17630_2');
        $withoutCategory = Mage::getModel('core/url_rewrite')->setIsSystem(false)->setProductId(7)->setIdPath('17630_3');

        expect($inRoot->getIdPathForOtherStores())->toBe('product/7')
            ->and($withoutCategory->getIdPathForOtherStores())->toBe('product/7');
    });

    it('keeps the ID path of a system rewrite and of a rewrite without a product or a category', function () {
        $system = Mage::getModel('core/url_rewrite')->setIsSystem(true)->setProductId(7)->setIdPath('product/7');
        $custom = Mage::getModel('core/url_rewrite')->setIsSystem(false)->setIdPath('pest-alias');

        expect($system->getIdPathForOtherStores())->toBe('product/7')
            ->and($custom->getIdPathForOtherStores())->toBe('pest-alias');
    });

    it('finds the system rewrite of the category from a custom rewrite that the admin page saved', function () {
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $system = Mage::getModel('core/url_rewrite')->getCollection()
            ->addFieldToFilter('store_id', $storeId)
            ->addFieldToFilter('is_system', 1)
            ->addFieldToFilter('id_path', ['like' => 'category/%'])
            ->setPageSize(1)
            ->getFirstItem();
        $custom = Mage::getService('core/url_rewrite')->newRewrite()
            ->setStoreId($storeId)
            ->setCategoryId((int) $system->getCategoryId())
            ->setRequestPath('pest-other-stores-alias.html');
        Mage::getService('core/url_rewrite')->save($custom);

        try {
            $found = Mage::getModel('core/url_rewrite')->setStoreId($storeId)->loadByIdPath($custom->getIdPathForOtherStores());

            expect($custom->getIdPath())->not->toBe($system->getIdPath())
                ->and((int) $found->getId())->toBe((int) $system->getId());
        } finally {
            $custom->delete();
        }
    });
});
