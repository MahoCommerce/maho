<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Layered Filter Provider, uses Maho's built-in catalog layer to build facets.
 */
final class LayeredFilterProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return TraversablePaginator<LayeredFilter>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        StoreContext::ensureStore();

        $args = array_merge($context['filters'] ?? [], $context['args'] ?? []);
        $categoryId = (int) ($args['categoryId'] ?? 0);

        if ($categoryId === 0) {
            return new TraversablePaginator(new \ArrayIterator([]), 1, 100, 0);
        }

        $storeId = StoreContext::getStoreId();
        // Facet counts vary by customer group: the catalog layer joins the
        // group-scoped price index (Mage_Catalog_Model_Layer::addPriceData),
        // which is why core keys its own layer state by _CUSTGROUP_ too.
        $groupId = $this->getCustomerGroupId();
        $cacheKey = "api_layered_filters_{$categoryId}_{$storeId}_{$groupId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = \Mage::helper('core')->jsonDecode($cached, true);
            if (is_array($data)) {
                $filters = array_map(fn(array $f) => $this->arrayToDto($f), $data);
                return new TraversablePaginator(new \ArrayIterator($filters), 1, 100, count($filters));
            }
        }

        $filters = $this->buildFilters($categoryId);

        if (!empty($filters)) {
            $cacheData = array_map(fn(LayeredFilter $f) => $this->dtoToArray($f), $filters);
            \Mage::app()->getCache()->save(
                \Mage::helper('core')->jsonEncode($cacheData),
                $cacheKey,
                ['API_LAYERED_FILTERS', 'API_PRODUCTS'],
                \Maho_ApiPlatform_Model_Observer::getCacheTtl(),
            );
        }

        return new TraversablePaginator(new \ArrayIterator($filters), 1, 100, count($filters));
    }

    /**
     * Build layered filter DTOs using Maho's catalog layer
     *
     * @return LayeredFilter[]
     */
    private function buildFilters(int $categoryId): array
    {
        $category = \Mage::getModel('catalog/category')->load($categoryId);
        if (!$category->getId()) {
            return [];
        }

        // Fresh instance, not the singleton: under FPM workers and the test
        // runner the singleton retains setCurrentCategory() state across
        // requests, which would leak the wrong category into facet counts
        // (mirrors ProductProvider).
        /** @var \Mage_Catalog_Model_Layer $layer */
        $layer = \Mage::getModel('catalog/layer');
        $layer->setCurrentCategory($category);

        $filterableAttributes = $layer->getFilterableAttributes();

        // Pre-load all swatch data in one query for all filterable select/multiselect attributes
        $swatchMap = $this->loadSwatchMap($filterableAttributes);

        $filters = [];

        foreach ($filterableAttributes as $attribute) {
            /** @var \Mage_Catalog_Model_Layer_Filter_Attribute $filterModel */
            $filterModel = \Mage::getModel('catalog/layer_filter_attribute');
            $filterModel->setLayer($layer);
            $filterModel->setAttributeModel($attribute);

            $dto = new LayeredFilter();
            $dto->code = $attribute->getAttributeCode();
            $dto->label = $attribute->getStoreLabel() ?: $attribute->getFrontendLabel();
            $dto->type = $attribute->getFrontendInput();
            $dto->position = (int) $attribute->getPosition();

            $items = $filterModel->getItems();
            foreach ($items as $item) {
                // Build a plain array shape (not a FilterOption DTO) - the
                // $dto->options property is typed as array<int, array<string, mixed>>
                // so GraphQL exposes it as Iterable scalar instead of wrapping
                // in a broken CursorConnection (FilterOption is a non-ApiResource
                // DTO with no Read operation for edge resolution).
                $optionId = (int) $item->getValue();
                $option = [
                    'value' => (string) $item->getValue(),
                    'label' => (string) $item->getLabel(),
                    'count' => (int) $item->getCount(),
                    'swatch' => null,
                ];

                if (isset($swatchMap[$optionId])) {
                    $option['swatch'] = [
                        'type'  => $swatchMap[$optionId]->type,
                        'value' => $swatchMap[$optionId]->value,
                    ];
                }

                $dto->options[] = $option;
            }

            if (!empty($dto->options)) {
                $filters[] = $dto;
            }
        }

        usort($filters, fn(LayeredFilter $a, LayeredFilter $b) => $a->position <=> $b->position);

        return $filters;
    }

    /**
     * Load swatch data for all option IDs across filterable attributes.
     * Returns a map of option_id => FilterOptionSwatch.
     *
     * @param iterable<\Mage_Eav_Model_Entity_Attribute_Abstract> $filterableAttributes
     * @return array<int, FilterOptionSwatch>
     */
    private function loadSwatchMap(iterable $filterableAttributes): array
    {
        $attributeIds = [];
        foreach ($filterableAttributes as $attribute) {
            if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
                $attributeIds[] = (int) $attribute->getId();
            }
        }

        if (empty($attributeIds)) {
            return [];
        }

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $swatchTable = $resource->getTableName('eav/attribute_option_swatch');
        $optionTable = $resource->getTableName('eav/attribute_option');

        $select = $read->select()
            ->from(['s' => $swatchTable], ['option_id', 'value', 'filename'])
            ->join(
                ['o' => $optionTable],
                's.option_id = o.option_id',
                [],
            )
            ->where('o.attribute_id IN (?)', $attributeIds)
            ->where('(s.value IS NOT NULL AND s.value != "") OR (s.filename IS NOT NULL AND s.filename != "")');

        $rows = $read->fetchAll($select);
        $map = [];

        $mediaBaseUrl = rtrim(\Mage::getBaseUrl('media'), '/');

        foreach ($rows as $row) {
            $optionId = (int) $row['option_id'];
            if (!empty($row['filename'])) {
                $map[$optionId] = new FilterOptionSwatch(
                    type: 'image',
                    value: $mediaBaseUrl . '/attribute/swatch/' . ltrim($row['filename'], '/'),
                );
            } elseif (!empty($row['value'])) {
                // Detect hex color (#RGB, #RRGGBB, #RRGGBBAA) vs text swatch
                $isHex = preg_match('/^#([0-9a-fA-F]{3,8})$/', $row['value']);
                $map[$optionId] = new FilterOptionSwatch(
                    type: $isHex ? 'color' : 'text',
                    value: $row['value'],
                );
            }
        }

        return $map;
    }

    /**
     * Convert DTO to array for caching
     */
    private function dtoToArray(LayeredFilter $dto): array
    {
        return [
            'code'     => $dto->code,
            'label'    => $dto->label,
            'type'     => $dto->type,
            'position' => $dto->position,
            // $dto->options is already array<int, array{value, label, count, swatch}>
            // - the builder above produces plain array shapes, not FilterOption DTOs
            // - so the cache round-trip is a straight passthrough.
            'options'  => $dto->options,
        ];
    }

    /**
     * Reconstruct DTO from cached array
     */
    private function arrayToDto(array $data): LayeredFilter
    {
        $dto = new LayeredFilter();
        $dto->code     = $data['code'] ?? '';
        $dto->label    = $data['label'] ?? '';
        $dto->type     = $data['type'] ?? 'select';
        $dto->position = $data['position'] ?? 0;
        // Options are stored and retrieved as plain array shapes
        // (array<int, array{value, label, count, swatch}>) - no DTO
        // round-trip needed.
        $dto->options  = $data['options'] ?? [];
        return $dto;
    }
}
