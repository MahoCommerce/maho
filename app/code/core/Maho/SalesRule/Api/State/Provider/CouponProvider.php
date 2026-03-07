<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SalesRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\SalesRule\Api\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\ArrayPaginator;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\SalesRule\Api\Resource\Coupon;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Coupon State Provider
 *
 * @implements ProviderInterface<Coupon>
 */
final class CouponProvider implements ProviderInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Coupon|ArrayPaginator|null
    {
        $this->requireAdminOrApiUser('Coupon access requires admin or API access');

        // Collection
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($context);
        }

        // Single item
        $id = (int) ($uriVariables['id'] ?? 0);
        if ($id) {
            return $this->getCouponById($id);
        }

        return null;
    }

    private function getCouponById(int $id): Coupon
    {
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->load($id);

        if (!$coupon->getId()) {
            throw new NotFoundHttpException('Coupon not found');
        }

        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->load($coupon->getRuleId());

        return $this->mapToDto($coupon, $rule);
    }

    private function getCollection(array $context): ArrayPaginator
    {
        $page = max(1, (int) ($context['filters']['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($context['filters']['itemsPerPage'] ?? 20)));

        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/coupon_collection');

        // Filter by code (LIKE search)
        if (!empty($context['filters']['code'])) {
            $collection->addFieldToFilter('code', ['like' => '%' . $context['filters']['code'] . '%']);
        }

        // Filter by is_active via the rule table
        if (isset($context['filters']['is_active'])) {
            $collection->getSelect()->joinInner(
                ['rule' => $collection->getResource()->getTable('salesrule/rule')],
                'main_table.rule_id = rule.rule_id',
                [],
            );
            $collection->getSelect()->where('rule.is_active = ?', (int) $context['filters']['is_active']);
        }

        $collection->setOrder('coupon_id', 'DESC');

        $coupons = [];
        foreach ($collection as $coupon) {
            /** @var \Mage_SalesRule_Model_Rule $rule */
            $rule = \Mage::getModel('salesrule/rule');
            $rule->load($coupon->getRuleId());
            $coupons[] = $this->mapToDto($coupon, $rule);
        }

        $offset = ($page - 1) * $perPage;

        return new ArrayPaginator($coupons, $offset, $perPage);
    }

    private function mapToDto(\Mage_SalesRule_Model_Coupon $coupon, \Mage_SalesRule_Model_Rule $rule): Coupon
    {
        $dto = new Coupon();
        $dto->id = (int) $coupon->getId();
        $dto->code = $coupon->getCode();
        $dto->ruleId = (int) $coupon->getRuleId();
        $dto->ruleName = $rule->getName();
        $dto->timesUsed = (int) $coupon->getTimesUsed();

        // Map discount type from internal to API format
        $discountTypeMap = [
            'by_percent' => 'percent',
            'by_fixed' => 'fixed',
            'cart_fixed' => 'cart_fixed',
            'buy_x_get_y' => 'buy_x_get_y',
        ];
        $dto->discountType = $discountTypeMap[$rule->getSimpleAction()] ?? $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();
        $dto->description = $rule->getDescription();
        $dto->isActive = (bool) $rule->getIsActive();
        $dto->usageLimit = $rule->getUsesPerCoupon() ? (int) $rule->getUsesPerCoupon() : null;
        $dto->usagePerCustomer = $rule->getUsesPerCustomer() ? (int) $rule->getUsesPerCustomer() : null;
        $dto->fromDate = $rule->getFromDate();
        $dto->toDate = $rule->getToDate();

        // Get minimum subtotal from conditions if set
        $conditions = $rule->getConditions();
        if ($conditions) {
            $dto->minimumSubtotal = $this->extractMinimumSubtotal($conditions);
        }

        return $dto;
    }

    private function extractMinimumSubtotal(\Mage_Rule_Model_Condition_Abstract $conditions): ?float
    {
        foreach ($conditions->getConditions() as $condition) {
            if ($condition->getAttribute() === 'base_subtotal' && $condition->getOperator() === '>=') {
                return (float) $condition->getValue();
            }
        }
        return null;
    }
}
