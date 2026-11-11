<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Observer
{
    /**
     * Tell the admin the result of each model list refresh that the save of the AI section started.
     */
    #[Maho\Config\Observer('admin_system_config_changed_section_ai', area: 'adminhtml', id: 'ai_model_refresh_messages')]
    public function addModelRefreshMessages(\Maho\Event\Observer $observer): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        foreach (Mage::getSingleton('ai/platform_modelFetcher')->takeRefreshResults() as $provider => $count) {
            if ($count === null) {
                $session->addWarning(Mage::helper('ai')->__('AI: could not refresh models for %s. See exception.log.', $provider));
            } else {
                $session->addSuccess(Mage::helper('ai')->__('AI: refreshed %d models for %s.', $count, $provider));
            }
        }
    }

    /**
     * Auto-queue an embedding task when a product is saved.
     */
    #[Maho\Config\Observer('catalog_product_save_after', id: 'ai_product_embed')]
    public function onProductSave(\Maho\Event\Observer $observer): void
    {
        if (!Mage::getStoreConfigFlag('ai/embed/enabled')) {
            return;
        }
        if (!Mage::getStoreConfigFlag('ai/embed/auto_embed_products')) {
            return;
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product || !$product->getId()) {
            return;
        }

        $text = $this->buildProductText($product);
        if (!$text) {
            return;
        }

        try {
            Mage::helper('ai')->submitEmbedTask([
                'consumer'    => 'catalog_product',
                'text'        => $text,
                'entity_type' => 'product',
                'entity_id'   => (int) $product->getId(),
                'store_id'    => (int) $product->getStoreId(),
            ]);
        } catch (Mage_Core_Exception $e) {
            Mage::log('Maho AI: failed to queue product embed: ' . $e->getMessage(), Mage::LOG_WARNING, 'ai.log');
        }
    }

    /**
     * Auto-queue an embedding task when a category is saved.
     */
    #[Maho\Config\Observer('catalog_category_save_after', id: 'ai_category_embed')]
    public function onCategorySave(\Maho\Event\Observer $observer): void
    {
        if (!Mage::getStoreConfigFlag('ai/embed/enabled')) {
            return;
        }
        if (!Mage::getStoreConfigFlag('ai/embed/auto_embed_categories')) {
            return;
        }

        /** @var Mage_Catalog_Model_Category $category */
        $category = $observer->getEvent()->getCategory();
        if (!$category || !$category->getId()) {
            return;
        }

        $text = $this->buildCategoryText($category);
        if (!$text) {
            return;
        }

        try {
            Mage::helper('ai')->submitEmbedTask([
                'consumer'    => 'catalog_category',
                'text'        => $text,
                'entity_type' => 'category',
                'entity_id'   => (int) $category->getId(),
                'store_id'    => (int) $category->getStoreId(),
            ]);
        } catch (Mage_Core_Exception $e) {
            Mage::log('Maho AI: failed to queue category embed: ' . $e->getMessage(), Mage::LOG_WARNING, 'ai.log');
        }
    }

    private function buildProductText(Mage_Catalog_Model_Product $product): string
    {
        return trim(implode(' ', array_filter([
            (string) $product->getName(),
            strip_tags((string) $product->getShortDescription()),
            strip_tags((string) $product->getDescription()),
        ])));
    }

    private function buildCategoryText(Mage_Catalog_Model_Category $category): string
    {
        return trim(implode(' ', array_filter([
            (string) $category->getName(),
            strip_tags((string) $category->getDescription()),
        ])));
    }
}
