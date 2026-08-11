<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Resource;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Coupon State Provider.
 */
final class CouponProvider extends \Maho\ApiPlatform\Provider
{
    protected ?string $modelAlias = 'salesrule/coupon';
    protected array $defaultSort = ['coupon_id' => 'DESC'];

    #[\Override]
    protected function provideItem(int|string $id): Resource
    {
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->load($id);

        if (!$coupon->getId()) {
            throw new NotFoundHttpException('Coupon not found');
        }

        // A restricted token only sees coupons whose rule targets at least one
        // allowed website; hide the rest like the collection filter does.
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            /** @var \Mage_SalesRule_Model_Rule $rule */
            $rule = \Mage::getModel('salesrule/rule');
            $rule->load($coupon->getRuleId());
            $ruleWebsiteIds = array_map('intval', (array) $rule->getWebsiteIds());
            if (array_intersect($ruleWebsiteIds, $allowedWebsiteIds) === []) {
                throw new NotFoundHttpException('Coupon not found');
            }
        }

        return Coupon::fromModel($coupon);
    }

    #[\Override]
    protected function provideCollection(array $context): TraversablePaginator
    {
        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/coupon_collection');

        $this->applyCollectionFilters($collection, $context['filters'] ?? []);

        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            $collection->getSelect()->where(
                'main_table.rule_id IN (SELECT rule_id FROM '
                    . $collection->getResource()->getTable('salesrule/website')
                    . ' WHERE website_id IN (?))',
                $allowedWebsiteIds === [] ? [-1] : $allowedWebsiteIds,
            );
        }

        foreach ($this->defaultSort as $field => $dir) {
            $collection->setOrder($field, $dir);
        }

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context);
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $total = (int) $collection->getSize();

        $coupons = [];
        foreach ($collection as $coupon) {
            $coupons[] = Coupon::fromModel($coupon);
        }

        return new TraversablePaginator(new \ArrayIterator($coupons), $page, $pageSize, $total);
    }

    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        if (!empty($filters['code'])) {
            $collection->addFieldToFilter('code', ['like' => '%' . $filters['code'] . '%']);
        }

        if (isset($filters['is_active'])) {
            $collection->getSelect()->joinInner(
                ['rule' => $collection->getResource()->getTable('salesrule/rule')],
                'main_table.rule_id = rule.rule_id',
                [],
            );
            $collection->getSelect()->where('rule.is_active = ?', (int) $filters['is_active']);
        }
    }
}
