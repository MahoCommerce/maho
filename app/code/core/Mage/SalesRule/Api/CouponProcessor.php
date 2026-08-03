<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Coupon State Processor - Handles coupon CRUD and validation.
 */
final class CouponProcessor extends \Maho\ApiPlatform\Processor
{
    private const DISCOUNT_TYPE_MAP = [
        'percent' => 'by_percent',
        'fixed' => 'by_fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    private const VALID_DISCOUNT_TYPES = ['percent', 'fixed', 'cart_fixed', 'buy_x_get_y'];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Coupon
    {
        $operationName = $operation->getName();

        return match (true) {
            $operationName === 'create' => $this->createFromGraphQl($context),
            $operationName === 'update' => $this->updateFromGraphQl($context),
            $operationName === 'delete' => $this->deleteFromGraphQl($context),
            $operationName === 'validate' => $this->validateFromGraphQl($context),
            $operation instanceof Delete => $this->doDelete((int) ($uriVariables['id'] ?? 0)),
            str_contains($operationName, 'validate') => $this->doValidateFromRest($context),
            isset($uriVariables['id']) => $this->doUpdate((int) $uriVariables['id'], $context['request']?->toArray() ?? []),
            default => $this->doCreate($context['request']?->toArray() ?? []),
        };
    }

    private function createFromGraphQl(array $context): Coupon
    {
        $args = $context['args']['input'] ?? [];
        return $this->doCreate($args);
    }

    private function updateFromGraphQl(array $context): Coupon
    {
        $args = $context['args']['input'] ?? [];
        $id = (int) ($args['id'] ?? 0);
        if (!$id) {
            throw new BadRequestHttpException('Coupon ID is required');
        }
        return $this->doUpdate($id, $args);
    }

    private function deleteFromGraphQl(array $context): null
    {
        $id = (int) ($context['args']['input']['id'] ?? 0);
        return $this->doDelete($id);
    }

    private function validateFromGraphQl(array $context): Coupon
    {
        $args = $context['args']['input'] ?? [];
        return $this->doValidate(
            $args['code'] ?? '',
            isset($args['cartId']) ? (int) $args['cartId'] : null,
        );
    }

    private function doValidateFromRest(array $context): Coupon
    {
        $body = $context['request']?->toArray() ?? [];
        return $this->doValidate(
            $body['code'] ?? '',
            isset($body['cartId']) ? (int) $body['cartId'] : null,
        );
    }

    private function doCreate(array $data): Coupon
    {
        $code = $data['code'] ?? '';
        $this->validateCouponCode($code);

        $discountType = $data['discountType'] ?? '';
        if (!in_array($discountType, self::VALID_DISCOUNT_TYPES, true)) {
            throw new BadRequestHttpException('Invalid discount type. Must be one of: ' . implode(', ', self::VALID_DISCOUNT_TYPES));
        }

        $discountAmount = (float) ($data['discountAmount'] ?? 0);
        if ($discountAmount <= 0) {
            throw new BadRequestHttpException('Discount amount must be greater than 0');
        }

        /** @var \Mage_SalesRule_Model_Coupon $existingCoupon */
        $existingCoupon = \Mage::getModel('salesrule/coupon');
        $existingCoupon->loadByCode($code);
        if ($existingCoupon->getId()) {
            throw new BadRequestHttpException("Coupon code '{$code}' already exists");
        }

        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->setName($data['description'] ?? "Coupon: {$code}");
        $rule->setDescription($data['description'] ?? '');
        $rule->setIsActive(isset($data['isActive']) ? (int) $data['isActive'] : 1);
        $rule->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC);
        $rule->setSimpleAction(self::DISCOUNT_TYPE_MAP[$discountType]);
        $rule->setDiscountAmount($discountAmount);
        $rule->setSortOrder(isset($data['sortOrder']) ? (int) $data['sortOrder'] : 0);
        $rule->setStopRulesProcessing(isset($data['stopRulesProcessing']) ? (int) (bool) $data['stopRulesProcessing'] : 0);
        $rule->setDiscountStep(isset($data['discountStep']) ? (int) $data['discountStep'] : 0);
        $rule->setSimpleFreeShipping($this->normalizeSimpleFreeShipping($data['simpleFreeShipping'] ?? 0));
        $rule->setApplyToShipping(isset($data['applyToShipping']) ? (int) (bool) $data['applyToShipping'] : 0);
        if (isset($data['discountQty'])) {
            $rule->setDiscountQty((float) $data['discountQty']);
        }

        if (!empty($data['fromDate'])) {
            $rule->setFromDate($data['fromDate']);
        }
        if (!empty($data['toDate'])) {
            $rule->setToDate($data['toDate']);
        }

        if (isset($data['usageLimit'])) {
            $rule->setUsesPerCoupon((int) $data['usageLimit']);
        }
        if (isset($data['usagePerCustomer'])) {
            $rule->setUsesPerCustomer((int) $data['usagePerCustomer']);
        }

        // When omitted the rule keeps the historical all-groups default; websites
        // default to the CURRENT store's website (not all websites) so a coupon
        // never silently spans websites the caller didn't ask for.
        $rule->setCustomerGroupIds(
            isset($data['customerGroupIds'])
                ? $this->normalizeCustomerGroupIds($data['customerGroupIds'])
                : array_keys(\Mage::getModel('customer/group')->getCollection()->toOptionHash()),
        );
        $rule->setWebsiteIds(
            isset($data['websiteIds'])
                ? $this->normalizeWebsiteIds($data['websiteIds'])
                : [(int) \Maho\ApiPlatform\Service\StoreContext::getStore()->getWebsiteId()],
        );

        if (isset($data['minimumSubtotal']) && (float) $data['minimumSubtotal'] > 0) {
            $this->setMinimumSubtotalCondition($rule, (float) $data['minimumSubtotal']);
        }

        $rule->setCouponCode($code);
        $rule->save();

        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->loadByCode($code);

        if (array_key_exists('expirationDate', $data)) {
            $coupon->setData('expiration_date', $this->normalizeExpirationDate($data['expirationDate']));
            $coupon->save();
        }

        return Coupon::fromModel($coupon);
    }

