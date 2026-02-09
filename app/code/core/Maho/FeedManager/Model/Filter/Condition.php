<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Condition Filter Model
 *
 * Provides SQL condition building for product feed filtering.
 * Used by both Generator and Generator/Batch to avoid code duplication.
 */
class Maho_FeedManager_Model_Filter_Condition
{
    /**
     * Build SQL condition array for standard attributes
     * Handles select/multiselect attributes by resolving option labels to IDs
     *
     * @return array{attribute: string, condition: array}|null
     */
    public function buildSqlCondition(string $attribute, string $operator, string $value): ?array
    {
        // Check if this is a select/multiselect attribute that needs option ID resolution
        $resolvedValue = $this->resolveSelectAttributeValue($attribute, $operator, $value);
        if ($resolvedValue !== null) {
            $value = $resolvedValue['value'];
            $operator = $resolvedValue['operator'];
        }

        $condition = match ($operator) {
            'eq' => ['eq' => $value],
            'neq' => ['neq' => $value],
            'gt' => ['gt' => $value],
            'gteq' => ['gteq' => $value],
            'lt' => ['lt' => $value],
            'lteq' => ['lteq' => $value],
            'in' => is_array($value) ? ['in' => $value] : ['in' => array_map('trim', explode(',', $value))],
            'nin' => is_array($value) ? ['nin' => $value] : ['nin' => array_map('trim', explode(',', $value))],
            'like' => ['like' => '%' . $value . '%'],
            'nlike' => ['nlike' => '%' . $value . '%'],
            'null' => ['null' => true],
            'notnull' => ['notnull' => true],
            default => ['eq' => $value],
        };

        return [
            'attribute' => $attribute,
            'condition' => $condition,
        ];
    }

    /**
     * Build stock condition info array
     *
     * @return array{attribute: string, operator: string, value: string, stock_value: mixed}
     */
    public function buildStockCondition(string $attribute, string $operator, string $value, array $condition): array
    {
        return [
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
            'stock_value' => $condition['stock_value'] ?? null,
        ];
    }

    /**
     * Build category condition info array
     *
     * @return array{operator: string, value: string, category_value: mixed}
     */
    public function buildCategoryCondition(string $operator, string $value, array $condition): array
    {
        return [
            'operator' => $operator,
            'value' => $value,
            'category_value' => $condition['category_value'] ?? null,
        ];
    }

    /**
     * Build type condition for product type filtering
     *
     * @return array<string, mixed>
     */
    public function buildTypeCondition(string $operator, string $value, array $condition): array
    {
        $typeValue = $condition['type_value'] ?? $value;

        return match ($operator) {
            'eq', 'in' => ['in' => is_array($typeValue) ? $typeValue : [$typeValue]],
            'neq', 'nin' => ['nin' => is_array($typeValue) ? $typeValue : [$typeValue]],
            default => ['eq' => $typeValue],
        };
    }

    /**
     * Build visibility condition for product visibility filtering
     *
     * @return array<string, mixed>
     */
    public function buildVisibilityCondition(string $operator, string $value, array $condition): array
    {
        $visibilityValue = $condition['visibility_value'] ?? $value;

        // Handle comma-separated string values
        if (is_string($visibilityValue) && str_contains($visibilityValue, ',')) {
            $visibilityValue = array_map('trim', explode(',', $visibilityValue));
        }

        return match ($operator) {
            'eq', 'in' => ['in' => is_array($visibilityValue) ? $visibilityValue : [$visibilityValue]],
            'neq', 'nin' => ['nin' => is_array($visibilityValue) ? $visibilityValue : [$visibilityValue]],
            default => ['eq' => $visibilityValue],
        };
    }

