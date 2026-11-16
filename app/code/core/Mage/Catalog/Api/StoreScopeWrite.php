<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

/**
 * Keeps a store view write to the fields of the request.
 *
 * A catalog model loaded for a store view holds the default value of each attribute
 * that the store view inherits. A model save at that store writes every loaded value
 * that is not empty as a store value, so a write of one field would also store all
 * the inherited values. Before the save, this class marks each inherited attribute
 * that the request does not write with useDefaultValue(), as the "Use Default Value"
 * checkboxes of the admin forms do, so the save writes no store value for it.
 */
final class StoreScopeWrite
{
    /**
     * Attribute codes of the fields that the request body sets. A request DTO holds
     * only the body fields; the other properties keep their class default.
     *
     * @param list<string> $extraCodes codes that a field sets under another name
     * @return array<string, true>
     */
    public static function requestedCodes(Product|Category $data, array $extraCodes = []): array
    {
        $codes = array_fill_keys($extraCodes, true);
        foreach ($data::metadata()->fields as $field) {
            $property = new \ReflectionProperty($data, $field->property);
            $default = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
            if ($property->isInitialized($data) && $property->getValue($data) !== $default) {
                $codes[$field->modelField] = true;
            }
        }
        foreach (array_keys($data->customAttributes ?? []) as $code) {
            $codes[(string) $code] = true;
        }
        return $codes;
    }

    /**
     * Mark the inherited attributes that the request does not write as "use the
     * default value". An attribute is left as it is when it has a value in the store
     * view (the admin shows it with "Use Default Value" cleared), when the request
     * names it, or when its value changed after the load.
     *
     * @param array<string, mixed> $loadedData the model data after the load
     * @param array<string, true> $requestedCodes
     */
    public static function keepInheritedValues(\Mage_Catalog_Model_Abstract $model, array $loadedData, array $requestedCodes): void
    {
        $storeId = (int) $model->getStoreId();
        if ($storeId === \Mage_Core_Model_App::ADMIN_STORE_ID) {
            return;
        }
        $resource = $model->getResource();
        if (!$resource instanceof \Mage_Catalog_Model_Resource_Abstract) {
            return;
        }

        /** @var array<string, \Mage_Catalog_Model_Resource_Eav_Attribute> $candidates */
        $candidates = [];
        foreach ($resource->getAttributesByCode() as $code => $attribute) {
            $code = (string) $code;
            if (!$attribute instanceof \Mage_Catalog_Model_Resource_Eav_Attribute
                || $attribute->isScopeGlobal()
                || $attribute->getBackend()->isStatic()
                || $model->getExistsStoreValueFlag($code)
                || isset($requestedCodes[$code])
            ) {
                continue;
            }
            $value = $model->getData($code);
            if ($value !== ($loadedData[$code] ?? null)) {
                continue;
            }
            // Most array values are not EAV value rows: the tier and group price
            // backends save them to their own tables, and read `false` as "delete
            // all the rows of the website". The sort-by backend is different: it
            // loads a value row as an array and writes it back as a string.
            if (is_array($value) && !$attribute->getBackend() instanceof \Mage_Catalog_Model_Category_Attribute_Backend_Sortby) {
                continue;
            }
            $candidates[$code] = $attribute;
        }

        $websiteValues = self::otherWebsiteStoreValues($model, $storeId, array_filter(
            $candidates,
            static fn(\Mage_Catalog_Model_Resource_Eav_Attribute $attribute): bool => $attribute->isScopeWebsite(),
        ));

        foreach (array_keys($candidates) as $code) {
            if (array_key_exists($code, $websiteValues)) {
                // "Use the default value" on a website attribute deletes the rows of all
                // the store views of the website. Another store view of the website has a
                // value, so the save writes that website value instead.
                $model->setData($code, $websiteValues[$code]);
                continue;
            }
            $model->useDefaultValue($code);
        }
    }

    /**
     * Values that the other store views of the same website have for website-scope
     * attributes, keyed by attribute code (the first store view wins).
     *
     * @param array<string, \Mage_Catalog_Model_Resource_Eav_Attribute> $attributes
     * @return array<string, mixed>
     */
    private static function otherWebsiteStoreValues(\Mage_Catalog_Model_Abstract $model, int $storeId, array $attributes): array
    {
        if ($attributes === []) {
            return [];
        }
        $storeIds = array_values(array_diff(
            array_map(intval(...), $model->getWebsiteStoreIds()),
            [$storeId, \Mage_Core_Model_App::ADMIN_STORE_ID],
        ));
        if ($storeIds === []) {
            return [];
        }

        $byTable = [];
        foreach ($attributes as $code => $attribute) {
            $byTable[$attribute->getBackend()->getTable()][(int) $attribute->getId()] = $code;
        }

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $values = [];
        foreach ($byTable as $table => $codesById) {
            $select = $adapter->select()
                ->from($table, ['attribute_id', 'value'])
                ->where('entity_id = ?', (int) $model->getId())
                ->where('attribute_id IN (?)', array_keys($codesById))
                ->where('store_id IN (?)', $storeIds)
                ->order('store_id ASC');
            foreach ($adapter->fetchAll($select) as $row) {
                $code = $codesById[(int) $row['attribute_id']];
                if (!array_key_exists($code, $values)) {
                    $values[$code] = $row['value'];
                }
            }
        }

        return $values;
    }
}