    private function doUpdate(int $id, array $data): Coupon
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

        if (!$rule->getId()) {
            throw new NotFoundHttpException('Associated price rule not found');
        }

        $this->assertRuleWebsitesAllowed($rule);

        if (isset($data['code'])) {
            $this->validateCouponCode($data['code']);
            /** @var \Mage_SalesRule_Model_Coupon $existingCoupon */
            $existingCoupon = \Mage::getModel('salesrule/coupon');
            $existingCoupon->loadByCode($data['code']);
            if ($existingCoupon->getId() && (int) $existingCoupon->getId() !== $id) {
                throw new BadRequestHttpException("Coupon code '{$data['code']}' already exists");
            }
            $coupon->setCode($data['code']);
            $coupon->save();
        }

        if (isset($data['discountType'])) {
            if (!in_array($data['discountType'], self::VALID_DISCOUNT_TYPES, true)) {
                throw new BadRequestHttpException('Invalid discount type');
            }
            $rule->setSimpleAction(self::DISCOUNT_TYPE_MAP[$data['discountType']]);
        }

        if (isset($data['discountAmount'])) {
            $amount = (float) $data['discountAmount'];
            if ($amount <= 0) {
                throw new BadRequestHttpException('Discount amount must be greater than 0');
            }
            $rule->setDiscountAmount($amount);
        }

        if (isset($data['description'])) {
            $rule->setDescription($data['description']);
            $rule->setName($data['description']);
        }

        if (isset($data['isActive'])) {
            $rule->setIsActive((int) $data['isActive']);
        }

        if (array_key_exists('usageLimit', $data)) {
            $rule->setUsesPerCoupon($data['usageLimit'] !== null ? (int) $data['usageLimit'] : 0);
        }

        if (array_key_exists('usagePerCustomer', $data)) {
            $rule->setUsesPerCustomer($data['usagePerCustomer'] !== null ? (int) $data['usagePerCustomer'] : 0);
        }