    /**
     * Apply stock condition to collection
     */
    public function applyStockConditionToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection, array $stockCond): void
    {
        $attribute = $stockCond['attribute'];
        $operator = $stockCond['operator'];
        $value = $stockCond['value'];

        // Join stock table if not already joined
        $collection->joinField(
            'qty',
            'cataloginventory/stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left',
        );

        if ($attribute === 'qty') {
            $sqlCondition = $this->buildSqlCondition('qty', $operator, $value);
            if ($sqlCondition) {
                $collection->addFieldToFilter('qty', $sqlCondition['condition']);
            }
        } elseif ($attribute === 'is_in_stock') {
            // Use stock_value from condition if available
            $stockValue = $stockCond['stock_value'] ?? $value;
            $collection->joinField(
                'is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left',
            );
            $collection->addFieldToFilter('is_in_stock', ['eq' => (int) $stockValue]);
        }
    }

    /**
     * Apply category condition to collection
     */
    public function applyCategoryConditionToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection, array $catCond): void
    {
        $operator = $catCond['operator'];
        $categoryIds = $catCond['category_value'] ?? $catCond['value'];

        if (is_string($categoryIds)) {
            $categoryIds = array_map('trim', explode(',', $categoryIds));
        }
        $categoryIds = array_filter($categoryIds);

        if (empty($categoryIds)) {
            return;
        }

        // Join with category_product table to filter by category
        $categoryTable = $collection->getTable('catalog/category_product');

        if ($operator === 'in' || $operator === 'eq') {
            $collection->getSelect()->join(
                ['cat_filter' => $categoryTable],
                'cat_filter.product_id = e.entity_id',
                [],
            );
            $collection->getSelect()->where('cat_filter.category_id IN (?)', $categoryIds);
            $collection->getSelect()->distinct(true);
        } elseif ($operator === 'nin' || $operator === 'neq') {
            // For NOT IN, we need products that are NOT in any of the specified categories
            $subSelect = $collection->getConnection()->select()
                ->from($categoryTable, ['product_id'])
                ->where('category_id IN (?)', $categoryIds);
            $collection->getSelect()->where('e.entity_id NOT IN (?)', $subSelect);
        }
    }

    /**
     * Resolve select/multiselect attribute values to option IDs
     *
     * For select attributes, text searches need to be converted to option ID searches
     * because the attribute stores integer option IDs, not text values.
     *
     * @return array{value: mixed, operator: string}|null
     */
    public function resolveSelectAttributeValue(string $attributeCode, string $operator, string $value): ?array
    {
        // Get the attribute
        $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
        if (!$attribute || !$attribute->getId()) {
            return null;
        }

        // Check if it's a select or multiselect type
        $frontendInput = $attribute->getFrontendInput();
        if (!in_array($frontendInput, ['select', 'multiselect'])) {
            return null;
        }

        // If the value is already numeric (option ID), no resolution needed
        if (is_numeric($value)) {
            return null;
        }

        // For text-based searches on select attributes, find matching option IDs
        $optionIds = $this->findOptionIdsByLabel($attribute, $value, $operator);

        if (empty($optionIds)) {
            // No matching options found - return impossible condition to get 0 results
            return ['value' => [-1], 'operator' => 'in'];
        }

        // Convert operator to 'in' or 'nin' since we now have option IDs
        $newOperator = match ($operator) {
            'eq', 'like' => 'in',
            'neq', 'nlike' => 'nin',
            default => $operator,
        };

        return ['value' => $optionIds, 'operator' => $newOperator];
    }

    /**
     * Find option IDs that match a label pattern
     *
     * @return array<int|string>
     */
    public function findOptionIdsByLabel(Mage_Eav_Model_Entity_Attribute_Abstract $attribute, string $searchValue, string $operator): array
    {
        $source = $attribute->getSource();
        if (!$source) {
            return [];
        }

        /** @var array<array{value: mixed, label: string}> $options */
        /** @phpstan-ignore arguments.count (getAllOptions accepts $withEmpty param in implementations) */
        $options = $source->getAllOptions(false);
        $matchingIds = [];

        foreach ($options as $option) {
            $label = (string) ($option['label'] ?? '');
            $optionId = $option['value'] ?? '';

            if (empty($optionId) || empty($label)) {
                continue;
            }

            $matches = match ($operator) {
                'eq' => strcasecmp($label, $searchValue) === 0,
                'neq' => strcasecmp($label, $searchValue) !== 0,
                'like' => stripos($label, $searchValue) !== false,
                'nlike' => stripos($label, $searchValue) === false,
                default => strcasecmp($label, $searchValue) === 0,
            };

            if ($matches) {
                $matchingIds[] = $optionId;
            }
        }

        return $matchingIds;
    }

    /**
     * Apply condition groups to collection as SQL WHERE clauses
     *
     * This is much more efficient than loading all products and filtering in PHP.
     * Logic: All groups must pass (AND), within each group ANY condition can pass (OR)
     */
    public function applyConditionGroupsToCollection(
        Mage_Catalog_Model_Resource_Product_Collection $collection,
        array $groups,
    ): void {
        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group) {
            $conditions = $group['conditions'] ?? [];
            if (empty($conditions)) {
                continue;
            }

            // Build OR conditions for this group
            $orConditions = [];
            $stockConditions = [];
            $categoryConditions = [];
            $typeConditions = [];
            $visibilityConditions = [];

            foreach ($conditions as $condition) {
                $attribute = $condition['attribute'] ?? '';
                $operator = $condition['operator'] ?? 'eq';
                $value = $condition['value'] ?? '';

                if (empty($attribute)) {
                    continue;
                }

                // Handle special attributes that need different treatment
                if ($attribute === 'qty' || $attribute === 'is_in_stock') {
                    $stockConditions[] = $this->buildStockCondition($attribute, $operator, $value, $condition);
                } elseif ($attribute === 'category_ids') {
                    $categoryConditions[] = $this->buildCategoryCondition($operator, $value, $condition);
                } elseif ($attribute === 'type_id') {
                    $typeConditions[] = $this->buildTypeCondition($operator, $value, $condition);
                } elseif ($attribute === 'visibility') {
                    $visibilityConditions[] = $this->buildVisibilityCondition($operator, $value, $condition);
                } else {
                    // Standard EAV attribute
                    $sqlCondition = $this->buildSqlCondition($attribute, $operator, $value);
                    if ($sqlCondition !== null) {
                        $orConditions[] = $sqlCondition;
                    }
                }
            }

            // Apply standard attribute conditions (OR within group)
            if (!empty($orConditions)) {
                if (count($orConditions) === 1) {
                    $collection->addAttributeToFilter($orConditions[0]['attribute'], $orConditions[0]['condition']);
                } else {
                    // Multiple OR conditions
                    $collection->addAttributeToFilter($orConditions);
                }
            }

            // Apply stock conditions
            foreach ($stockConditions as $stockCond) {
                $this->applyStockConditionToCollection($collection, $stockCond);
            }

            // Apply category conditions
            foreach ($categoryConditions as $catCond) {
                $this->applyCategoryConditionToCollection($collection, $catCond);
            }

            // Apply type conditions
            foreach ($typeConditions as $typeCond) {
                $collection->addAttributeToFilter('type_id', $typeCond);
            }

            // Apply visibility conditions
            foreach ($visibilityConditions as $visCond) {
                $collection->addAttributeToFilter('visibility', $visCond);
            }
        }
    }
}
