<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Image_WarmMessageHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(Mage_Catalog_Model_Product_Image_WarmMessage $message): void
    {
        Mage::getSingleton('catalog/product_image_warmer')->warmProducts($message->productIds);
    }
}
