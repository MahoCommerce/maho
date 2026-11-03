<?php

/**
 * Load, save and delete the options of a bundle product and their selections.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

use Mage_Bundle_Model_Option as Option;
use Mage_Catalog_Model_Product as Product;

/**
 * A write saves the product with all its options and selections in the format of the admin product save, so the
 * bundle product type checks them and updates the product relations. The write uses the admin scope.
 */
class Mage_Bundle_Service_Option
{
    private const OPTION_FIELDS = ['title', 'type', 'required', 'position'];

    private const SELECTION_FIELDS = [
        'selection_id', 'product_id', 'position', 'is_default', 'selection_price_type',
        'selection_price_value', 'selection_qty', 'selection_can_change_qty',
    ];

    /**
     * @throws Mage_Core_Exception_Input when $optionId is not a positive ID
     * @throws Mage_Core_Exception_NoSuchEntity when $product has no option $optionId
     */
    public function getById(Product $product, int $optionId): Option
    {
        if ($optionId <= 0) {
            throw new Mage_Core_Exception_Input()->addError('option_id', Mage::helper('bundle')->__('optionId is required'));
        }
        /** @var Option $option */
        $option = Mage::getModel('bundle/option')->load($optionId);
        if (!$option->getId() || (int) $option->getParentId() !== (int) $product->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('bundle')->__('Bundle option not found'));
        }
        return $option;
    }

    /**
     * Save $option, a new option of $product or one from getById(), and add the selections $selections to it.
     *
     * @param list<array{product_id: int, selection_qty: float, selection_price_value: float, selection_price_type: int, selection_can_change_qty: int, is_default: int, position: int}> $selections
     * @param list<int>|null $websiteIds with a list, the product of each new selection must be in one of these websites
     * @throws Mage_Core_Exception_Input for an invalid option or selection
     * @throws Mage_Core_Exception_Forbidden for a selection product outside $websiteIds
     */
    public function save(Product $product, Option $option, array $selections = [], ?array $websiteIds = null): Option
    {
        /** @var Mage_Catalog_Service_Product_Link $links */
        $links = Mage::getService('catalog/product_link');
        $links->assertInWebsites(array_column($selections, 'product_id'), $websiteIds);

        $before = $this->getOptionIds($product);
        $this->write($product, $option, $selections, false);
        $optionId = (int) $option->getId() ?: (int) max(array_diff($this->getOptionIds($product), $before) ?: [0]);
        return $this->getById($product, $optionId);
    }

    public function delete(Product $product, Option $option): void
    {
        $this->write($product, $option, [], true);
    }

    /**
     * @param list<array<string, mixed>> $newSelections
     */
    private function write(Product $product, Option $target, array $newSelections, bool $delete): void
    {
        /** @var Product $product */
        $product = Mage::getModel('catalog/product')->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)->load($product->getId());
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $selectionsByOption = [];
        foreach ($read->fetchAll($read->select()
            ->from($resource->getTableName('bundle/selection'), [...self::SELECTION_FIELDS, 'option_id'])
            ->where('parent_product_id = ?', (int) $product->getId())) as $row
        ) {
            $selectionsByOption[(int) $row['option_id']][] = ['delete' => ''] + $row;
        }

        $options = [];
        $selections = [];
        $collection = Mage::getResourceModel('bundle/option_collection')
            ->setProductIdFilter($product->getId())
            ->joinValues(Mage_Core_Model_App::ADMIN_STORE_ID);
        foreach ($collection as $option) {
            if ((int) $option->getId() === (int) $target->getId()) {
                $target->setData('title', $target->getData('title') ?? $option->getTitle());
            } else {
                $options[] = $this->toArray($option);
                $selections[] = $selectionsByOption[(int) $option->getId()] ?? [];
            }
        }

        $targetSelections = $selectionsByOption[(int) $target->getId()] ?? [];
        foreach ($newSelections as $selection) {
            $targetSelections[] = ['selection_id' => '', 'delete' => ''] + array_intersect_key($selection, array_flip(self::SELECTION_FIELDS));
        }
        if ($delete) {
            $targetSelections = array_map(fn(array $selection): array => ['delete' => '1'] + $selection, $targetSelections);
        }
        $options[] = ['delete' => $delete ? '1' : ''] + $this->toArray($target);
        $selections[] = $targetSelections;

        $product->setBundleOptionsData($options);
        $product->setBundleSelectionsData($selections);
        $product->setCanSaveBundleSelections(true);
        $product->save();
    }

    /**
     * @return array<string, mixed> $option in the format of the admin product save
     */
    private function toArray(Option $option): array
    {
        $data = array_intersect_key($option->getData(), array_flip(self::OPTION_FIELDS));
        $data['option_id'] = $option->getId() ? (string) $option->getId() : '';
        $data['delete'] = '';
        return $data;
    }

    /**
     * @return list<int>
     */
    private function getOptionIds(Product $product): array
    {
        return array_map(intval(...), Mage::getResourceModel('bundle/option_collection')->setProductIdFilter($product->getId())->getAllIds());
    }
}
