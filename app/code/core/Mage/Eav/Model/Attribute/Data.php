<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Attribute_Data
{
    public const string OUTPUT_FORMAT_JSON    = 'json';
    public const string OUTPUT_FORMAT_TEXT    = 'text';
    public const string OUTPUT_FORMAT_HTML    = 'html';
    public const string OUTPUT_FORMAT_PDF     = 'pdf';
    public const string OUTPUT_FORMAT_ONELINE = 'oneline';
    public const string OUTPUT_FORMAT_ARRAY   = 'array'; // available only for multiply attributes

    /**
     * Array of attribute data models by input type
     *
     * @var array<string, Mage_Eav_Model_Attribute_Data_Abstract>
     */
    protected static $_dataModels   = [];

    /**
     * Return attribute data model by attribute
     * Set entity to data model (need for work)
     *
     * @return Mage_Eav_Model_Attribute_Data_Abstract
     */
    public static function factory(Mage_Eav_Model_Attribute $attribute, Mage_Core_Model_Abstract $entity)
    {
        $dataModelClass = $attribute->getDataModel();
        if (!empty($dataModelClass)) {
            if (empty(self::$_dataModels[$dataModelClass])) {
                $dataModel = Mage::getModel($dataModelClass);
                self::$_dataModels[$dataModelClass] = $dataModel;
            } else {
                $dataModel = self::$_dataModels[$dataModelClass];
            }
        } else {
            if (empty(self::$_dataModels[$attribute->getFrontendInput()])) {
                $dataModelClass = sprintf('eav/attribute_data_%s', $attribute->getFrontendInput());
                $dataModel      = Mage::getModel($dataModelClass);
                self::$_dataModels[$attribute->getFrontendInput()] = $dataModel;
            } else {
                $dataModel = self::$_dataModels[$attribute->getFrontendInput()];
            }
        }

        if (!$dataModel instanceof Mage_Eav_Model_Attribute_Data_Abstract) {
            throw new \RuntimeException("The attribute {$attribute->getAttributeCode()} has no valid data model.");
        }
        $dataModel->setAttribute($attribute);
        $dataModel->setEntity($entity);

        return $dataModel;
    }
}
