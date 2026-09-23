<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CartPriceRuleProcessor extends \Maho\ApiPlatform\Processor
{
    use ConditionMetadataTrait;
    use RuleFieldsTrait;

    private const WRITABLE_FIELDS = [
        'name', 'description', 'isActive', 'websiteIds', 'customerGroupIds', 'couponType', 'couponCode',
        'usesPerCoupon', 'usesPerCustomer', 'fromDate', 'toDate', 'sortOrder', 'stopRulesProcessing', 'isRss',
        'simpleAction', 'discountAmount', 'discountQty', 'discountStep', 'applyToShipping', 'simpleFreeShipping',
        'storeLabels', 'conditions', 'actions',
    ];

    /**
     * A client can send back a rule that it read, so these keys do not cause an error.
     */
    private const READ_ONLY_FIELDS = ['id', 'timesUsed', 'couponCount', 'primaryCouponId', 'extensions', '@context', '@id', '@type'];

    private const SIMPLE_ACTIONS = [
        \Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION,
        \Mage_SalesRule_Model_Rule::BY_FIXED_ACTION,
        \Mage_SalesRule_Model_Rule::CART_FIXED_ACTION,
        \Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION,
    ];

    private const BOOLEAN_FIELDS = [
        'isActive' => 'setIsActive',
        'stopRulesProcessing' => 'setStopRulesProcessing',
        'isRss' => 'setIsRss',
        'applyToShipping' => 'setApplyToShipping',
    ];

    private const COUNT_FIELDS = [
        'usesPerCoupon' => 'setUsesPerCoupon',
        'usesPerCustomer' => 'setUsesPerCustomer',
        'sortOrder' => 'setSortOrder',
        'discountStep' => 'setDiscountStep',
    ];

    /** @var list<array{field: string, message: string}> */
    private array $errors = [];

    public function __construct(
        Security $security,
        private readonly CartPriceRuleProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CartPriceRule
    {
        $user = $this->requireUser();
        $locale = $this->adminLocale();

        // Labels of the response trees come from condition models that the write creates, so the write runs in the locale too
        return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function () use ($operation, $uriVariables, $context, $user, $locale): ?CartPriceRule {
            if ($operation instanceof DeleteOperationInterface) {
                $this->delete((int) ($uriVariables['id'] ?? 0), $user);
                return null;
            }

            $body = $this->parseRequestBody($context['request'] ?? null);
            return isset($uriVariables['id'])
                ? $this->update((int) $uriVariables['id'], $body, $user, $locale)
                : $this->create($body, $user, $locale);
        });
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body, ApiUser $user, string $locale): CartPriceRule
    {
        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->setIsActive(false)
            ->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setUseAutoGeneration(false)
            ->setSimpleAction(\Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(0)
            ->setDiscountStep(0)
            ->setSortOrder(0)
            ->setStopRulesProcessing(false)
            ->setIsRss(false)
            ->setApplyToShipping(false)
            ->setSimpleFreeShipping(0)
            ->setUsesPerCoupon(0)
            ->setUsesPerCustomer(0);

        $this->errors = [];
        foreach (['name', 'websiteIds', 'customerGroupIds'] as $field) {
            if (!array_key_exists($field, $body)) {
                $this->addError($field, "{$field} is required");
            }
        }
        $this->applyFields($rule, $body, $user, true);
        $this->applyTrees($rule, $body, $locale, true);
        $this->throwErrors();

        $this->safeSave($rule, 'create cart price rule');
        $this->logApiActivity('cart_price_rule', 'create', null, $rule, $user);

        return $this->provider->toRuleDto($this->provider->loadRule((int) $rule->getId()), true);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function update(int $id, array $body, ApiUser $user, string $locale): CartPriceRule
    {
        $rule = $this->loadWritableRule($id, $user);
        $oldData = $rule->getData();

        $this->errors = [];
        $this->applyFields($rule, $body, $user, false);
        $this->applyTrees($rule, $body, $locale, false);
        $this->throwErrors();

        // Saving the rule copies its toDate to the expiration date of the primary coupon.
        // Keep a date set on the coupon itself, as PUT /coupons does, unless the body changes toDate.
        $primaryCoupon = $rule->getPrimaryCoupon();
        $preservedExpiration = $primaryCoupon->getId() ? $primaryCoupon->getData('expiration_date') : null;

        $this->safeSave($rule, 'update cart price rule');

        if ($primaryCoupon->getId() && !array_key_exists('toDate', $body)) {
            /** @var \Mage_SalesRule_Model_Coupon $coupon */
            $coupon = \Mage::getModel('salesrule/coupon')->load($primaryCoupon->getId());
            if ($coupon->getId() && $coupon->getData('expiration_date') !== $preservedExpiration) {
                $coupon->setData('expiration_date', $preservedExpiration)->save();
            }
        }
        $this->logApiActivity('cart_price_rule', 'update', $oldData, $rule, $user);

        return $this->provider->toRuleDto($this->provider->loadRule($id), true);
    }

    private function delete(int $id, ApiUser $user): void
    {
        $rule = $this->loadWritableRule($id, $user);
        $oldData = $rule->getData();
        $this->safeDelete($rule, 'delete cart price rule');
        $this->logApiActivity('cart_price_rule', 'delete', $oldData, null, $user);
    }

    /**
     * A restricted token changes only a rule whose websites are all in its scope.
     */
    public function loadWritableRule(int $id, ApiUser $user): \Mage_SalesRule_Model_Rule
    {
        $rule = $this->provider->loadRule($id);
        $this->provider->assertRuleReadable($rule, $user);
        $this->assertAllWebsitesAllowed((array) $rule->getWebsiteIds(), $user, 'cart price rule');
        return $rule;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyFields(\Mage_SalesRule_Model_Rule $rule, array $body, ApiUser $user, bool $isNew): void
    {
        foreach (array_keys($body) as $key) {
            if (!in_array($key, self::WRITABLE_FIELDS, true) && !in_array($key, self::READ_ONLY_FIELDS, true)) {
                $this->addError((string) $key, 'Unknown field');
            }
        }

        if (array_key_exists('name', $body)) {
            $name = is_string($body['name']) ? trim($body['name']) : '';
            if ($name === '' || mb_strlen($name) > 255) {
                $this->addError('name', 'name must be a text of 1 to 255 characters');
            } else {
                $rule->setName($name);
            }
        }

        if (array_key_exists('description', $body)) {
            if ($body['description'] !== null && !is_string($body['description'])) {
                $this->addError('description', 'description must be a text or null');
            } else {
                $rule->setDescription($body['description']);
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field => $setter) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if (!is_bool($value) && $value !== 0 && $value !== 1) {
                    $this->addError($field, "{$field} must be true or false");
                } else {
                    $rule->{$setter}((bool) $value);
                }
            }
        }

        foreach (self::COUNT_FIELDS as $field => $setter) {
            if (array_key_exists($field, $body)) {
                $value = $this->readCount($body[$field]);
                if ($value === null) {
                    $this->addError($field, "{$field} must be an integer of 0 or more");
                } else {
                    $rule->{$setter}($value);
                }
            }
        }

        $this->collect(function () use ($rule, $body): void {
            if (array_key_exists('websiteIds', $body)) {
                $rule->setWebsiteIds($this->normalizeWebsiteIds($body['websiteIds']));
            }
        });
        $this->collect(function () use ($rule, $body): void {
            if (array_key_exists('customerGroupIds', $body)) {
                $rule->setCustomerGroupIds($this->normalizeCustomerGroupIds($body['customerGroupIds']));
            }
        });
        $this->collect(function () use ($rule, $body): void {
            if (array_key_exists('simpleFreeShipping', $body)) {
                $rule->setSimpleFreeShipping($this->normalizeSimpleFreeShipping($body['simpleFreeShipping']));
            }
        });

        foreach (['fromDate' => 'setFromDate', 'toDate' => 'setToDate'] as $field => $setter) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if ($value === null || $value === '') {
                    $rule->{$setter}(null);
                } elseif (!is_string($value) || !$this->isDate($value)) {
                    $this->addError($field, "{$field} must be a date in the format YYYY-MM-DD or null");
                } else {
                    $rule->{$setter}($value);
                }
            }
        }
        $fromDate = $rule->getFromDate() ? substr($rule->getFromDate(), 0, 10) : null;
        $toDate = $rule->getToDate() ? substr($rule->getToDate(), 0, 10) : null;
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $this->addError(array_key_exists('toDate', $body) ? 'toDate' : 'fromDate', 'fromDate must not be after toDate');
        }

        if (array_key_exists('simpleAction', $body)) {
            if (!in_array($body['simpleAction'], self::SIMPLE_ACTIONS, true)) {
                $this->addError('simpleAction', 'simpleAction must be one of: ' . implode(', ', self::SIMPLE_ACTIONS));
            } else {
                $rule->setSimpleAction($body['simpleAction']);
            }
        }
        if (array_key_exists('discountAmount', $body)) {
            $amount = $this->readAmount($body['discountAmount']);
            if ($amount === null) {
                $this->addError('discountAmount', 'discountAmount must be a number of 0 or more');
            } else {
                $rule->setDiscountAmount($amount);
            }
        }
        if ($rule->getSimpleAction() === \Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION && (float) $rule->getDiscountAmount() > 100) {
            $this->addError('discountAmount', 'discountAmount must not be more than 100 for by_percent');
        }
        if (array_key_exists('discountQty', $body)) {
            $quantity = $body['discountQty'] === null ? null : $this->readAmount($body['discountQty']);
            if ($body['discountQty'] !== null && $quantity === null) {
                $this->addError('discountQty', 'discountQty must be a number of 0 or more, or null');
            } else {
                $rule->setDiscountQty($quantity);
            }
        }

        $this->applyCoupon($rule, $body, $isNew);
        $this->applyStoreLabels($rule, $body, $user, $isNew);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyCoupon(\Mage_SalesRule_Model_Rule $rule, array $body, bool $isNew): void
    {
        if (!array_key_exists('couponType', $body) && !array_key_exists('couponCode', $body)) {
            return;
        }

        $currentType = $isNew ? CartPriceRule::COUPON_TYPE_NONE : CartPriceRuleProvider::couponTypeOf($rule);
        $type = $body['couponType'] ?? $currentType;
        $validTypes = [CartPriceRule::COUPON_TYPE_NONE, CartPriceRule::COUPON_TYPE_SPECIFIC, CartPriceRule::COUPON_TYPE_AUTO];
        if (!in_array($type, $validTypes, true)) {
            $this->addError('couponType', 'couponType must be one of: ' . implode(', ', $validTypes));
            return;
        }

        $currentCode = $currentType === CartPriceRule::COUPON_TYPE_SPECIFIC ? (string) $rule->getCouponCode() : '';
        $code = array_key_exists('couponCode', $body) ? $body['couponCode'] : $currentCode;
        $code = is_string($code) ? trim($code) : $code;

        if ($type !== CartPriceRule::COUPON_TYPE_SPECIFIC) {
            if (array_key_exists('couponCode', $body) && $code !== null && $code !== '') {
                $this->addError('couponCode', 'couponCode applies only to couponType "specific"');
                return;
            }
            $rule->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
                ->setUseAutoGeneration(false)
                ->setCouponCode(null);
            if ($type === CartPriceRule::COUPON_TYPE_AUTO) {
                $rule->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)->setUseAutoGeneration(true);
            }
            return;
        }

        if ($code === null || $code === '') {
            $this->addError('couponCode', 'couponCode is required for couponType "specific"');
            return;
        }
        if ($code !== $currentCode) {
            try {
                $this->validateCouponCode($code, 'couponCode');
            } catch (ValidationException $e) {
                $this->addError('couponCode', $e->getMessage());
                return;
            }
            $primaryCouponId = $isNew ? null : (int) $rule->getPrimaryCoupon()->getId();
            if ($this->isCouponCodeTaken($code, $primaryCouponId ?: null)) {
                $this->addError('couponCode', "Coupon code '{$code}' already exists");
                return;
            }
        }
        $rule->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
            ->setUseAutoGeneration(false)
            ->setCouponCode($code);
    }

    /**
     * Replace the labels of the stores that the caller can use. Keep the labels of the other stores.
     *
     * @param array<string, mixed> $body
     */
    private function applyStoreLabels(\Mage_SalesRule_Model_Rule $rule, array $body, ApiUser $user, bool $isNew): void
    {
        if (!array_key_exists('storeLabels', $body)) {
            return;
        }
        $input = $body['storeLabels'] ?? [];
        if (!is_array($input) || !array_is_list($input)) {
            $this->addError('storeLabels', 'storeLabels must be a list of {storeId, label}');
            return;
        }

        $allowedStoreIds = $user->getAllowedStoreIds();
        $knownStoreIds = array_map(intval(...), array_keys(\Mage::app()->getStores(true)));
        $labels = [];
        foreach ($input as $index => $entry) {
            $field = "storeLabels[{$index}]";
            $storeId = is_array($entry) ? ($entry['storeId'] ?? null) : null;
            $label = is_array($entry) ? ($entry['label'] ?? null) : null;
            if (!is_int($storeId) || !in_array($storeId, $knownStoreIds, true)) {
                $this->addError("{$field}.storeId", 'storeId must be the ID of a store, or 0 for the default label');
                continue;
            }
            if (!is_string($label) || mb_strlen($label) > 255) {
                $this->addError("{$field}.label", 'label must be a text of at most 255 characters');
                continue;
            }
            if (array_key_exists($storeId, $labels)) {
                $this->addError("{$field}.storeId", "storeId {$storeId} is in the list more than once");
                continue;
            }
            if ($allowedStoreIds !== null && !in_array($storeId, $allowedStoreIds, true)) {
                throw new AccessDeniedHttpException("Access denied for store: {$storeId}");
            }
            $labels[$storeId] = trim($label);
        }

        // An empty label deletes the stored label of that store
        $existing = $isNew ? [] : (array) $rule->getStoreLabels();
        foreach (array_keys($existing) as $storeId) {
            $storeId = (int) $storeId;
            if (!array_key_exists($storeId, $labels) && ($allowedStoreIds === null || in_array($storeId, $allowedStoreIds, true))) {
                $labels[$storeId] = '';
            }
        }
        $rule->setStoreLabels($labels);
    }

    /**
     * Check the trees in $body and put them into $rule. The stored tree of an update lets old values pass.
     *
     * @param array<string, mixed> $body
     */
    private function applyTrees(\Mage_SalesRule_Model_Rule $rule, array $body, string $locale, bool $isNew): void
    {
        $treeKeys = array_values(array_filter(['conditions', 'actions'], fn(string $key) => array_key_exists($key, $body)));
        if ($treeKeys === []) {
            return;
        }

        $document = $this->conditionMetadata($locale);
        $validator = new \Mage_Rule_Model_Condition_TreeValidator($this->conditionMetadataModel(), $document);
        $writer = new \Mage_Rule_Model_Condition_TreeWriter($document);

        $cleanTrees = [];
        foreach ($treeKeys as $key) {
            $tree = $body[$key] ?? ['type' => $document['roots'][$key], 'aggregator' => 'all', 'value' => true, 'conditions' => []];
            $result = $validator->validate($key, $tree, $isNew ? null : $writer->readTree($rule, $key));
            foreach ($result['errors'] as $error) {
                $this->addError($error['field'], $error['message']);
            }
            if ($result['tree'] !== null) {
                $cleanTrees[$key] = $result['tree'];
            }
        }

        if ($this->errors === []) {
            foreach ($cleanTrees as $key => $cleanTree) {
                $writer->replaceTree($rule, $key, $cleanTree);
            }
        }
    }

    private function readCount(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false || $number < 0 ? null : $number;
    }

    private function readAmount(mixed $value): ?float
    {
        if (is_bool($value) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) && $number >= 0 ? $number : null;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * Run $check and record its validation error instead of throwing it, so the response lists all errors.
     */
    private function collect(\Closure $check): void
    {
        try {
            $check();
        } catch (ValidationException $e) {
            $this->addError((string) ($e->getDetails()['field'] ?? ''), $e->getMessage());
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
    }

    private function throwErrors(): void
    {
        if ($this->errors === []) {
            return;
        }
        $first = $this->errors[0];
        throw new ValidationException($first['message'], $first['field'], 'Invalid', ['errors' => $this->errors]);
    }
}
