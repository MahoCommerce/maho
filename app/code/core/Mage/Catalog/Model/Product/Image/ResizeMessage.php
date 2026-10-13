<?php

/**
 * Queue message for the resize of product images, handled by
 * Mage_Catalog_Model_Product_Image_ResizeMessageHandler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

final readonly class Mage_Catalog_Model_Product_Image_ResizeMessage
{
    /**
     * @param list<int> $productIds
     */
    public function __construct(
        public array $productIds,
    ) {}
}