        if (array_key_exists('fromDate', $data)) {
            $rule->setFromDate($data['fromDate']);
        }

        if (array_key_exists('toDate', $data)) {
            $rule->setToDate($data['toDate']);
        }

        if (isset($data['sortOrder'])) {
            $rule->setSortOrder((int) $data['sortOrder']);
        }

        if (isset($data['stopRulesProcessing'])) {
            $rule->setStopRulesProcessing((int) (bool) $data['stopRulesProcessing']);
        }

        if (array_key_exists('discountQty', $data)) {
            $rule->setDiscountQty($data['discountQty'] !== null ? (float) $data['discountQty'] : null);
        }

        if (isset($data['discountStep'])) {
            $rule->setDiscountStep((int) $data['discountStep']);
        }

        if (isset($data['simpleFreeShipping'])) {
            $rule->setSimpleFreeShipping($this->normalizeSimpleFreeShipping($data['simpleFreeShipping']));
        }

        if (isset($data['applyToShipping'])) {
            $rule->setApplyToShipping((int) (bool) $data['applyToShipping']);
        }

        if (isset($data['customerGroupIds'])) {
            $rule->setCustomerGroupIds($this->normalizeCustomerGroupIds($data['customerGroupIds']));
        }

        if (isset($data['websiteIds'])) {
            $rule->setWebsiteIds($this->normalizeWebsiteIds($data['websiteIds']));
        }

        if (isset($data['minimumSubtotal'])) {
            $this->setMinimumSubtotalCondition($rule, (float) $data['minimumSubtotal']);
        }

        // Saving the rule re-syncs the primary coupon's expiration_date to the rule's
        // toDate (Rule::_afterSave), which would wipe a custom per-coupon date on any
        // unrelated update. An explicit expirationDate wins by being set last; when the
        // body carries neither expirationDate nor toDate the stored date is restored.
        $preservedExpiration = $coupon->getData('expiration_date');

        $rule->save();

        if (array_key_exists('expirationDate', $data)) {
            $coupon->load($id);
            $coupon->setData('expiration_date', $this->normalizeExpirationDate($data['expirationDate']));
            $coupon->save();
        } elseif (!array_key_exists('toDate', $data)) {
            $coupon->load($id);
            if ($coupon->getData('expiration_date') !== $preservedExpiration) {
                $coupon->setData('expiration_date', $preservedExpiration);
                $coupon->save();
            }
        }

        $coupon->load($id);

