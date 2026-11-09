<?php

/**
 * Give the coupon generation and the coupon deletes of a cart price rule to the cart price rule service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

final class CartPriceRuleCouponProcessor extends \Maho\ApiPlatform\Processor
{
    /** One request generates at most this many coupons */
    public const MAX_GENERATE_QTY = 1000;
    public const MAX_CODE_LENGTH = \Mage_SalesRule_Service_Rule::MAX_CODE_LENGTH;
    public const MAX_DELETE_IDS = 1000;

    private const GENERATE_FIELDS = ['qty', 'length', 'format', 'prefix', 'suffix', 'dash'];

    public function __construct(
        Security $security,
        private readonly CartPriceRuleProvider $ruleProvider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?JsonResponse
    {
        $rule = $this->ruleProvider->loadWritableRule((int) ($uriVariables['ruleId'] ?? 0), $this->requireUser());
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');

        return match ($operation->getName()) {
            'cart_price_rule_coupons_generate' => $this->generate($service, $rule, $this->parseRequestBody($context['request'] ?? null)),
            'cart_price_rule_coupons_mass_delete' => $this->massDelete($service, $rule, $this->parseRequestBody($context['request'] ?? null)),
            default => $this->deleteOne($service, $rule, (int) ($uriVariables['couponId'] ?? 0)),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function generate(\Mage_SalesRule_Service_Rule $service, \Mage_SalesRule_Model_Rule $rule, array $body): JsonResponse
    {
        $unknown = array_diff(array_keys($body), self::GENERATE_FIELDS);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_values($unknown));
        }
        $settings = [];
        foreach (self::GENERATE_FIELDS as $field) {
            $value = $body[$field] ?? null;
            if (in_array($field, ['format', 'prefix', 'suffix'], true)) {
                if ($value !== null && !is_string($value)) {
                    throw new ValidationException("{$field} must be a text", $field);
                }
                $settings[$field] = $value;
            } else {
                // A value that is not an integer is out of range for the service
                $settings[$field] = $value === null && $field !== 'qty' ? null : ($this->readInteger($value) ?? -1);
            }
        }
        if ($settings['qty'] > self::MAX_GENERATE_QTY) {
            throw new ValidationException(sprintf('qty must be an integer from 1 to %d', self::MAX_GENERATE_QTY), 'qty');
        }

        $count = $service->generateCoupons($rule, ...$settings);
        /** @var \Mage_SalesRule_Model_Resource_Coupon_Collection $collection */
        $collection = \Mage::getResourceModel('salesrule/coupon_collection');
        $collection->addRuleToFilter($rule)->addGeneratedCouponsFilter();
        $collection->getSelect()->order('main_table.coupon_id DESC')->limit($count);
        $coupons = [];
        foreach ($collection as $coupon) {
            $coupons[] = CartPriceRuleCoupon::fromCoupon($coupon)->toArray();
        }
        $this->logApiActivity('cart_price_rule', 'generate_coupons', null, $rule);

        return $this->respondRaw(['generatedCount' => $count, 'coupons' => $coupons], JsonResponse::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function massDelete(\Mage_SalesRule_Service_Rule $service, \Mage_SalesRule_Model_Rule $rule, array $body): JsonResponse
    {
        $ids = $body['ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids) || $ids === [] || count($ids) > self::MAX_DELETE_IDS) {
            throw new ValidationException(sprintf('ids must be a list of 1 to %d coupon IDs', self::MAX_DELETE_IDS), 'ids');
        }
        $couponIds = [];
        foreach ($ids as $index => $id) {
            $couponIds[] = $this->readInteger($id) ?? 0;
            if (end($couponIds) < 1) {
                throw new ValidationException('Each ID must be a positive integer', "ids[{$index}]");
            }
        }

        $deleted = $service->deleteCoupons($rule, $couponIds);
        $this->logApiActivity('cart_price_rule', 'delete_coupons', null, $rule);
        return $this->respondRaw(['deletedCount' => $deleted]);
    }

    private function deleteOne(\Mage_SalesRule_Service_Rule $service, \Mage_SalesRule_Model_Rule $rule, int $couponId): null
    {
        $coupon = $service->deleteGeneratedCoupon($rule, $couponId);
        $this->logApiActivity('coupon', 'delete', $coupon->getData(), null);
        return null;
    }

    private function readInteger(mixed $value): ?int
    {
        $number = is_bool($value) || !is_scalar($value) ? false : filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? null : $number;
    }
}
