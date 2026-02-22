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

namespace Maho\SalesRule\Api\State\Processor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\SalesRule\Api\Resource\Coupon;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Coupon State Processor - Handles coupon CRUD and validation
 *
 * @implements ProcessorInterface<Coupon, Coupon>
 */
final class CouponProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    private const DISCOUNT_TYPE_MAP = [
        'percent' => 'by_percent',
        'fixed' => 'by_fixed',
        'cart_fixed' => 'cart_fixed',
        'buy_x_get_y' => 'buy_x_get_y',
    ];

    private const VALID_DISCOUNT_TYPES = ['percent', 'fixed', 'cart_fixed', 'buy_x_get_y'];

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Coupon
    {
        $operationName = $operation->getName();

        return match (true) {
            $operationName === 'createCoupon' => $this->createFromGraphQl($context),
            $operationName === 'updateCoupon' => $this->updateFromGraphQl($context),
            $operationName === 'deleteCoupon' => $this->deleteFromGraphQl($context),
            $operationName === 'validateCoupon' => $this->validateFromGraphQl($context),
            $operation instanceof Delete => $this->doDelete((int) ($uriVariables['id'] ?? 0)),
            str_contains($operationName, 'validate') => $this->doValidateFromRest($context),
            isset($uriVariables['id']) => $this->doUpdate((int) $uriVariables['id'], $context['request']?->toArray() ?? []),
            default => $this->doCreate($context['request']?->toArray() ?? []),
        };
    }

    private function createFromGraphQl(array $context): Coupon
    {
        $this->requireAdminOrApiUser('Coupon creation requires admin or API access');
        $args = $context['args']['input'] ?? [];
        return $this->doCreate($args);
    }

    private function updateFromGraphQl(array $context): Coupon
    {
        $this->requireAdminOrApiUser('Coupon update requires admin or API access');
        $args = $context['args']['input'] ?? [];
        $id = (int) ($args['id'] ?? 0);
        if (!$id) {
            throw new BadRequestHttpException('Coupon ID is required');
        }
        return $this->doUpdate($id, $args);
    }

    private function deleteFromGraphQl(array $context): null
    {
        $this->requireAdminOrApiUser('Coupon deletion requires admin or API access');
        $id = (int) ($context['args']['input']['id'] ?? 0);
        return $this->doDelete($id);
    }

    private function validateFromGraphQl(array $context): Coupon
    {
        // Validate allows authenticated customers too
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

        // Check for duplicate code
        /** @var \Mage_SalesRule_Model_Coupon $existingCoupon */
        $existingCoupon = \Mage::getModel('salesrule/coupon');
        $existingCoupon->loadByCode($code);
        if ($existingCoupon->getId()) {
            throw new BadRequestHttpException("Coupon code '{$code}' already exists");
        }

        // Build the sales rule
        /** @var \Mage_SalesRule_Model_Rule $rule */
        $rule = \Mage::getModel('salesrule/rule');
        $rule->setName($data['description'] ?? "Coupon: {$code}");
        $rule->setDescription($data['description'] ?? '');
        $rule->setIsActive(isset($data['isActive']) ? (int) $data['isActive'] : 1);
        $rule->setCouponType(\Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC);
        $rule->setSimpleAction(self::DISCOUNT_TYPE_MAP[$discountType]);
        $rule->setDiscountAmount($discountAmount);
        $rule->setDiscountStep(0);
        $rule->setStopRulesProcessing(0);

        // Set dates
        if (!empty($data['fromDate'])) {
            $rule->setFromDate($data['fromDate']);
        }
        if (!empty($data['toDate'])) {
            $rule->setToDate($data['toDate']);
        }

        // Usage limits
        if (isset($data['usageLimit'])) {
            $rule->setUsesPerCoupon((int) $data['usageLimit']);
        }
        if (isset($data['usagePerCustomer'])) {
            $rule->setUsesPerCustomer((int) $data['usagePerCustomer']);
        }

        // Set for all customer groups and all websites
        $rule->setCustomerGroupIds(array_keys(\Mage::getModel('customer/group')->getCollection()->toOptionHash()));
        $rule->setWebsiteIds(array_keys(\Mage::app()->getWebsites()));

        // Minimum subtotal condition
        if (isset($data['minimumSubtotal']) && (float) $data['minimumSubtotal'] > 0) {
            $this->setMinimumSubtotalCondition($rule, (float) $data['minimumSubtotal']);
        }

        // Set coupon code — rule's _afterSave will create the primary coupon
        $rule->setCouponCode($code);
        $rule->save();

        // Load the auto-created coupon
        /** @var \Mage_SalesRule_Model_Coupon $coupon */
        $coupon = \Mage::getModel('salesrule/coupon');
        $coupon->loadByCode($code);

        return $this->mapToDto($coupon, $rule);
    }

    private function doUpdate(int $id, array $data): Coupon
    {
        $this->requireAdminOrApiUser('Coupon update requires admin or API access');

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

        // Update code if provided
        if (isset($data['code'])) {
            $this->validateCouponCode($data['code']);
            // Check uniqueness (excluding current)
            /** @var \Mage_SalesRule_Model_Coupon $existingCoupon */
            $existingCoupon = \Mage::getModel('salesrule/coupon');
            $existingCoupon->loadByCode($data['code']);
            if ($existingCoupon->getId() && (int) $existingCoupon->getId() !== $id) {
                throw new BadRequestHttpException("Coupon code '{$data['code']}' already exists");
            }
            $coupon->setCode($data['code']);
            $coupon->save();
        }

        // Update rule fields
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

        if (isset($data['minimumSubtotal'])) {
            $this->setMinimumSubtotalCondition($rule, (float) $data['minimumSubtotal']);
        }

        $rule->save();

        // Reload coupon in case code changed
        $coupon->load($id);

        return $this->mapToDto($coupon, $rule);
    }

    private function doDelete(int $id): null
    {
        $this->requireAdminOrApiUser('Coupon deletion requires admin or API access');

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
            $rule->delete(); // Cascades to coupon
        } else {
            $coupon->delete();
        }

        return null;
    }

    private function doValidate(string $code, ?int $cartId): Coupon
    {
        if (empty($code)) {
            throw new BadRequestHttpException('Coupon code is required');
        }

        $dto = new Coupon();
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

        // Check dates
        $now = \Mage::getModel('core/date')->gmtDate('Y-m-d');
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

        // Check usage limits
        if ($rule->getUsesPerCoupon() && $coupon->getTimesUsed() >= $rule->getUsesPerCoupon()) {
            $dto->isValid = false;
            $dto->validationMessage = 'Coupon usage limit reached';
            return $dto;
        }

        // Try to apply to cart for discount preview
        if ($cartId) {
            /** @var \Mage_Sales_Model_Quote $quote */
            $quote = \Mage::getModel('sales/quote');
            $quote->load($cartId);
            if ($quote->getId()) {
                $quote->setCouponCode($code);
                $quote->collectTotals();
                $discount = abs((float) $quote->getShippingAddress()->getDiscountAmount());
                $dto->discountPreview = $discount;
                // Reset the coupon on the quote
                $quote->setCouponCode('');
                $quote->collectTotals();
                $quote->save();
            }
        }

        $dto->isValid = true;
        $dto->validationMessage = 'Coupon is valid';
        $dto->id = (int) $coupon->getId();
        $dto->ruleId = (int) $coupon->getRuleId();

        // Map discount info
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

    private function mapToDto(\Mage_SalesRule_Model_Coupon $coupon, \Mage_SalesRule_Model_Rule $rule): Coupon
    {
        $dto = new Coupon();
        $dto->id = (int) $coupon->getId();
        $dto->code = $coupon->getCode();
        $dto->ruleId = (int) $coupon->getRuleId();
        $dto->ruleName = $rule->getName();
        $dto->timesUsed = (int) $coupon->getTimesUsed();

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

        $conditions = $rule->getConditions();
        if ($conditions) {
            foreach ($conditions->getConditions() as $condition) {
                if ($condition->getAttribute() === 'base_subtotal' && $condition->getOperator() === '>=') {
                    $dto->minimumSubtotal = (float) $condition->getValue();
                    break;
                }
            }
        }

        return $dto;
    }
}
