<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Attribute_Data_Image extends Mage_Eav_Model_Attribute_Data_File
{
    private const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_AVIF => 'avif',
    ];

    /**
     * Validate file by attribute validate rules
     * Return array of errors
     *
     * @param array $value
     * @return array
     */
    #[\Override]
    protected function _validateByRules($value)
    {
        $label  = Mage::helper('eav')->__($this->getAttribute()->getStoreLabel());
        $rules  = $this->getAttribute()->getValidateRules();

        $imageProp = @\Maho\Io::getImageSize($value['tmp_name']);

        if (!is_uploaded_file($value['tmp_name']) || !$imageProp) {
            return [
                Mage::helper('eav')->__('"%s" is not a valid file', $label),
            ];
        }

        if (!isset(self::ALLOWED_IMAGE_TYPES[$imageProp[2]])) {
            return [
                Mage::helper('eav')->__('"%s" is not a valid image format', $label),
            ];
        }

        $errors = [];
        if (!empty($rules['max_file_size'])) {
            $size = $value['size'];
            if ($rules['max_file_size'] < $size) {
                $errors[] = Mage::helper('eav')->__('"%s" exceeds the allowed file size.', $label);
            }
        }

        if (!empty($rules['max_image_width'])) {
            if ($rules['max_image_width'] < $imageProp[0]) {
                $r = $rules['max_image_width'];
                $errors[] = Mage::helper('eav')->__('"%s" width exceeds allowed value of %s px.', $label, $r);
            }
        }
        if (!empty($rules['max_image_heght'])) {
            if ($rules['max_image_heght'] < $imageProp[1]) {
                $r = $rules['max_image_heght'];
                $errors[] = Mage::helper('eav')->__('"%s" height exceeds allowed value of %s px.', $label, $r);
            }
        }

        return $errors;
    }

    /**
     * @param array|string $value
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function compactValue($value)
    {
        if (is_array($value)) {
            $value = $this->_setImageTypeExtension($value);
        }

        return parent::compactValue($value);
    }

    /**
     * Give the uploaded file the extension of its real image type.
     */
    protected function _setImageTypeExtension(array $value): array
    {
        if (empty($value['tmp_name']) || !isset($value['name'])) {
            return $value;
        }
        $imageProp = @\Maho\Io::getImageSize($value['tmp_name']);
        if ($imageProp && isset(self::ALLOWED_IMAGE_TYPES[$imageProp[2]])) {
            $value['name'] = pathinfo($value['name'], PATHINFO_FILENAME) . '.' . self::ALLOWED_IMAGE_TYPES[$imageProp[2]];
        }
        return $value;
    }
}
