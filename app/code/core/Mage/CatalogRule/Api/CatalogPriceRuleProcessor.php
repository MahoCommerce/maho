<?php

/**
 * Read a catalog price rule from the request body, and give the write and the apply to the catalog price rule service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\PriceRuleFieldsTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

final class CatalogPriceRuleProcessor extends \Maho\ApiPlatform\Processor
{
    use ConditionMetadataTrait;
    use PriceRuleFieldsTrait;

    private const FIELDS = [
        'name' => ['name', 'text'],
        'description' => ['description', 'text?'],
        'isActive' => ['is_active', 'bool'],
        'websiteIds' => ['website_ids', 'ids'],
        'customerGroupIds' => ['customer_group_ids', 'ids'],
        'fromDate' => ['from_date', 'date'],
        'toDate' => ['to_date', 'date'],
        'sortOrder' => ['sort_order', 'int'],
        'simpleAction' => ['simple_action', 'text'],
        'discountAmount' => ['discount_amount', 'number'],
        'subIsEnable' => ['sub_is_enable', 'bool'],
        'subSimpleAction' => ['sub_simple_action', 'text?'],
        'subDiscountAmount' => ['sub_discount_amount', 'number'],
        'stopRulesProcessing' => ['stop_rules_processing', 'bool'],
    ];

    /**
     * The keys that process() reads itself, and the read-only keys that a client can send back from a rule it read.
     */
    private const OTHER_FIELDS = ['conditions', 'id'];

    public function __construct(
        Security $security,
        private readonly CatalogPriceRuleProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CatalogPriceRule|Response|null
    {
        $user = $this->requireUser();
        /** @var \Mage_CatalogRule_Service_Rule $service */
        $service = \Mage::getService('catalogrule/rule');

        if ($operation->getName() === 'apply_catalog_price_rules') {
            $this->assertUnrestricted($user);
            set_time_limit(0);
            $service->apply();
            $this->logApiActivity('catalog_price_rule', 'apply', null, null);
            return $this->respondRaw(['success' => true]);
        }

        $locale = $this->adminLocale();
        // Labels of the response tree come from condition models that the write creates, so the write runs in the locale too
        return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function () use ($operation, $uriVariables, $context, $user, $locale, $service): ?CatalogPriceRule {
            $rule = isset($uriVariables['id']) ? $this->provider->loadWritableRule((int) $uriVariables['id'], $user) : $service->newRule();
            $oldData = $rule->getId() ? $rule->getData() : null;

            if ($operation instanceof DeleteOperationInterface) {
                $service->delete($rule);
                $this->logApiActivity('catalog_price_rule', 'delete', $oldData, null);
                return null;
            }

            $body = $this->parseRequestBody($context['request'] ?? null);
            $errors = new \Mage_Core_Exception_Input();
            $this->readRuleFields($rule, $body, self::FIELDS, self::OTHER_FIELDS, $errors);
            $this->readRuleTrees($rule, $body, ['conditions'], $locale, $errors);
            $service->save($rule, $errors);
            $this->logApiActivity('catalog_price_rule', $oldData === null ? 'create' : 'update', $oldData, $rule);

            return $this->provider->toRuleDto($service->getById((int) $rule->getId()), true);
        });
    }
}
