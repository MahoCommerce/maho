<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Observer
{
    /**
     * Auto-queue an embedding task when a product is saved.
     */
    #[Maho\Config\Observer('catalog_product_save_after', id: 'maho_ai_product_embed')]
    public function onProductSave(\Maho\Event\Observer $observer): void
    {
        if (!Mage::getStoreConfigFlag('maho_ai/embed/enabled')) {
            return;
        }
        if (!Mage::getStoreConfigFlag('maho_ai/embed/auto_embed_products')) {
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
            Mage::log('Maho AI: failed to queue product embed: ' . $e->getMessage(), Mage::LOG_WARNING, 'maho_ai.log');
        }
    }

    /**
     * Auto-queue an embedding task when a category is saved.
     */
    #[Maho\Config\Observer('catalog_category_save_after', id: 'maho_ai_category_embed')]
    public function onCategorySave(\Maho\Event\Observer $observer): void
    {
        if (!Mage::getStoreConfigFlag('maho_ai/embed/enabled')) {
            return;
        }
        if (!Mage::getStoreConfigFlag('maho_ai/embed/auto_embed_categories')) {
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
            Mage::log('Maho AI: failed to queue category embed: ' . $e->getMessage(), Mage::LOG_WARNING, 'maho_ai.log');
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
