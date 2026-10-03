<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_ResizeMessageHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Catalog_Model_Product_Image_ResizeMessage $message): void
    {
        Mage::getSingleton('catalog/product_image_resizer')->resizeProducts($message->productIds);
    }
}
