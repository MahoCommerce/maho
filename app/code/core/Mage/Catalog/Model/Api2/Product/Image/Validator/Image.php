<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Api2_Product_Image_Validator_Image extends Mage_Api2_Model_Resource_Validator
{
    /**
     * Validate data. In case of validation failure return false,
     * getErrors() could be used to retrieve list of validation error messages
     *
     * @return bool
     */
    public function isValidData(array $data)
    {
        if (!isset($data['file_content']) || !isset($data['file_mime_type']) || empty($data['file_content']) ||
            empty($data['file_mime_type'])
        ) {
            $this->_addError('The image is not specified');
        }

        return !count($this->getErrors());
    }
}
