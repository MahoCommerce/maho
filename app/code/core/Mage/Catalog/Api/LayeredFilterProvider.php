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
                $filters = array_map($this->arrayToDto(...), $data);
                return new TraversablePaginator(new \ArrayIterator($filters), 1, 100, count($filters));
            }
        }

        $filters = $this->buildFilters($categoryId);

        if (!empty($filters)) {
            $cacheData = array_map($this->dtoToArray(...), $filters);
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

        $swatchHelper = \Mage::helper('configurableswatches');
        $swatchImageHelper = \Mage::helper('configurableswatches/productimg');
        $dimensionHelper = \Mage::helper('configurableswatches/swatchdimensions');
        $swatchWidth = (int) $dimensionHelper->getInnerWidth(\Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER);
        $swatchHeight = (int) $dimensionHelper->getInnerHeight(\Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER);

        $filters = [];

        foreach ($layer->getFilterableAttributes() as $attribute) {
            /** @var \Mage_Catalog_Model_Layer_Filter_Attribute $filterModel */
            $filterModel = \Mage::getModel('catalog/layer_filter_attribute');
            $filterModel->setLayer($layer);
            $filterModel->setAttributeModel($attribute);

            $dto = new LayeredFilter();
            $dto->code = $attribute->getAttributeCode();
            $dto->label = $attribute->getStoreLabel() ?: $attribute->getFrontendLabel();
            $dto->type = $attribute->getFrontendInput();
            $dto->position = (int) $attribute->getPosition();
            $dto->multiple = (bool) $attribute->getIsFilterableMultiple();
            $isSwatch = $swatchHelper->isEnabled() && $swatchHelper->attrIsSwatchType($attribute);

            $items = $filterModel->getItems();
            foreach ($items as $item) {
                // Build a plain array shape (not a FilterOption DTO) - the
                // $dto->options property is typed as array<int, array<string, mixed>>
                // so GraphQL exposes it as Iterable scalar instead of wrapping
                // in a broken CursorConnection (FilterOption is a non-ApiResource
                // DTO with no Read operation for edge resolution).
                $dto->options[] = [
                    'value' => (string) $item->getValue(),
                    'label' => (string) $item->getLabel(),
                    'count' => (int) $item->getCount(),
                    'swatch' => $isSwatch
                        ? $swatchImageHelper->getGlobalSwatch($item, (string) $item->getLabel(), $swatchWidth, $swatchHeight)
                        : null,
                ];
            }

            if (!empty($dto->options)) {
                $filters[] = $dto;
            }
        }

        usort($filters, fn(LayeredFilter $a, LayeredFilter $b) => $a->position <=> $b->position);

        return $filters;
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
            'multiple' => $dto->multiple,
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
        $dto->multiple = (bool) ($data['multiple'] ?? false);
        // Options are stored and retrieved as plain array shapes
        // (array<int, array{value, label, count, swatch}>) - no DTO
        // round-trip needed.
        $dto->options  = $data['options'] ?? [];
        return $dto;
    }
}
