<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Wishlist\Api;

/**
 * Maps wishlist item models to WishlistItem DTOs
 */
final class WishlistItemMapper
{
    public static function mapToDto(\Mage_Wishlist_Model_Item $item, \Mage_Catalog_Model_Product $product): WishlistItem
    {
        $wishlistItem = new WishlistItem();
        $wishlistItem->id = (int) $item->getId();
        $wishlistItem->productId = (int) $product->getId();
        $wishlistItem->productName = $product->getName();
        $wishlistItem->productSku = $product->getSku();
        $wishlistItem->productPrice = (float) $product->getFinalPrice();
        $wishlistItem->productImageUrl = self::getProductImageUrl($product);
        $wishlistItem->productUrl = '/' . ($product->getUrlKey() ?: $product->formatUrlKey($product->getName()));
        $wishlistItem->productType = $product->getTypeId();
        $wishlistItem->qty = (int) ($item->getQty() ?: 1);
        $wishlistItem->description = $item->getDescription();
        $wishlistItem->addedAt = $item->getAddedAt();
        $wishlistItem->inStock = (bool) $product->isInStock();

        return $wishlistItem;
    }

    public static function getProductImageUrl(\Mage_Catalog_Model_Product $product): string
    {
        try {
            return (string) \Mage::helper('catalog/image')
                ->init($product, 'small_image')
                ->resize(300);
        } catch (\Exception $e) {
            return '';
        }
    }
}
