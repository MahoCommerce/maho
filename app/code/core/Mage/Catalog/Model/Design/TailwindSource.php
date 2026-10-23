<?php

/**
 * The HTML of the WYSIWYG text attributes of the products and the categories, for the Tailwind build.
 *
 * The codes of these attributes come from the database, so this class queues the build from
 * its own observers instead of from the model and fields of the attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Design_TailwindSource
{
    /** @var array<string, array<int, string>> attribute code by attribute id, by entity type */
    protected array $attributes = [];

    protected bool $updatesContent = false;

    /**
     * @return Generator<int, string>
     */
    #[Maho\Config\TailwindSource('catalog_product')]
    public function getProductContent(): Generator
    {
        yield from $this->readValues(Mage_Catalog_Model_Product::ENTITY, 'catalog/product');
    }

    /**
     * @return Generator<int, string>
     */
    #[Maho\Config\TailwindSource('catalog_category')]
    public function getCategoryContent(): Generator
    {
        yield from $this->readValues(Mage_Catalog_Model_Category::ENTITY, 'catalog/category');
    }

    #[Maho\Config\Observer('catalog_product_save_commit_after', type: 'singleton', id: 'tailwind_source_check_product')]
    #[Maho\Config\Observer('catalog_category_save_commit_after', type: 'singleton', id: 'tailwind_source_check_category')]
    public function checkSavedEntity(\Maho\Event\Observer $observer): void
    {
        $object = $observer->getEvent()->getData('data_object');
        $entityType = match (true) {
            $object instanceof Mage_Catalog_Model_Product => Mage_Catalog_Model_Product::ENTITY,
            $object instanceof Mage_Catalog_Model_Category => Mage_Catalog_Model_Category::ENTITY,
            default => null,
        };
        if ($entityType === null) {
            return;
        }

        $changed = array_any($this->getAttributes($entityType), fn(string $code): bool => $object->dataHasChangedFor($code));
        if ($changed) {
            Mage::getSingleton('core/design_content_bundle')->queueBuild();
        }
    }

    /**
     * Record if the admin "Update Attributes" action writes a WYSIWYG attribute.
     */
    #[Maho\Config\Observer('catalog_product_attribute_update_before', type: 'singleton', id: 'tailwind_source_check_product_mass')]
    public function checkUpdatedAttributes(\Maho\Event\Observer $observer): void
    {
        $data = (array) $observer->getEvent()->getData('attributes_data');
        $this->updatesContent = array_intersect(array_keys($data), $this->getAttributes(Mage_Catalog_Model_Product::ENTITY)) !== [];
    }

    /**
     * Queue the build after the "Update Attributes" action wrote the values, so the build reads them.
     */
    #[Maho\Config\Observer('catalog_product_attribute_update_after', type: 'singleton', id: 'tailwind_source_queue_product_mass')]
    public function queueUpdatedAttributes(): void
    {
        if ($this->updatesContent) {
            $this->updatesContent = false;
            Mage::getSingleton('core/design_content_bundle')->queueBuild();
        }
    }

    /**
     * The text attributes of $entityType that have the WYSIWYG editor.
     *
     * @return array<int, string> attribute code by attribute id
     */
    public function getAttributes(string $entityType): array
    {
        if (isset($this->attributes[$entityType])) {
            return $this->attributes[$entityType];
        }

        $collection = Mage::getResourceModel($entityType === Mage_Catalog_Model_Product::ENTITY
            ? 'catalog/product_attribute_collection'
            : 'catalog/category_attribute_collection')
            ->addFieldToFilter('backend_type', 'text')
            ->addFieldToFilter('is_wysiwyg_enabled', 1);

        $attributes = [];
        foreach ($collection as $attribute) {
            $attributes[(int) $attribute->getId()] = (string) $attribute->getAttributeCode();
        }
        return $this->attributes[$entityType] = $attributes;
    }

    /**
     * @return Generator<int, string>
     */
    protected function readValues(string $entityType, string $entityTable): Generator
    {
        $attributeIds = array_keys($this->getAttributes($entityType));
        if ($attributeIds === []) {
            return;
        }

        $resource = Mage::getResourceSingleton('core/design_content');
        yield from $resource->readColumns($resource->getTable([$entityTable, 'text']), 'value_id', ['value'], ['attribute_id' => $attributeIds]);
    }
}
