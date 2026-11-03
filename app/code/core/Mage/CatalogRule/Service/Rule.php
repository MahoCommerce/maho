<?php

/**
 * Load, save and delete catalog price rules, and apply them to the product prices.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

use Mage_CatalogRule_Model_Rule as Rule;

class Mage_CatalogRule_Service_Rule
{
    /**
     * Return an unsaved rule with the default values of a new rule. A new rule is inactive, so that it does not
     * apply before it is complete.
     */
    public function newRule(): Rule
    {
        /** @var Rule $rule */
        $rule = Mage::getModel('catalogrule/rule');
        return $rule->setIsActive(false)
            ->setSimpleAction('by_percent')
            ->setSortOrder(0)
            ->setStopRulesProcessing(false)
            ->addData(['discount_amount' => 0, 'sub_is_enable' => 0, 'sub_simple_action' => null, 'sub_discount_amount' => 0]);
    }

    /**
     * Load the rule $id. With $websiteIds, a rule that has none of these websites does not exist for the caller.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?array $websiteIds = null): Rule
    {
        /** @var Rule $rule */
        $rule = Mage::getModel('catalogrule/rule');
        if ($id > 0) {
            $rule->load($id);
        }
        if (!$rule->getId()
            || ($websiteIds !== null && array_intersect(array_map(intval(...), (array) $rule->getWebsiteIds()), $websiteIds) === [])
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalogrule')->__('Catalog price rule not found'));
        }
        return $rule;
    }

    /**
     * Save $rule, and mark the rules as changed: the product prices change only when the rules are applied.
     * The transport puts its input into the rule first. $inputErrors holds the values that the transport could
     * not read: they come back together with the problems of the rule.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem
     */
    public function save(Rule $rule, ?Mage_Core_Exception_Input $inputErrors = null): Rule
    {
        if ($inputErrors !== null && $inputErrors->getErrors() !== []) {
            $rule->validateRecord($inputErrors)->throwIfErrors();
        }
        $rule->save();
        $this->markChanged();
        return $rule;
    }

    /**
     * Delete $rule, and mark the rules as changed: its discounts stay in the product prices until the rules are applied.
     */
    public function delete(Rule $rule): void
    {
        $rule->delete();
        $this->markChanged();
    }

    /**
     * Apply all rules to the product prices now, and refresh the price index. This can take a long time.
     *
     * @throws RuntimeException when another run does not end in time
     */
    public function apply(): void
    {
        Mage::getModel('catalogrule/rule')->applyAll();
    }

    /**
     * Apply all rules in the background. The result goes to the inbox of $adminUserId.
     */
    public function queueApply(?int $adminUserId = null): void
    {
        Mage::getModel('catalogrule/rule')->queueApplyAll($adminUserId);
    }

    /**
     * The admin shows a notice until the rules are applied again.
     */
    private function markChanged(): void
    {
        Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();
    }
}
