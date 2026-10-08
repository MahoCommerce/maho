<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

namespace Mage\CatalogRule\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\PriceRuleFieldsTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

final class CatalogPriceRuleProcessor extends \Maho\ApiPlatform\Processor
{
    use ConditionMetadataTrait;
    use PriceRuleFieldsTrait;

    private const WRITABLE_FIELDS = [
        'name', 'description', 'isActive', 'websiteIds', 'customerGroupIds', 'fromDate', 'toDate', 'sortOrder',
        'simpleAction', 'discountAmount', 'subIsEnable', 'subSimpleAction', 'subDiscountAmount', 'stopRulesProcessing',
        'conditions',
    ];

    /**
     * A client can send back a rule that it read, so these keys do not cause an error.
     */
    private const READ_ONLY_FIELDS = ['id', 'extensions', '@context', '@id', '@type'];

    private const BOOLEAN_FIELDS = [
        'isActive' => 'is_active',
        'subIsEnable' => 'sub_is_enable',
        'stopRulesProcessing' => 'stop_rules_processing',
    ];

    private const PERCENT_ACTIONS = ['by_percent', 'to_percent'];

    private \Mage_Core_Exception_Input $errors;

    public function __construct(
        Security $security,
        private readonly CatalogPriceRuleProvider $provider,
    ) {
        parent::__construct($security);
        $this->errors = new \Mage_Core_Exception_Input();
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CatalogPriceRule|Response|null
    {
        $user = $this->requireUser();

        if ($operation->getName() === 'apply_catalog_price_rules') {
            return $this->applyAll($user);
        }

        $locale = $this->adminLocale();

        // Labels of the response tree come from condition models that the write creates, so the write runs in the locale too
        return \Mage_Rule_Model_Condition_Metadata::runInLocale($locale, function () use ($operation, $uriVariables, $context, $user, $locale): ?CatalogPriceRule {
            if ($operation instanceof DeleteOperationInterface) {
                $this->delete((int) ($uriVariables['id'] ?? 0), $user);
                return null;
            }

            $body = $this->parseRequestBody($context['request'] ?? null);
            return isset($uriVariables['id'])
                ? $this->update((int) $uriVariables['id'], $body, $user, $locale)
                : $this->create($body, $locale);
        });
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body, string $locale): CatalogPriceRule
    {
        /** @var \Mage_CatalogRule_Model_Rule $rule */
        $rule = \Mage::getModel('catalogrule/rule');
        $rule->setIsActive(false)
            ->setSimpleAction('by_percent')
            ->setSortOrder(0)
            ->setStopRulesProcessing(false);
        $rule->setData('discount_amount', 0);
        $rule->setData('sub_is_enable', 0);
        $rule->setData('sub_simple_action');
        $rule->setData('sub_discount_amount', 0);

        $this->errors = new \Mage_Core_Exception_Input();
        foreach (['name', 'websiteIds', 'customerGroupIds'] as $field) {
            if (!array_key_exists($field, $body)) {
                $this->addError($field, "{$field} is required");
            }
        }
        $this->applyFields($rule, $body);
        $this->applyTree($rule, $body, $locale, true);
        $this->errors->throwIfErrors();

        $this->safeSave($rule, 'create catalog price rule');
        $this->markRulesDirty();
        $this->logApiActivity('catalog_price_rule', 'create', null, $rule);

        return $this->provider->toRuleDto($this->provider->loadRule((int) $rule->getId()), true);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function update(int $id, array $body, ApiUser $user, string $locale): CatalogPriceRule
    {
        $rule = $this->loadWritableRule($id, $user);
        $oldData = $rule->getData();

        $this->errors = new \Mage_Core_Exception_Input();
        $this->applyFields($rule, $body);
        $this->applyTree($rule, $body, $locale, false);
        $this->errors->throwIfErrors();

        $this->safeSave($rule, 'update catalog price rule');
        $this->markRulesDirty();
        $this->logApiActivity('catalog_price_rule', 'update', $oldData, $rule);

        return $this->provider->toRuleDto($this->provider->loadRule($id), true);
    }

    private function delete(int $id, ApiUser $user): void
    {
        $rule = $this->loadWritableRule($id, $user);
        $oldData = $rule->getData();
        $this->safeDelete($rule, 'delete catalog price rule');
        $this->markRulesDirty();
        $this->logApiActivity('catalog_price_rule', 'delete', $oldData, null);
    }

    /**
     * Run every rule against the catalog, as the "Apply Rules" button of the admin does.
     */
    private function applyAll(ApiUser $user): Response
    {
        if ($this->allowedWebsiteIds($user) !== null) {
            throw new AccessDeniedHttpException('A token with a store restriction cannot apply catalog price rules');
        }

        set_time_limit(0);
        try {
            \Mage::getModel('catalogrule/rule')->applyAll();
            \Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();
        } catch (\Throwable $e) {
            \Mage::logException($e instanceof \Exception ? $e : new \Exception($e->getMessage(), 0, $e));
            $detail = $e instanceof \Mage_Core_Exception ? ': ' . $e->getMessage() : '';
            throw new UnprocessableEntityHttpException('Unable to apply the catalog price rules' . $detail);
        }
        $this->logApiActivity('catalog_price_rule', 'apply', null, null);

        return $this->respondRaw(['success' => true]);
    }

    /**
     * A restricted token changes only a rule whose websites are all in its scope.
     */
    private function loadWritableRule(int $id, ApiUser $user): \Mage_CatalogRule_Model_Rule
    {
        $rule = $this->provider->loadRule($id);
        $this->provider->assertRuleReadable($rule, $user);
        $this->assertAllWebsitesAllowed((array) $rule->getWebsiteIds(), $user, 'catalog price rule');
        return $rule;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyFields(\Mage_CatalogRule_Model_Rule $rule, array $body): void
    {
        $unknown = array_diff(array_keys($body), self::WRITABLE_FIELDS, self::READ_ONLY_FIELDS);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
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

        foreach (self::BOOLEAN_FIELDS as $field => $column) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if (!is_bool($value) && $value !== 0 && $value !== 1) {
                    $this->addError($field, "{$field} must be true or false");
                } else {
                    $rule->setData($column, (int) (bool) $value);
                }
            }
        }

        if (array_key_exists('sortOrder', $body)) {
            $value = $this->readInteger($body['sortOrder']);
            if ($value === null || $value < 0) {
                $this->addError('sortOrder', 'sortOrder must be an integer of 0 or more');
            } else {
                $rule->setSortOrder($value);
            }
        }

        $this->collect(function () use ($rule, $body): void {
            if (array_key_exists('websiteIds', $body)) {
                $rule->setData('website_ids', $this->normalizeWebsiteIds($body['websiteIds']));
            }
        });
        $this->collect(function () use ($rule, $body): void {
            if (array_key_exists('customerGroupIds', $body)) {
                $rule->setData('customer_group_ids', $this->normalizeCustomerGroupIds($body['customerGroupIds']));
            }
        });

        foreach (['fromDate' => 'from_date', 'toDate' => 'to_date'] as $field => $column) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if ($value === null || $value === '') {
                    $rule->setData($column);
                } elseif (!is_string($value) || !\Mage::helper('core')->isValidDate($value)) {
                    $this->addError($field, "{$field} must be a date in the format YYYY-MM-DD or null");
                } else {
                    $rule->setData($column, $value);
                }
            }
        }
        $fromDate = $rule->getFromDate() ? substr($rule->getFromDate(), 0, 10) : null;
        $toDate = $rule->getToDate() ? substr($rule->getToDate(), 0, 10) : null;
        if ($fromDate !== null && $toDate !== null && $fromDate > $toDate) {
            $this->addError(array_key_exists('toDate', $body) ? 'toDate' : 'fromDate', 'fromDate must not be after toDate');
        }

