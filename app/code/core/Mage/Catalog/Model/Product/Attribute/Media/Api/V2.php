<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Media_Api_V2 extends Mage_Catalog_Model_Product_Attribute_Media_Api
{
    /**
     * Prepare data to create or update image
     *
     * @param stdClass $data
     * @return array
     */
    #[\Override]
    protected function _prepareImageData($data)
    {
        if (!is_object($data)) {
            return parent::_prepareImageData($data);
        }
        $_imageData = get_object_vars($data);
        if (isset($data->file) && is_object($data->file)) {
            $_imageData['file'] = get_object_vars($data->file);
        }
        return parent::_prepareImageData($_imageData);
    }
}
