<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CartPriceRuleCouponProvider extends \Maho\ApiPlatform\Provider
{
    public function __construct(
        Security $security,
        private readonly CartPriceRuleProvider $ruleProvider,
    ) {
        parent::__construct($security);
    }

    /**
     * @return TraversablePaginator<CartPriceRuleCoupon>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $rule = $this->ruleProvider->loadRule((int) ($uriVariables['ruleId'] ?? 0));
        $this->ruleProvider->assertRuleReadable($rule, $this->requireUser());

        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/coupon_collection');
        $collection->addRuleToFilter($rule);
        $this->applyCollectionFilters($collection, $context['filters'] ?? []);
        $collection->getSelect()->order('main_table.coupon_id DESC');

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setPageSize($pageSize)->setCurPage($page);

        $items = [];
        foreach ($collection as $coupon) {
            $items[] = CartPriceRuleCoupon::fromCoupon($coupon);
        }

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $adapter = $collection->getConnection();
        foreach ($this->searchWords($this->stringFilter($filters, 'search')) as $word) {
            $collection->getSelect()->where(
                $adapter->prepareSqlCondition('main_table.code', ['like' => '%' . $word . '%']),
                null,
                \Maho\Db\Select::TYPE_CONDITION,
            );
        }

        $isUsed = $this->booleanFilter($filters, 'isUsed');
        if ($isUsed !== null) {
            $collection->getSelect()->where($isUsed ? 'main_table.times_used > 0' : 'main_table.times_used = 0');
        }

        $isPrimary = $this->booleanFilter($filters, 'isPrimary');
        if ($isPrimary !== null) {
            $collection->getSelect()->where(
                $isPrimary ? 'main_table.is_primary = 1' : '(main_table.is_primary IS NULL OR main_table.is_primary = 0)',
            );
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function booleanFilter(array $filters, string $key): ?bool
    {
        $value = $this->stringFilter($filters, $key);
        if ($value === null) {
            return null;
        }
        $flag = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($flag === null) {
            throw new BadRequestHttpException("{$key} must be true or false");
        }
        return $flag;
    }
}
