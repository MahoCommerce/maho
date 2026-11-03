<?php

/**
 * Read a coupon from REST or GraphQL, and give the write and the check of a code to the cart price rule service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\PriceRuleFieldsTrait;

final class CouponProcessor extends \Maho\ApiPlatform\Processor
{
    use PriceRuleFieldsTrait;

    private const DISCOUNT_TYPE_MAP = [
        'percent' => 'by_percent',
        'fixed' => 'by_fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    /** The rule column and the type of each field */
    private const RULE_FIELDS = [
        'isActive' => ['is_active', 'bool'],
        'usageLimit' => ['uses_per_coupon', 'int'],
        'usagePerCustomer' => ['uses_per_customer', 'int'],
        'fromDate' => ['from_date', 'date'],
        'toDate' => ['to_date', 'date'],
        'sortOrder' => ['sort_order', 'int'],
        'stopRulesProcessing' => ['stop_rules_processing', 'bool'],
        'discountQty' => ['discount_qty', 'float'],
        'discountStep' => ['discount_step', 'int'],
        'simpleFreeShipping' => ['simple_free_shipping', 'int'],
        'applyToShipping' => ['apply_to_shipping', 'bool'],
    ];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Coupon
    {
        $name = $operation->getName();
        $isGraphQl = in_array($name, ['create', 'update', 'delete', 'validate'], true);
        $input = $isGraphQl ? $context['args']['input'] ?? [] : ($operation instanceof Delete ? [] : $context['request']?->toArray() ?? []);
        $id = (int) ($isGraphQl ? $input['id'] ?? 0 : $uriVariables['id'] ?? 0);

        return match (true) {
            str_contains($name, 'validate') => $this->validate((string) ($input['code'] ?? ''), isset($input['cartId']) ? (int) $input['cartId'] : null),
            $name === 'delete', $operation instanceof Delete => $this->delete($id),
            $name === 'update', isset($uriVariables['id']) => $this->update($id, $input),
            default => $this->create($input),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function create(array $data): Coupon
    {
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');
        $errors = new \Mage_Core_Exception_Input();
        $code = (string) ($data['code'] ?? '');

        $rule = $service->newRule()->setIsActive()->setName($data['description'] ?? "Coupon: {$code}")->setDescription($data['description'] ?? '');
        // Without scope fields, a coupon is for all groups and for the website of the current store only
        $rule->setCustomerGroupIds(\Mage::getResourceModel('customer/group_collection')->getAllIds())
            ->setWebsiteIds([(int) \Maho\ApiPlatform\Service\StoreContext::getStore()->getWebsiteId()]);
        $this->readFields($rule, $data + ['discountType' => ''], $errors);
        $this->readDiscountAmount($rule, $data['discountAmount'] ?? 0, $errors);

        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->setIsPrimary()->setCode($code);
        $this->readExpirationDate($coupon, $data, $errors);
        return Coupon::fromModel($service->getCouponById((int) $service->saveCoupon($coupon, $rule, $errors)->getId()));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function update(int $id, array $data): Coupon
    {
        if (!$id) {
            throw new \Mage_Core_Exception_Input()->addError('id', 'Coupon ID is required');
        }
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');
        [$coupon, $rule] = $this->loadWritable($service, $id);
        $errors = new \Mage_Core_Exception_Input();

        if (isset($data['description'])) {
            $rule->setDescription($data['description'])->setName($data['description']);
        }
        $this->readFields($rule, $data, $errors);
        if (isset($data['discountAmount'])) {
            $this->readDiscountAmount($rule, $data['discountAmount'], $errors);
        }
        if (isset($data['code'])) {
            $coupon->setCode((string) $data['code']);
        }
        $this->readExpirationDate($coupon, $data, $errors);
        $service->saveCoupon($coupon, $rule, $errors);

        return Coupon::fromModel($service->getCouponById($id));
    }

    private function delete(int $id): null
    {
        if (!$id) {
            throw new \Mage_Core_Exception_Input()->addError('id', 'Coupon ID is required');
        }
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');
        $service->deleteCoupon($this->loadWritable($service, $id)[0]);
        return null;
    }

    /**
     * Load the coupon $id and its rule. A restricted token changes only a coupon whose rule has all its websites
     * in the scope of the token, and does not see a coupon whose rule has none.
     *
     * @return array{\Mage_SalesRule_Model_Coupon, \Mage_SalesRule_Model_Rule}
     */
    private function loadWritable(\Mage_SalesRule_Service_Rule $service, int $id): array
    {
        $user = $this->requireUser();
        $coupon = $service->getCouponById($id, $this->allowedWebsiteIds($user));
        $rule = $service->getById((int) $coupon->getRuleId());
        $this->assertAllWebsitesAllowed((array) $rule->getWebsiteIds(), $user, 'coupon');
        return [$coupon, $rule];
    }

    /**
     * Put the fields of $data into $rule. An empty date removes the date.
     *
     * @param array<string, mixed> $data
     */
    private function readFields(\Mage_SalesRule_Model_Rule $rule, array $data, \Mage_Core_Exception_Input $errors): void
    {
        foreach (self::RULE_FIELDS as $field => [$column, $type]) {
            if (array_key_exists($field, $data) && ($data[$field] !== null || $type !== 'bool')) {
                $value = $data[$field];
                $rule->setData($column, match ($type) {
                    'bool' => (bool) $value,
                    'int' => (int) $value,
                    'float' => $value === null ? null : (float) $value,
                    'date' => $value ?: null,
                });
            }
        }

        if (isset($data['discountType'])) {
            if (!isset(self::DISCOUNT_TYPE_MAP[$data['discountType']])) {
                $errors->addError('discount_type', 'Invalid discount type. Must be one of: ' . implode(', ', array_keys(self::DISCOUNT_TYPE_MAP)));
            } else {
                $rule->setSimpleAction(self::DISCOUNT_TYPE_MAP[$data['discountType']]);
            }
        }
        foreach (['customerGroupIds' => 'customer_group_ids', 'websiteIds' => 'website_ids'] as $field => $column) {
            if (isset($data[$field])) {
                $ids = $this->readIdList($data[$field]);
                if ($ids === null) {
                    $errors->addError($column, "{$field} must be a list of IDs");
                    continue;
                }
                if ($column === 'website_ids') {
                    $this->assertWebsitesInScope($ids);
                }
                $rule->setData($column, $ids);
            }
        }
        if (isset($data['minimumSubtotal'])) {
            $this->setMinimumSubtotalCondition($rule, (float) $data['minimumSubtotal']);
        }
    }

    /**
     * A coupon of this resource always gives a discount.
     */
    private function readDiscountAmount(\Mage_SalesRule_Model_Rule $rule, mixed $amount, \Mage_Core_Exception_Input $errors): void
    {
        if ((float) $amount <= 0) {
            $errors->addError('discount_amount', 'Discount amount must be greater than 0');
        }
        $rule->setDiscountAmount((float) $amount);
    }

    /**
     * Put the expiration date of $data into $coupon. An empty value removes it.
     *
     * @param array<string, mixed> $data
     */
    private function readExpirationDate(\Mage_SalesRule_Model_Coupon $coupon, array $data, \Mage_Core_Exception_Input $errors): void
    {
        if (!array_key_exists('expirationDate', $data)) {
            return;
        }
        $value = $data['expirationDate'];
        try {
            $coupon->setExpirationDate($value === null || $value === '' ? null : \Mage::app()->getLocale()->formatDateForDb((string) $value));
        } catch (\Exception) {
            $errors->addError('expiration_date', 'Invalid date for expirationDate; use Y-m-d or Y-m-d H:i:s format.');
        }
    }

    /**
     * Check the code for the website, the group and the customer of the caller, or of the cart for an admin or an
     * API integration. An anonymous caller gets a yes or no only, so that it cannot read the value of each code
     * before it uses it. A cart preview needs a caller that may read the cart.
     */
    private function validate(string $code, ?int $cartId): Coupon
    {
        $customerId = $this->getAuthenticatedCustomerId();
        $isPrivileged = $this->isAdmin() || $this->isApiUser();
        /** @var \Mage_Sales_Model_Quote $quote */
        $quote = \Mage::getModel('sales/quote');
        if ($cartId) {
            $quote->load($cartId);
        }
        $useCart = $isPrivileged && $quote->getId();
        $groupId = match (true) {
            $useCart => (int) $quote->getCustomerGroupId(),
            $customerId !== null => (int) \Mage::getModel('customer/customer')->load($customerId)->getGroupId(),
            $isPrivileged => null,
            default => \Mage_Customer_Model_Group::NOT_LOGGED_IN_ID,
        };

        $dto = new Coupon();
        $dto->id = 0;
        $dto->code = $code;
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');
        try {
            $coupon = $service->checkCoupon(
                $code,
                (int) ($useCart ? $quote->getStore() : \Maho\ApiPlatform\Service\StoreContext::getStore())->getWebsiteId(),
                $groupId,
                $useCart ? ((int) $quote->getCustomerId() ?: null) : $customerId,
            );
        } catch (\Mage_SalesRule_Exception $e) {
            $dto->isValid = false;
            $dto->validationMessage = $e->getMessage();
            return $dto;
        }

        if ($quote->getId()) {
            // A guest cart has no owner to check here: its masked ID goes through the coupon endpoints of the cart
            if (!$isPrivileged && ($customerId === null || (int) $quote->getCustomerId() !== $customerId)) {
                throw new \Mage_Core_Exception('Cart not accessible');
            }
            // A copy, so that the stored cart does not change
            $preview = clone $quote;
            $preview->setCouponCode($code)->setTotalsCollectedFlag(false)->collectTotals();
            $dto->discountPreview = abs((float) $preview->getShippingAddress()->getDiscountAmount());
        }
        $dto->isValid = true;
        $dto->validationMessage = 'Coupon is valid';
        if ($customerId === null && !$isPrivileged) {
            return $dto;
        }

        $rule = $service->getById((int) $coupon->getRuleId());
        $dto->id = (int) $coupon->getId();
        $dto->ruleId = (int) $coupon->getRuleId();
        $dto->discountType = array_search($rule->getSimpleAction(), self::DISCOUNT_TYPE_MAP, true) ?: $rule->getSimpleAction();
        $dto->discountAmount = (float) $rule->getDiscountAmount();
        return $dto;
    }

    /**
     * Insert, change or remove the top-level condition "subtotal equals or greater than" and keep all other conditions.
     * A root that is not "ALL of these conditions are TRUE" gives the minimum another meaning, so the change is refused.
     *
     * @throws \Mage_Core_Exception_Conflict when the rule has other conditions under another root
     */
    private function setMinimumSubtotalCondition(\Mage_SalesRule_Model_Rule $rule, float $minimumSubtotal): void
    {
        $conditions = $rule->getConditions();
        $children = $conditions->getConditions();
        if ($conditions->getAggregator() !== 'all' || !$conditions->getValue()) {
            if ($children !== []) {
                throw new \Mage_Core_Exception_Conflict('The rule has advanced conditions. Change them with /cart-price-rules.');
            }
            $conditions->setAggregator('all')->setValue(1);
        }

        $subtotalCondition = null;
        foreach ($children as $index => $child) {
            if ($child instanceof \Mage_SalesRule_Model_Rule_Condition_Address
                && $child->getAttribute() === 'base_subtotal'
                && $child->getOperator() === '>='
            ) {
                $subtotalCondition = $child;
                if ($minimumSubtotal <= 0) {
                    unset($children[$index]);
                    $conditions->setConditions(array_values($children));
                }
                break;
            }
        }

        if ($minimumSubtotal <= 0) {
            return;
        }
        if ($subtotalCondition === null) {
            /** @var \Mage_SalesRule_Model_Rule_Condition_Address $subtotalCondition */
            $subtotalCondition = \Mage::getModel('salesrule/rule_condition_address');
            $subtotalCondition->setType('salesrule/rule_condition_address')->setAttribute('base_subtotal')->setOperator('>=');
            $conditions->addCondition($subtotalCondition);
        }
        $subtotalCondition->setValue((string) $minimumSubtotal);
    }
}
