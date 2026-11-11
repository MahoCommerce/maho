<?php

/**
 * Read a cart price rule from the request body, and give the write to the cart price rule service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\PriceRuleFieldsTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class CartPriceRuleProcessor extends \Maho\ApiPlatform\Processor
{
    use ConditionMetadataTrait;
    use PriceRuleFieldsTrait;

    private const FIELDS = [
        'name' => ['name', 'text'],
        'description' => ['description', 'text?'],
        'isActive' => ['is_active', 'bool'],
        'websiteIds' => ['website_ids', 'ids'],
        'customerGroupIds' => ['customer_group_ids', 'ids'],
        'usesPerCoupon' => ['uses_per_coupon', 'int'],
        'usesPerCustomer' => ['uses_per_customer', 'int'],
        'fromDate' => ['from_date', 'date'],
        'toDate' => ['to_date', 'date'],
        'sortOrder' => ['sort_order', 'int'],
        'stopRulesProcessing' => ['stop_rules_processing', 'bool'],
        'isRss' => ['is_rss', 'bool'],
        'simpleAction' => ['simple_action', 'text'],
        'discountAmount' => ['discount_amount', 'number'],
        'discountQty' => ['discount_qty', 'number?'],
        'discountStep' => ['discount_step', 'int'],
        'applyToShipping' => ['apply_to_shipping', 'bool'],
        'simpleFreeShipping' => ['simple_free_shipping', 'int'],
    ];

    /**
     * The keys that process() reads itself, and the read-only keys that a client can send back from a rule it read.
     */
    private const OTHER_FIELDS = ['couponType', 'couponCode', 'storeLabels', 'conditions', 'actions', 'id', 'timesUsed', 'couponCount', 'primaryCouponId'];

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
        /** @var \Mage_SalesRule_Service_Rule $service */
        $service = \Mage::getService('salesrule/rule');

        // Labels of the response trees come from condition models that the write creates, so the write runs in the locale too
        return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function () use ($operation, $uriVariables, $context, $user, $locale, $service): ?CartPriceRule {
            $rule = isset($uriVariables['id']) ? $this->provider->loadWritableRule((int) $uriVariables['id'], $user) : $service->newRule();
            $oldData = $rule->getId() ? $rule->getData() : null;

            if ($operation instanceof DeleteOperationInterface) {
                $service->delete($rule);
                $this->logApiActivity('cart_price_rule', 'delete', $oldData, null);
                return null;
            }

            $body = $this->parseRequestBody($context['request'] ?? null);
            $errors = new \Mage_Core_Exception_Input();
            $this->readRuleFields($rule, $body, self::FIELDS, self::OTHER_FIELDS, $errors);
            $this->readCoupon($rule, $body, $errors);
            $this->readStoreLabels($rule, $body, $user, $errors);
            $this->readRuleTrees($rule, $body, ['conditions', 'actions'], $locale, $errors);
            try {
                $service->save($rule, $errors);
            } catch (\Mage_Core_Exception_Input $e) {
                // The coupon calls its code "code", and the client of a rule sends it as couponCode
                throw $e->withFieldNames(['code' => 'coupon_code']);
            }
            $this->logApiActivity('cart_price_rule', $oldData === null ? 'create' : 'update', $oldData, $rule);

            return $this->provider->toRuleDto($service->getById((int) $rule->getId()), true);
        });
    }

    /**
     * Turn couponType and couponCode into the coupon type, the auto generation flag and the code of $rule.
     *
     * @param array<string, mixed> $body
     */
    private function readCoupon(\Mage_SalesRule_Model_Rule $rule, array $body, \Mage_Core_Exception_Input $errors): void
    {
        if (!array_key_exists('couponType', $body) && !array_key_exists('couponCode', $body)) {
            return;
        }

        $type = $body['couponType'] ?? CartPriceRuleProvider::couponTypeOf($rule);
        $code = array_key_exists('couponCode', $body) ? $body['couponCode'] : ($type === CartPriceRule::COUPON_TYPE_SPECIFIC ? $rule->getCouponCode() : null);
        $code = is_string($code) ? trim($code) : $code;
        if (!in_array($type, [CartPriceRule::COUPON_TYPE_NONE, CartPriceRule::COUPON_TYPE_SPECIFIC, CartPriceRule::COUPON_TYPE_AUTO], true)) {
            $errors->addError('coupon_type', 'couponType must be one of: none, specific, auto');
        } elseif ($code !== null && !is_string($code)) {
            $errors->addError('coupon_code', 'couponCode must be a text or null');
        } elseif ($type !== CartPriceRule::COUPON_TYPE_SPECIFIC && (string) $code !== '') {
            $errors->addError('coupon_code', 'couponCode applies only to couponType "specific"');
        } else {
            $rule->setCouponType($type === CartPriceRule::COUPON_TYPE_NONE ? \Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON : \Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
                ->setUseAutoGeneration($type === CartPriceRule::COUPON_TYPE_AUTO)
                ->setCouponCode($type === CartPriceRule::COUPON_TYPE_SPECIFIC ? (string) $code : null);
        }
    }

    /**
     * Replace the labels of the stores that the caller can use. Keep the labels of the other stores.
     *
     * @param array<string, mixed> $body
     */
    private function readStoreLabels(\Mage_SalesRule_Model_Rule $rule, array $body, ApiUser $user, \Mage_Core_Exception_Input $errors): void
    {
        if (!array_key_exists('storeLabels', $body)) {
            return;
        }
        $input = $body['storeLabels'] ?? [];
        if (!is_array($input) || !array_is_list($input)) {
            $errors->addError('storeLabels', 'storeLabels must be a list of {storeId, label}');
            return;
        }

        $knownStoreIds = array_map(intval(...), array_keys(\Mage::app()->getStores(true)));
        $labels = [];
        foreach ($input as $index => $entry) {
            $field = "storeLabels[{$index}]";
            $storeId = is_array($entry) ? ($entry['storeId'] ?? null) : null;
            $label = is_array($entry) ? ($entry['label'] ?? null) : null;
            if (!is_int($storeId) || !in_array($storeId, $knownStoreIds, true)) {
                $errors->addError("{$field}.storeId", 'storeId must be the ID of a store, or 0 for the default label');
            } elseif (!is_string($label) || mb_strlen($label) > 255) {
                $errors->addError("{$field}.label", 'label must be a text of at most 255 characters');
            } elseif (array_key_exists($storeId, $labels)) {
                $errors->addError("{$field}.storeId", "storeId {$storeId} is in the list more than once");
            } else {
                $this->assertRequestedStoreAllowed($storeId, $user);
                $labels[$storeId] = trim($label);
            }
        }

        // An empty label deletes the stored label of that store
        foreach (array_keys($rule->getId() ? (array) $rule->getStoreLabels() : []) as $storeId) {
            if (!array_key_exists((int) $storeId, $labels) && $user->canAccessStore((int) $storeId)) {
                $labels[(int) $storeId] = '';
            }
        }
        $rule->setStoreLabels($labels);
    }
}
