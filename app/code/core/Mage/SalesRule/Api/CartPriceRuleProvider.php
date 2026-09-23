<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CartPriceRuleProvider extends \Maho\ApiPlatform\Provider
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
    protected function provideItem(int|string $id): CartPriceRule
    {
        $rule = $this->loadRule((int) $id);
        $this->assertRuleReadable($rule, $this->requireUser());
        return $this->toRuleDto($rule, true);
    }

    /**
     * @return TraversablePaginator<CartPriceRule>
     */
    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        $filters = $context['filters'] ?? [];
        /** @var \Mage_SalesRule_Model_Resource_Rule_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/rule_collection');
        $collection->getSelect()->columns(['primary_coupon_id' => 'rule_coupons.coupon_id']);

        $this->applyCollectionFilters($collection, $filters);

        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            $this->whereRuleIn($collection, 'salesrule/website', 'website_id', $allowedWebsiteIds === [] ? [-1] : $allowedWebsiteIds);
        }

        $sort = $this->stringFilter($filters, 'sort') ?? 'id';
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

        $rules = array_values(array_filter($collection->getItems(), fn($rule) => $rule instanceof \Mage_SalesRule_Model_Rule));
        $couponCounts = $this->countCoupons(array_map(fn(\Mage_SalesRule_Model_Rule $rule) => (int) $rule->getId(), $rules));

        $items = [];
        foreach ($rules as $rule) {
            $rule->afterLoad();
            $items[] = $this->toRuleDto($rule, false, $couponCounts[(int) $rule->getId()] ?? 0);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        /** @var \Mage_SalesRule_Model_Resource_Rule_Collection $collection */
        $select = $collection->getSelect();
        $adapter = $collection->getConnection();

        // Every word must match part of the name, the description or the coupon code
        foreach ($this->searchWords($this->stringFilter($filters, 'search')) as $word) {
            $like = '%' . $word . '%';
            $select->where(implode(' OR ', array_map(
                fn(string $column) => $adapter->prepareSqlCondition($column, ['like' => $like]),
                ['main_table.name', 'main_table.description', 'rule_coupons.code'],
            )), null, \Maho\Db\Select::TYPE_CONDITION);
        }

        $isActive = $this->booleanFilter($filters, 'isActive');
        if ($isActive !== null) {
            $select->where('main_table.is_active = ?', $isActive ? 1 : 0);
        }

        $couponType = $this->stringFilter($filters, 'couponType');
        if ($couponType !== null) {
            match ($couponType) {
                CartPriceRule::COUPON_TYPE_NONE => $select->where('main_table.coupon_type = ?', \Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON),
                CartPriceRule::COUPON_TYPE_SPECIFIC => $select
                    ->where('main_table.coupon_type = ?', \Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
                    ->where('main_table.use_auto_generation = ?', 0),
                CartPriceRule::COUPON_TYPE_AUTO => $select->where(
                    '(main_table.coupon_type = ? AND main_table.use_auto_generation = 1) OR main_table.coupon_type = '
                        . \Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO,
                    \Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC,
                ),
                default => throw new BadRequestHttpException('couponType must be one of: none, specific, auto'),
            };
        }

        $websiteId = $this->intFilter($filters, 'websiteId');
        if ($websiteId !== null) {
            $this->whereRuleIn($collection, 'salesrule/website', 'website_id', [$websiteId]);
        }

        $customerGroupId = $this->intFilter($filters, 'customerGroupId');
        if ($customerGroupId !== null) {
            $this->whereRuleIn($collection, 'salesrule/customer_group', 'customer_group_id', [$customerGroupId]);
        }

        $activeOn = $this->stringFilter($filters, 'activeOn');
        if ($activeOn !== null) {
            if (!\Mage::helper('core')->isValidDate($activeOn)) {
                throw new BadRequestHttpException('activeOn must be a date in the format YYYY-MM-DD');
            }
            $fromDate = $adapter->quoteIdentifier('main_table.from_date');
            $toDate = $adapter->quoteIdentifier('main_table.to_date');
            $select->where("{$fromDate} IS NULL OR {$fromDate} <= ?", $activeOn)
                ->where("{$toDate} IS NULL OR {$toDate} >= ?", $activeOn);
        }

        $code = $this->stringFilter($filters, 'code');
        if ($code !== null) {
            $select->where('rule_coupons.code = ?', $code);
        }

        $attribute = $this->stringFilter($filters, 'usesAttribute');
        if ($attribute !== null) {
            if (!preg_match('/^[a-z][a-z0-9_]{0,254}$/', $attribute)) {
                throw new BadRequestHttpException('usesAttribute must be an attribute code');
            }
            $collection->addAttributeInConditionFilter($attribute);
        }
    }

    /**
     * Load a rule or answer 404.
     */
    public function loadRule(int $id): \Mage_SalesRule_Model_Rule
    {
        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = $this->loadById('salesrule/rule', $id);
        if (!$rule->getId()) {
            throw new NotFoundHttpException('Cart price rule not found');
        }
        return $rule;
    }

    /**
     * A restricted token reads a rule that has at least one of its websites. The rule is hidden otherwise.
     */
    public function assertRuleReadable(\Mage_SalesRule_Model_Rule $rule, ApiUser $user): void
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($user);
        if ($allowedWebsiteIds !== null
            && array_intersect(array_map(intval(...), (array) $rule->getWebsiteIds()), $allowedWebsiteIds) === []
        ) {
            throw new NotFoundHttpException('Cart price rule not found');
        }
    }

    /**
     * Build the DTO of $rule. Read the trees only when $withTrees is true, in the admin locale of the caller.
     */
    public function toRuleDto(\Mage_SalesRule_Model_Rule $rule, bool $withTrees, ?int $couponCount = null): CartPriceRule
    {
        $dto = new CartPriceRule();
        $dto->id = (int) $rule->getId();
        $dto->name = $rule->getName();
        $dto->description = $rule->getDescription();
        $dto->isActive = (bool) $rule->getIsActive();
        $dto->websiteIds = array_values(array_map(intval(...), (array) $rule->getWebsiteIds()));
        $dto->customerGroupIds = array_values(array_map(intval(...), (array) $rule->getCustomerGroupIds()));
        $dto->couponType = self::couponTypeOf($rule);
        $dto->couponCode = $dto->couponType === CartPriceRule::COUPON_TYPE_SPECIFIC ? $rule->getCouponCode() : null;
        $dto->usesPerCoupon = (int) $rule->getUsesPerCoupon();
        $dto->usesPerCustomer = (int) $rule->getUsesPerCustomer();
        $dto->fromDate = self::dateOf($rule->getFromDate());
        $dto->toDate = self::dateOf($rule->getToDate());
        $dto->sortOrder = (int) $rule->getSortOrder();
        $dto->stopRulesProcessing = (bool) $rule->getStopRulesProcessing();
        $dto->isRss = (bool) $rule->getIsRss();
        $dto->simpleAction = (string) $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();
        $dto->discountQty = $rule->getDiscountQty() !== null ? (float) $rule->getDiscountQty() : null;
        $dto->discountStep = (int) $rule->getDiscountStep();
        $dto->applyToShipping = (bool) $rule->getApplyToShipping();
        $dto->simpleFreeShipping = (int) $rule->getSimpleFreeShipping();
        $dto->timesUsed = (int) $rule->getTimesUsed();

        $labels = (array) $rule->getStoreLabels();
        ksort($labels);
        foreach ($labels as $storeId => $label) {
            $dto->storeLabels[] = ['storeId' => (int) $storeId, 'label' => (string) $label];
        }

        $primaryCouponId = $rule->hasData('primary_coupon_id') ? $rule->getData('primary_coupon_id') : $rule->getPrimaryCoupon()->getId();
        $dto->primaryCouponId = $primaryCouponId ? (int) $primaryCouponId : null;
        $dto->couponCount = $couponCount ?? ($this->countCoupons([$dto->id])[$dto->id] ?? 0);

        if ($withTrees) {
            $locale = $this->adminLocale();
            $writer = new \Mage_Rule_Model_Condition_TreeWriter($this->conditionMetadata($locale));
            [$dto->conditions, $dto->actions] = \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, fn() => [
                $writer->readTree($rule, 'conditions'),
                $writer->readTree($rule, 'actions'),
            ]);
        }

        return $dto;
    }

    public static function couponTypeOf(\Mage_SalesRule_Model_Rule $rule): string
    {
        return match (true) {
            $rule->getCouponType() === \Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON => CartPriceRule::COUPON_TYPE_NONE,
            $rule->getCouponType() === \Mage_SalesRule_Model_Rule::COUPON_TYPE_AUTO,
            (bool) $rule->getUseAutoGeneration() => CartPriceRule::COUPON_TYPE_AUTO,
            default => CartPriceRule::COUPON_TYPE_SPECIFIC,
        };
    }

    private static function dateOf(?string $value): ?string
    {
        return $value === null || $value === '' ? null : substr($value, 0, 10);
    }

    /**
     * @param int[] $ruleIds
     * @return array<int, int> Number of coupons by rule ID
     */
    private function countCoupons(array $ruleIds): array
    {
        if ($ruleIds === []) {
            return [];
        }
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('salesrule/coupon'), ['rule_id', 'count' => new \Maho\Db\Expr('COUNT(*)')])
            ->where('rule_id IN (?)', $ruleIds)
            ->group('rule_id');
        return array_map(intval(...), $adapter->fetchPairs($select));
    }

    /**
     * Keep the rules that have a row with one of $values in $column of the association table $table.
     *
     * @param int[] $values
     */
    private function whereRuleIn(\Mage_SalesRule_Model_Resource_Rule_Collection $collection, string $table, string $column, array $values): void
    {
        $collection->getSelect()->where(
            'main_table.rule_id IN (SELECT rule_id FROM ' . $collection->getTable($table) . " WHERE {$column} IN (?))",
            $values,
        );
    }
}
