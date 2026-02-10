<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\FilterOption;
use Maho\ApiPlatform\ApiResource\LayeredFilter;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Layered Filter Provider — uses Maho's built-in catalog layer to build facets
 *
 * @implements ProviderInterface<LayeredFilter>
 */
final class LayeredFilterProvider implements ProviderInterface
{
    /**
     * @return ArrayPaginator<LayeredFilter>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator
    {
        StoreContext::ensureStore();

        $args = array_merge($context['filters'] ?? [], $context['args'] ?? []);
        $categoryId = (int) ($args['categoryId'] ?? 0);

        if ($categoryId === 0) {
            return new ArrayPaginator(items: [], currentPage: 1, itemsPerPage: 100, totalItems: 0);
        }

        $storeId = StoreContext::getStoreId();
        $cacheKey = "api_layered_filters_{$categoryId}_{$storeId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)) {
                $filters = array_map(fn(array $f) => $this->arrayToDto($f), $data);
                return new ArrayPaginator(items: $filters, currentPage: 1, itemsPerPage: 100, totalItems: count($filters));
            }
        }

        $filters = $this->buildFilters($categoryId);

        if (!empty($filters)) {
            $cacheData = array_map(fn(LayeredFilter $f) => $this->dtoToArray($f), $filters);
            \Mage::app()->getCache()->save(
                json_encode($cacheData),
                $cacheKey,
                ['API_LAYERED_FILTERS', 'API_PRODUCTS'],
                \Maho_ApiPlatform_Model_Observer::getCacheTtl(),
            );
        }

        return new ArrayPaginator(
            items: $filters,
            currentPage: 1,
            itemsPerPage: 100,
            totalItems: count($filters),
        );
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

        /** @var \Mage_Catalog_Model_Layer $layer */
        $layer = \Mage::getSingleton('catalog/layer');
        $layer->setCurrentCategory($category);

        $filterableAttributes = $layer->getFilterableAttributes();
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
                $option = new FilterOption();
                $option->value = (string) $item->getValue();
                $option->label = (string) $item->getLabel();
                $option->count = (int) $item->getCount();
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
     * Convert DTO to array for caching
     */
    private function dtoToArray(LayeredFilter $dto): array
    {
        return [
            'code' => $dto->code,
            'label' => $dto->label,
            'type' => $dto->type,
            'position' => $dto->position,
            'options' => array_map(fn(FilterOption $o) => [
                'value' => $o->value,
                'label' => $o->label,
                'count' => $o->count,
            ], $dto->options),
        ];
    }

    /**
     * Reconstruct DTO from cached array
     */
    private function arrayToDto(array $data): LayeredFilter
    {
        $dto = new LayeredFilter();
        $dto->code = $data['code'] ?? '';
        $dto->label = $data['label'] ?? '';
        $dto->type = $data['type'] ?? 'select';
        $dto->position = $data['position'] ?? 0;
        $dto->options = array_map(function (array $o) {
            $option = new FilterOption();
            $option->value = $o['value'] ?? '';
            $option->label = $o['label'] ?? '';
            $option->count = $o['count'] ?? 0;
            return $option;
        }, $data['options'] ?? []);
        return $dto;
    }
}
