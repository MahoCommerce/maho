<?php

/**
 * Queue message for the warm-up of product images, handled by
 * Mage_Catalog_Model_Product_Image_WarmMessageHandler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

final readonly class Mage_Catalog_Model_Product_Image_WarmMessage
{
    /**
     * @param list<int> $productIds
     */
    public function __construct(
        public array $productIds,
    ) {}
}