        $this->applyAction($rule, $body, 'simpleAction', 'discountAmount', 'simple_action', 'discount_amount', false);
        $this->applyAction($rule, $body, 'subSimpleAction', 'subDiscountAmount', 'sub_simple_action', 'sub_discount_amount', true);
    }

    /**
     * Check an action and its amount together, because the limit of the amount depends on the action.
     *
     * @param array<string, mixed> $body
     */
    private function applyAction(\Mage_CatalogRule_Model_Rule $rule, array $body, string $actionField, string $amountField, string $actionColumn, string $amountColumn, bool $nullable): void
    {
        if (array_key_exists($actionField, $body)) {
            $action = $body[$actionField];
            if ($nullable && ($action === null || $action === '')) {
                $rule->setData($actionColumn);
            } elseif (!in_array($action, CatalogPriceRule::SIMPLE_ACTIONS, true)) {
                $this->addError($actionField, "{$actionField} must be one of: " . implode(', ', CatalogPriceRule::SIMPLE_ACTIONS));
            } else {
                $rule->setData($actionColumn, $action);
            }
        }

        if (array_key_exists($amountField, $body)) {
            $amount = $this->readAmount($body[$amountField]);
            if ($amount === null) {
                $this->addError($amountField, "{$amountField} must be a number of 0 or more");
            } else {
                $rule->setData($amountColumn, $amount);
            }
        }

        if (in_array($rule->getData($actionColumn), self::PERCENT_ACTIONS, true) && (float) $rule->getData($amountColumn) > 100) {
            $this->addError($amountField, "{$amountField} must not be more than 100 for " . implode(' and ', self::PERCENT_ACTIONS));
        }
    }

    /**
     * Check the conditions tree in $body and put it into $rule. The stored tree of an update lets old values pass.
     *
     * @param array<string, mixed> $body
     */
    private function applyTree(\Mage_CatalogRule_Model_Rule $rule, array $body, string $locale, bool $isNew): void
    {
        if (!array_key_exists('conditions', $body)) {
            return;
        }

        $document = $this->conditionMetadata($locale);
        $validator = new \Mage_Rule_Model_Condition_TreeValidator($this->conditionMetadataModel(), $document);
        $writer = new \Mage_Rule_Model_Condition_TreeWriter($document);

        $tree = $body['conditions'] ?? ['type' => CatalogPriceRule::ROOT_CONDITIONS, 'aggregator' => 'all', 'value' => true, 'conditions' => []];
        $result = $validator->validate('conditions', $tree, $isNew ? null : $writer->readTree($rule, 'conditions'));
        foreach ($result['errors'] as $error) {
            $this->addError($error['field'], $error['message']);
        }

        if ($this->errors->getErrors() === [] && $result['tree'] !== null) {
            $writer->replaceTree($rule, 'conditions', $result['tree']);
        }
    }

    /**
     * The admin shows a notice until the rules are applied again.
     */
    private function markRulesDirty(): void
    {
        \Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();
    }

    private function readAmount(mixed $value): ?float
    {
        if (is_bool($value) || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        return is_finite($number) && $number >= 0 ? $number : null;
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
        $this->errors->addError($field, $message);
    }

}
