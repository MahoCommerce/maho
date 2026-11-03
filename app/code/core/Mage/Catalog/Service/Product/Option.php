<?php

/**
 * Load, save and delete the custom options of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;
use Mage_Catalog_Model_Product_Option as Option;

/**
 * A write saves the product with all its options in the format of the admin product save, so the product and the
 * options follow the same code: the option model checks each option, and the product sets has_options and
 * required_options. The write uses the admin scope.
 */
class Mage_Catalog_Service_Product_Option
{
    private const FIELDS = [
        'title', 'type', 'is_require', 'sort_order', 'price', 'price_type', 'sku',
        'max_characters', 'file_extension', 'image_size_x', 'image_size_y',
    ];

    private const VALUE_FIELDS = ['title', 'price', 'price_type', 'sku', 'sort_order'];

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $product has no option $optionId
     */
    public function getById(Product $product, int $optionId): Option
    {
        /** @var Option $option */
        $option = Mage::getResourceModel('catalog/product_option_collection')
            ->addProductToFilter($product->getId())
            ->addIdsToFilter($optionId)
            ->addTitleToResult(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->addPriceToResult(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->getFirstItem();
        if (!$option->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Option not found'));
        }
        return $option;
    }

    /**
     * Save $option, a new option of $product or one from getById(). For a select type, a list of value arrays in the
     * data "values" replaces the values of the option. Without this list, the values stay.
     *
     * @throws Mage_Core_Exception_Input for an invalid title, type or value
     * @throws Mage_Core_Exception for a forbidden file extension
     */
    public function save(Product $product, Option $option): Option
    {
        $product = $this->write($product, $option, false);
        if ($option->getId()) {
            return $this->getById($product, (int) $option->getId());
        }
        // The option of the save is the last one, so the option instance of the product holds its ID
        return $this->getById($product, (int) $product->getOptionInstance()->getId());
    }

    public function delete(Product $product, Option $option): void
    {
        $this->write($product, $option, true);
    }

    private function write(Product $product, Option $target, bool $delete): Product
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product')->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)->load($product->getId());
        $options = [];
        foreach ($product->getProductOptionsCollection() as $option) {
            if ((int) $option->getId() !== (int) $target->getId()) {
                $options[] = $this->toArray($option, array_values($option->getValues() ?? []), null);
            }
        }
        $values = $target->getId() ? array_values($target->getValuesCollection()->getItems()) : [];
        $newValues = $target->getData('values');
        $data = $this->toArray($target, $values, is_array($newValues) ? $newValues : null);
        $data['is_delete'] = $delete ? '1' : '';
        $data['previous_type'] = (string) $target->getOrigData('type');
        $options[] = $data;

        $product->setProductOptions($options);
        $product->setCanSaveCustomOptions(true);
        $product->save();
        return $product;
    }

    /**
     * Return $option in the format of the admin product save.
     *
     * @param list<Mage_Catalog_Model_Product_Option_Value> $values the values that the option has now
     * @param list<array<string, mixed>>|null $newValues the values that replace $values, or null to keep them
     * @return array<string, mixed>
     */
    private function toArray(Option $option, array $values, ?array $newValues): array
    {
        $data = array_intersect_key($option->getData(), array_flip(self::FIELDS));
        $data['option_id'] = (string) ($option->getId() ?: '0');
        $data['is_delete'] = '';
        if ($option->getGroupByType() !== Option::OPTION_GROUP_SELECT) {
            return $data;
        }

        $data['values'] = [];
        foreach ($newValues ?? [] as $value) {
            $data['values'][] = array_intersect_key($value, array_flip(self::VALUE_FIELDS)) + ['option_type_id' => '-1', 'is_delete' => ''];
        }
        foreach ($values as $value) {
            $data['values'][] = $newValues === null
                ? array_intersect_key($value->getData(), array_flip(self::VALUE_FIELDS)) + ['option_type_id' => $value->getId(), 'is_delete' => '']
                : ['option_type_id' => $value->getId(), 'is_delete' => '1'];
        }
        return $data;
    }
}
