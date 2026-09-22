<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

/**
 * Wishlist block for rendering price of item with product
 *
 * @package    Mage_Wishlist
 */
class Mage_Wishlist_Block_Render_Item_Price extends Mage_Core_Block_Template
{
    /**
     * Returns html for rendering non-configured product
     */
    public function getCleanProductPriceHtml()
    {
        $renderer = $this->getCleanRenderer();
        if (!$renderer) {
            return '';
        }

        $product = $this->getProduct();
        if ($product->canConfigure()) {
            $product = clone $product;
            $product->setCustomOptions([]);
        }

        return $renderer->setProduct($product)
            ->setDisplayMinimalPrice($this->getDisplayMinimalPrice())
            ->setIdSuffix($this->getIdSuffix())
            ->toHtml();
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        return $this->getData('product');
    }

    public function getDisplayMinimalPrice(): ?bool
    {
        $value = $this->getData('display_minimal_price');
        return $value === null ? null : (bool) $value;
    }

    public function getIdSuffix(): ?string
    {
        $value = $this->getData('id_suffix');
        return $value === null ? null : (string) $value;
    }
}
