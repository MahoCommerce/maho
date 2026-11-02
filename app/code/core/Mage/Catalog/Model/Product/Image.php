<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * @deprecated since 26.11 a product image has resized files for a fixed list of widths, see
 *             Mage_Catalog_Helper_Image for templates and Mage_Core_Model_Image_Resizer for files
 */
class Mage_Catalog_Model_Product_Image extends Mage_Core_Model_Abstract
{
    #[\Deprecated(message: 'no cache holds the sizes of the source images, see Mage_Core_Model_Image_Size', since: '26.11')]
    public const CACHE_TAG = 'catalog_product_image';

    public const CACHE_DIRECTORY = 'catalog/product/' . Mage_Core_Model_Image_Set::CACHE_FOLDER;

    #[\Deprecated(message: 'use Mage_Core_Model_Image_Resizer::refreshAll()', since: '26.11')]
    public function clearCache(): void
    {
        Mage::getSingleton('core/image_resizer')->refreshAll();
    }
}
