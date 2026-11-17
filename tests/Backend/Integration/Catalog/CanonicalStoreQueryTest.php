<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Return the href of each canonical link that a block of $blockType adds to the head.
 *
 * @param array<string, mixed> $data
 * @return list<string>
 */
function catalogCanonicalHrefs(string $blockType, array $data = []): array
{
    $layout = Mage::getModel('core/layout');
    /** @var Mage_Page_Block_Html_Head $head */
    $head = $layout->createBlock('page/html_head', 'head');
    $layout->createBlock($blockType, 'view', $data);

    $hrefs = [];
    foreach ($head->getData('items') ?? [] as $item) {
        if ($item['type'] === 'link_rel' && $item['params'] === 'rel="canonical"') {
            $hrefs[] = $item['name'];
        }
    }
    return $hrefs;
}

describe('catalog canonical tag store parameter', function () {
    beforeEach(function () {
        // The sample data puts default-fr on the base URL of the default store view
        $this->useSiblingStoreView = function (string $layout): Mage_Core_Model_Store {
            $other = Mage::app()->getStore('default-fr');
            foreach ([$other->getWebsite()->getDefaultStore(), $other] as $store) {
                $store->setConfig(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL, '0');
            }
            if ($layout === 'own base URL') {
                foreach (['web/unsecure/base_url', 'web/secure/base_url', 'web/unsecure/base_link_url', 'web/secure/base_link_url'] as $path) {
                    $other->setConfig($path, 'https://canonical-catalog.example/');
                }
            }
            $other->setConfig(Mage_Catalog_Helper_Category::XML_PATH_USE_CATEGORY_CANONICAL_TAG, '1');
            $other->setConfig(Mage_Catalog_Helper_Product::XML_PATH_USE_PRODUCT_CANONICAL_TAG, '1');
            Mage::app()->setCurrentStore($other);
            return $other;
        };
    });

    afterEach(function () {
        Mage::unregister('product');
    });

    it('adds the store parameter to the category canonical only on a shared base URL', function (string $layout) {
        $other = ($this->useSiblingStoreView)($layout);
        $query = $layout === 'same base URL' ? '?___store=' . $other->getCode() : '';
        $url = Mage::getBaseUrl() . 'canonical-category.html';
        $category = Mage::getModel('catalog/category')->setData('url', $url);

        expect(catalogCanonicalHrefs('catalog/category_view', ['current_category' => $category]))->toBe([$url . $query]);
    })->with(['same base URL', 'own base URL']);

    it('adds the store parameter to the product canonical only on a shared base URL', function (string $layout) {
        $other = ($this->useSiblingStoreView)($layout);
        $query = $layout === 'same base URL' ? '?___store=' . $other->getCode() : '';
        $url = Mage::getBaseUrl() . 'canonical-product.html';
        Mage::register('product', Mage::getModel('catalog/product')
            ->setStoreId((int) $other->getId())
            ->setData('request_path', 'canonical-product.html')
            ->setData('description', 'Description'));

        expect(catalogCanonicalHrefs('catalog/product_view'))->toBe([$url . $query]);
    })->with(['same base URL', 'own base URL']);
});
