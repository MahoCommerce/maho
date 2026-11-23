<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2016-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Type
{
    /**
     * Available product types
     */
    public const string TYPE_SIMPLE       = 'simple';
    public const string TYPE_BUNDLE       = 'bundle';
    public const string TYPE_CONFIGURABLE = 'configurable';
    public const string TYPE_GROUPED      = 'grouped';
    public const string TYPE_VIRTUAL      = 'virtual';

    public const string DEFAULT_TYPE      = 'simple';
    public const string DEFAULT_TYPE_MODEL    = 'catalog/product_type_simple';
    public const string DEFAULT_PRICE_MODEL   = 'catalog/product_type_price';

    protected static ?array $_types = null;
    protected static ?array $_compositeTypes = null;
    /** @var array<string, Mage_Catalog_Model_Product_Type_Price>|null */
    protected static ?array $_priceModels = null;
    protected static ?array $_typesPriority = null;

    /**
     * Product type instance factory
     */
    public static function factory(Mage_Catalog_Model_Product $product, bool $singleton = false): Mage_Catalog_Model_Product_Type_Abstract
    {
        $types = self::getTypes();
        $typeId = (string) $product->getTypeId();

        if (!empty($types[$typeId]['model'])) {
            $typeModelName = $types[$typeId]['model'];
        } else {
            $typeModelName = self::DEFAULT_TYPE_MODEL;
            $typeId = self::DEFAULT_TYPE;
        }

        $typeModel = $singleton ? Mage::getSingleton($typeModelName) : Mage::getModel($typeModelName);
        if (!$typeModel instanceof Mage_Catalog_Model_Product_Type_Abstract) {
            throw new \UnexpectedValueException("The model {$typeModelName} of the product type {$typeId} must extend Mage_Catalog_Model_Product_Type_Abstract.");
        }
        if (!$singleton) {
            $typeModel->setProduct($product);
        }
        $typeModel->setConfig($types[$typeId]);
        return $typeModel;
    }

    /**
     * Product type price model factory
     */
    public static function priceFactory(?string $productType): Mage_Catalog_Model_Product_Type_Price
    {
        $productType = (string) $productType;
        if (isset(self::$_priceModels[$productType])) {
            return self::$_priceModels[$productType];
        }

        $types = self::getTypes();

        if (!empty($types[$productType]['price_model'])) {
            $priceModelName = $types[$productType]['price_model'];
        } else {
            $priceModelName = self::DEFAULT_PRICE_MODEL;
        }

        /** @var Mage_Catalog_Model_Product_Type_Price $priceModel */
        $priceModel = Mage::getModel($priceModelName);
        self::$_priceModels[$productType] = $priceModel;
        return $priceModel;
    }

    /**
     * @return array
     */
    public static function getOptionArray()
    {
        $options = [];
        foreach (self::getTypes() as $typeId => $type) {
            $options[$typeId] = Mage::helper('catalog')->__($type['label']);
        }

        return $options;
    }

    public static function toOptionArray(): array
    {
        return self::getOptionArray();
    }

    /**
     * @return array
     */
    public static function getAllOption()
    {
        $options = self::getOptionArray();
        array_unshift($options, ['value' => '', 'label' => '']);
        return $options;
    }

    /**
     * @return array
     */
    public static function getAllOptions()
    {
        $res = [];
        $res[] = ['value' => '', 'label' => ''];
        foreach (self::getOptionArray() as $index => $value) {
            $res[] = [
                'value' => $index,
                'label' => $value,
            ];
        }
        return $res;
    }

    /**
     * @return array
     */
    public static function getOptions()
    {
        $res = [];
        foreach (self::getOptionArray() as $index => $value) {
            $res[] = [
                'value' => $index,
                'label' => $value,
            ];
        }
        return $res;
    }

    /**
     * @param string $optionId
     * @return mixed|null
     */
    public static function getOptionText($optionId)
    {
        $options = self::getOptionArray();
        return $options[$optionId] ?? null;
    }

    /**
     * @return array|string
     */
    public static function getTypes()
    {
        if (is_null(self::$_types)) {
            $productTypes = Mage::getConfig()->getNode('global/catalog/product/type')->asArray();
            foreach ($productTypes as $productKey => $productConfig) {
                $moduleName = $productConfig['@']['module'] ?? 'catalog';
                $translatedLabel = Mage::helper($moduleName)->__($productConfig['label']);
                $productTypes[$productKey]['label'] = $translatedLabel;
            }
            self::$_types = $productTypes;
        }

        return self::$_types;
    }

    /**
     * Return composite product type Ids
     *
     * @return array
     */
    public static function getCompositeTypes()
    {
        if (is_null(self::$_compositeTypes)) {
            self::$_compositeTypes = [];
            $types = self::getTypes();
            foreach ($types as $typeId => $typeInfo) {
                if (array_key_exists('composite', $typeInfo) && $typeInfo['composite']) {
                    self::$_compositeTypes[] = $typeId;
                }
            }
        }
        return self::$_compositeTypes;
    }

    /**
     * Return product types by type indexing priority
     *
     * @return array
     */
    public static function getTypesByPriority()
    {
        if (is_null(self::$_typesPriority)) {
            self::$_typesPriority = [];
            $a = [];
            $b = [];

            $types = self::getTypes();
            foreach ($types as $typeId => $typeInfo) {
                $priority = isset($typeInfo['index_priority']) ? abs((int) $typeInfo['index_priority']) : 0;
                if (!empty($typeInfo['composite'])) {
                    $b[$typeId] = $priority;
                } else {
                    $a[$typeId] = $priority;
                }
            }

            asort($a, SORT_NUMERIC);
            asort($b, SORT_NUMERIC);

            foreach (array_keys($a) as $typeId) {
                self::$_typesPriority[$typeId] = $types[$typeId];
            }
            foreach (array_keys($b) as $typeId) {
                self::$_typesPriority[$typeId] = $types[$typeId];
            }
        }
        return self::$_typesPriority;
    }
}