        return Coupon::fromModel($coupon);
    }

    private function doDelete(int $id): null
    {
        if (!$id) {
            throw new BadRequestHttpException('Coupon ID is required');
        }

        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->load($id);

        if (!$coupon->getId()) {
            throw new NotFoundHttpException('Coupon not found');
        }

        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->load($coupon->getRuleId());

        if ($rule->getId()) {
            $this->assertRuleWebsitesAllowed($rule);
            $rule->delete();
        } else {
            $coupon->delete();
        }

        return null;
    }

    /**
     * Hide rules outside the token's website scope from mutations, matching
     * the read-side filter (404, not 403, to avoid disclosing existence).
     */
    private function assertRuleWebsitesAllowed(\Mage_SalesRule_Model_Rule $rule): void
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->getAuthorizedUser());
        if ($allowedWebsiteIds === null) {
            return;
        }

        $ruleWebsiteIds = array_map('intval', (array) $rule->getWebsiteIds());
        if (array_intersect($ruleWebsiteIds, $allowedWebsiteIds) === []) {
            throw new NotFoundHttpException('Coupon not found');
        }
    }

    private function doValidate(string $code, ?int $cartId): Coupon
    {
        if (empty($code)) {
            throw new BadRequestHttpException('Coupon code is required');
        }

        // Public endpoint, throttle by IP to stop bulk enumeration of
        // auto-generated coupon batches.
        $this->checkRateLimitByIp('coupon_validate', 'coupon_validate', 60);

        // Add a per-customer cap on top of the IP limiter: a trivially-created
        // account behind a rotating-IP pool would otherwise bypass the IP cap
        // and still enumerate codes. Guests (no customer id) rely on IP alone.
        $customerId = $this->getAuthenticatedCustomerId();
        if ($customerId !== null) {
            $this->checkRateLimit('coupon_validate:customer:' . $customerId, 'coupon_validate', 60);
        }

        // Anonymous callers get a yes/no answer only, the concrete discount
        // type/amount would let an unauthenticated client harvest the value of
        // every code before redeeming it. Customers/admins/API users still see
        // the full result (they need it to preview a discount at checkout).
        $isAnonymous = !$this->isAdmin()
            && !$this->isApiUser()
            && $this->getAuthenticatedCustomerId() === null;

        $dto = new Coupon();
        $dto->id = 0;
        $dto->code = $code;

        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->loadByCode($code);

        if (!$coupon->getId()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon code not found';
            return $dto;
        }

        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->load($coupon->getRuleId());

        if (!$rule->getId() || !$rule->getIsActive()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon is not active';
            return $dto;
        }

        $now = \Mage::app()->getLocale()->formatDateForDb('now', withTime: false);
        if ($rule->getFromDate() && $now < $rule->getFromDate()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon is not yet active';
            return $dto;
        }
        if ($rule->getToDate() && $now > $rule->getToDate()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon has expired';
            return $dto;
        }

        // Per-coupon expiry, admin-entered as store-local datetime (see Mage_SalesRule_Model_Validator)
        $expirationDate = $coupon->getData('expiration_date');
        if ($expirationDate
            && $expirationDate < \Mage::app()->getLocale()->utcToStore()->format(\Mage_Core_Model_Locale::DATETIME_FORMAT)
        ) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon has expired';
            return $dto;
        }

        if ($rule->getUsesPerCoupon() && $coupon->getTimesUsed() >= $rule->getUsesPerCoupon()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon usage limit reached';
            return $dto;
        }

        if ($cartId) {
            /** @var \Mage_Sales_Model_Quote $quote */
            $quote = \Mage::getModel('sales/quote');
            $quote->load($cartId);
            if ($quote->getId()) {
                // Only the cart's owner (or a privileged caller) may preview a
                // discount on someone else's cart, without this check, any
                // authenticated client could probe arbitrary cart totals.
                $this->assertCanPreviewQuote($quote);

                // Run the discount preview on a transient copy so the
                // persisted quote is never mutated and we don't have to roll
                // back state. setCouponCode() + collectTotals() on a clone is
                // enough to populate $shippingAddress->getDiscountAmount().
                $previewQuote = clone $quote;
                $previewQuote->setCouponCode($code);
                $previewQuote->setTotalsCollectedFlag(false);
                $previewQuote->collectTotals();

                $dto->discountPreview = abs((float) $previewQuote->getShippingAddress()->getDiscountAmount());
            }
        }

        $dto->isValid = true;
        $dto->validationMessage = 'Coupon is valid';

        // Don't disclose rule identity or discount value to anonymous callers.
        if ($isAnonymous) {
            return $dto;
        }

        $dto->id = (int) $coupon->getId();
        $dto->ruleId = (int) $coupon->getRuleId();

        $discountTypeMap = [
            'by_percent' => 'percent',
            'by_fixed' => 'fixed',
            'cart_fixed' => 'cart_fixed',
            'buy_x_get_y' => 'buy_x_get_y',
        ];
        $dto->discountType = $discountTypeMap[$rule->getSimpleAction()] ?? $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();

        return $dto;
    }

    /**
     * Reject coupon preview attempts against carts the caller doesn't own.
     * Admins and API users with full coupon access bypass this check.
     */
    private function assertCanPreviewQuote(\Mage_Sales_Model_Quote $quote): void
    {
        if ($this->isAdmin() || $this->isApiUser()) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        if ($cartCustomerId !== null) {
            if ($authenticatedCustomerId === null || $cartCustomerId !== $authenticatedCustomerId) {
                throw new BadRequestHttpException('Cart not accessible');
            }
            return;
        }

        // Guest carts can't be previewed via the numeric ID endpoint,
        // mirrors CartService::verifyCartAccess(). The masked-id flow
        // is exercised through the CartProcessor coupon endpoints instead.
        throw new BadRequestHttpException('Cart not accessible');
    }

    private function validateCouponCode(string $code): void
    {
        if (empty($code)) {
            throw new BadRequestHttpException('Coupon code is required');
        }

        if (strlen($code) < 3 || strlen($code) > 64) {
            throw new BadRequestHttpException('Coupon code must be between 3 and 64 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $code)) {
            throw new BadRequestHttpException('Coupon code may only contain alphanumeric characters, dashes, and underscores');
        }
    }

    /** @return int[] */
    private function normalizeWebsiteIds(mixed $value): array
    {
        $known = array_map('intval', array_keys(\Mage::app()->getWebsites()));
        $ids = $this->normalizeIdList($value, $known, 'websiteIds', 'website');

        // A restricted token may only target websites its store allowlist maps to.
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->getAuthorizedUser());
        if ($allowedWebsiteIds !== null) {
            foreach ($ids as $id) {
                if (!in_array($id, $allowedWebsiteIds, true)) {
                    throw new AccessDeniedHttpException("Access denied for website: {$id}");
                }
            }
        }

        return $ids;
    }

    /** @return int[] */
    private function normalizeCustomerGroupIds(mixed $value): array
    {
        $known = array_map('intval', array_keys(\Mage::getModel('customer/group')->getCollection()->toOptionHash()));
        return $this->normalizeIdList($value, $known, 'customerGroupIds', 'customer group');
    }

    /**
     * @param int[] $known
     * @return int[]
     */
    private function normalizeIdList(mixed $value, array $known, string $field, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new BadRequestHttpException("{$field} must be a non-empty array of IDs");
        }

        $ids = [];
        foreach ($value as $id) {
            if (!is_numeric($id) || !in_array((int) $id, $known, true)) {
                throw new BadRequestHttpException("Unknown {$label} ID: " . (is_scalar($id) ? (string) $id : gettype($id)));
            }
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    private function normalizeSimpleFreeShipping(mixed $value): int
    {
        $normalized = (int) $value;
        $valid = [
            0,
            \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM,
            \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS,
        ];
        if (!in_array($normalized, $valid, true)) {
            throw new BadRequestHttpException('simpleFreeShipping must be 0 (no), 1 (matching items) or 2 (whole shipment)');
        }
        return $normalized;
    }

    /**
     * Normalize a per-coupon expiration date; empty string clears it (returns null).
     */
    private function normalizeExpirationDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = \Mage::app()->getLocale()->formatDateForDb((string) $value);
        } catch (\Exception) {
            throw new BadRequestHttpException('Invalid date for expirationDate; use Y-m-d or Y-m-d H:i:s format.');
        }

        return $date;
    }

    private function setMinimumSubtotalCondition(\Mage_SalesRule_Model_Rule $rule, float $minimumSubtotal): void
    {
        /** @var \Mage_SalesRule_Model_Rule_Condition_Combine $conditions */
        $conditions = \Mage::getModel('salesrule/rule_condition_combine');
        $conditions->setType('salesrule/rule_condition_combine');
        $conditions->setAttribute(null);
        $conditions->setOperator(null);
        $conditions->setValue(1);
        $conditions->setAggregator('all');

        if ($minimumSubtotal > 0) {
            /** @var \Mage_SalesRule_Model_Rule_Condition_Address $subtotalCondition */
            $subtotalCondition = \Mage::getModel('salesrule/rule_condition_address');
            $subtotalCondition->setType('salesrule/rule_condition_address');
            $subtotalCondition->setAttribute('base_subtotal');
            $subtotalCondition->setOperator('>=');
            $subtotalCondition->setValue($minimumSubtotal);
            $conditions->addCondition($subtotalCondition);
        }

        $rule->setConditions($conditions);
    }
}
