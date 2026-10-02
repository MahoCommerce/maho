<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CatalogPriceRuleProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataTrait;

    private const SORT_COLUMNS = [
        'id' => 'main_table.rule_id',
        'name' => 'main_table.name',
        'sortOrder' => 'main_table.sort_order',
        'fromDate' => 'main_table.from_date',
        'toDate' => 'main_table.to_date',
    ];

    #[\Override]
    protected function provideItem(int|string $id): CatalogPriceRule
    {
        $rule = $this->loadRule((int) $id);
        $this->assertRuleReadable($rule, $this->requireUser());
        return $this->toRuleDto($rule, true);
    }

    /**
     * @return TraversablePaginator<CatalogPriceRule>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        $filters = $context['filters'] ?? [];
        /** @var \Mage_CatalogRule_Model_Resource_Rule_Collection $collection */
        $collection = \Mage::getResourceModel('catalogrule/rule_collection');

        $this->applyCollectionFilters($collection, $filters);

        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            $this->whereRuleIn($collection, 'catalogrule/website', 'website_id', $allowedWebsiteIds === [] ? [-1] : $allowedWebsiteIds);
        }

        $sort = $this->stringFilter($filters, 'sort') ?? 'sortOrder';
        $order = strtolower($this->stringFilter($filters, 'order') ?? ($sort === 'id' ? 'desc' : 'asc'));
        if (!isset(self::SORT_COLUMNS[$sort])) {
            throw new BadRequestHttpException('sort must be one of: ' . implode(', ', array_keys(self::SORT_COLUMNS)));
        }
        if (!in_array($order, ['asc', 'desc'], true)) {
            throw new BadRequestHttpException('order must be asc or desc');
        }
        $direction = strtoupper($order);
        $column = $collection->getConnection()->quoteIdentifier(self::SORT_COLUMNS[$sort]);
        $collection->getSelect()->order(["{$column} {$direction}", "main_table.rule_id {$direction}"]);

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setPageSize($pageSize)->setCurPage($page);

        $items = [];
        foreach ($collection->getItems() as $rule) {
            if ($rule instanceof \Mage_CatalogRule_Model_Rule) {
                $rule->afterLoad();
                $items[] = $this->toRuleDto($rule, false);
            }
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        /** @var \Mage_CatalogRule_Model_Resource_Rule_Collection $collection */
        $select = $collection->getSelect();
        $adapter = $collection->getConnection();

        // Every word must match part of the name or the description
        foreach ($this->searchWords($this->stringFilter($filters, 'search')) as $word) {
            $like = '%' . $word . '%';
            $select->where(implode(' OR ', array_map(
                fn(string $column) => $adapter->prepareSqlCondition($column, ['like' => $like]),
                ['main_table.name', 'main_table.description'],
            )), null, \Maho\Db\Select::TYPE_CONDITION);
        }

        $isActive = $this->booleanFilter($filters, 'isActive');
        if ($isActive !== null) {
            $select->where('main_table.is_active = ?', $isActive ? 1 : 0);
        }

        $websiteId = $this->intFilter($filters, 'websiteId');
        if ($websiteId !== null) {
            $this->whereRuleIn($collection, 'catalogrule/website', 'website_id', [$websiteId]);
        }

        $customerGroupId = $this->intFilter($filters, 'customerGroupId');
        if ($customerGroupId !== null) {
            $this->whereRuleIn($collection, 'catalogrule/customer_group', 'customer_group_id', [$customerGroupId]);
        }
    }

    /**
     * Load a rule or answer 404.
     */
    public function loadRule(int $id): \Mage_CatalogRule_Model_Rule
    {
        /** @var \Mage_CatalogRule_Model_Rule $rule */
        $rule = $this->loadById('catalogrule/rule', $id);
        if (!$rule->getId()) {
            throw new NotFoundHttpException('Catalog price rule not found');
        }
        return $rule;
    }

    /**
     * A restricted token reads a rule that has at least one of its websites. The rule is hidden otherwise.
     */
    public function assertRuleReadable(\Mage_CatalogRule_Model_Rule $rule, ApiUser $user): void
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($user);
        if ($allowedWebsiteIds !== null
            && array_intersect(array_map(intval(...), (array) $rule->getWebsiteIds()), $allowedWebsiteIds) === []
        ) {
            throw new NotFoundHttpException('Catalog price rule not found');
        }
    }

    /**
     * Build the DTO of $rule. Read the conditions tree only when $withTree is true, in the admin locale of the caller.
     */
    public function toRuleDto(\Mage_CatalogRule_Model_Rule $rule, bool $withTree): CatalogPriceRule
    {
        $dto = new CatalogPriceRule();
        $dto->id = (int) $rule->getId();
        $dto->name = $rule->getName();
        $dto->description = $rule->getDescription();
        $dto->isActive = (bool) $rule->getIsActive();
        $dto->websiteIds = array_values(array_map(intval(...), (array) $rule->getWebsiteIds()));
        $dto->customerGroupIds = array_values(array_map(intval(...), (array) $rule->getCustomerGroupIds()));
        $dto->fromDate = self::dateOf($rule->getFromDate());
        $dto->toDate = self::dateOf($rule->getToDate());
        $dto->sortOrder = (int) $rule->getSortOrder();
        $dto->simpleAction = (string) ($rule->getSimpleAction() ?? 'by_percent');
        $dto->discountAmount = (float) $rule->getData('discount_amount');
        $dto->subIsEnable = (bool) $rule->getSubIsEnable();
        $dto->subSimpleAction = $rule->getSubSimpleAction() !== null && $rule->getSubSimpleAction() !== '' ? $rule->getSubSimpleAction() : null;
        $dto->subDiscountAmount = (float) $rule->getSubDiscountAmount();
        $dto->stopRulesProcessing = (bool) $rule->getStopRulesProcessing();

        if ($withTree) {
            $locale = $this->adminLocale();
            $writer = new \Mage_Rule_Model_Condition_TreeWriter($this->conditionMetadata($locale));
            $dto->conditions = \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, fn() => $writer->readTree($rule, 'conditions'));
        }

        return $dto;
    }

    private static function dateOf(?string $value): ?string
    {
        return $value === null || $value === '' ? null : substr($value, 0, 10);
    }

    /**
     * Keep the rules that have a row with one of $values in $column of the association table $table.
     *
     * @param int[] $values
     */
    private function whereRuleIn(\Mage_CatalogRule_Model_Resource_Rule_Collection $collection, string $table, string $column, array $values): void
    {
        $collection->getSelect()->where(
            'main_table.rule_id IN (SELECT rule_id FROM ' . $collection->getTable($table) . " WHERE {$column} IN (?))",
            $values,
        );
    }
}
