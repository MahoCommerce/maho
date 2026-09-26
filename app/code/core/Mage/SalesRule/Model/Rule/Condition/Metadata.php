<?php

/**
 * Describe the condition types of cart price rules.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

class Mage_SalesRule_Model_Rule_Condition_Metadata extends Mage_Rule_Model_Condition_Metadata
{
    public const ROOT_CONDITIONS = 'salesrule/rule_condition_combine';
    public const ROOT_ACTIONS = 'salesrule/rule_condition_product_combine';

    /**
     * @param array{inline_option_limit?: int} $args
     */
    public function __construct(array $args = [])
    {
        parent::__construct(
            Mage::getModel('salesrule/rule'),
            ['conditions' => self::ROOT_CONDITIONS, 'actions' => self::ROOT_ACTIONS],
            'salesrule',
            (int) ($args['inline_option_limit'] ?? self::DEFAULT_INLINE_OPTION_LIMIT),
        );
    }

    #[\Override]
    protected function getSentence(Mage_Rule_Model_Condition_Combine $combine): string
    {
        return match (true) {
            $combine instanceof Mage_SalesRule_Model_Rule_Condition_Product_Subselect => Mage::helper('salesrule')->__(
                'If %s %s %s for a subselection of items in cart matching %s of these conditions:',
                '{attribute}',
                '{operator}',
                '{value}',
                '{aggregator}',
            ),
            $combine instanceof Mage_SalesRule_Model_Rule_Condition_Product_Found => Mage::helper('salesrule')->__(
                'If an item is %s in the cart with %s of these conditions true:',
                '{value}',
                '{aggregator}',
            ),
            default => parent::getSentence($combine),
        };
    }

    /**
     * The cart item attributes hold quantities and amounts, but Maho gives them the input type "string".
     */
    #[\Override]
    protected function hasNumericValue(Mage_Rule_Model_Condition_Abstract $condition, string $code): bool
    {
        return str_starts_with($code, 'quote_item_') || parent::hasNumericValue($condition, $code);
    }
}
