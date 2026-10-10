<?php

/**
 * Give the condition metadata of cart price rules to the providers and processors of the cart price rule API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

trait ConditionMetadataTrait
{
    use \Maho\ApiPlatform\Trait\ConditionMetadataTrait;

    private ?\Mage_SalesRule_Model_Rule_Condition_Metadata $conditionMetadataModel = null;

    #[\Override]
    protected function conditionMetadataModel(): \Mage_SalesRule_Model_Rule_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= \Mage::getModel('salesrule/rule_condition_metadata');
    }

    #[\Override]
    protected function conditionMetadataCacheKey(): string
    {
        return 'API_CART_PRICE_RULE_CONDITION_METADATA';
    }

    /**
     * @return array<string, mixed>
     */
    protected function extraConditionMetadata(): array
    {
        return ['rule' => $this->ruleOptions()];
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleOptions(): array
    {
        $salesRule = \Mage::helper('salesrule');
        /** @var \Mage_SalesRule_Helper_Coupon $couponHelper */
        $couponHelper = \Mage::helper('salesrule/coupon');

        $formats = [];
        foreach ($couponHelper->getFormatsList() as $value => $label) {
            $formats[] = ['value' => (string) $value, 'label' => (string) $label];
        }
        // The shipped configuration has the format "1", which is not a format code
        $defaultFormat = (string) $couponHelper->getDefaultFormat();
        if (!array_key_exists($defaultFormat, $couponHelper->getFormatsList())) {
            $defaultFormat = \Mage_SalesRule_Helper_Coupon::COUPON_FORMAT_ALPHANUMERIC;
        }

        return [
            'simpleAction' => [
                ['value' => \Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION, 'label' => $salesRule->__('Percent of product price discount')],
                ['value' => \Mage_SalesRule_Model_Rule::BY_FIXED_ACTION, 'label' => $salesRule->__('Fixed amount discount')],
                ['value' => \Mage_SalesRule_Model_Rule::CART_FIXED_ACTION, 'label' => $salesRule->__('Fixed amount discount for whole cart')],
                ['value' => \Mage_SalesRule_Model_Rule::BUY_X_GET_Y_ACTION, 'label' => $salesRule->__('Buy X get Y free (discount amount is Y)')],
            ],
            'couponType' => [
                ['value' => 'none', 'label' => $salesRule->__('No Coupon')],
                ['value' => 'specific', 'label' => $salesRule->__('Specific Coupon')],
                ['value' => 'auto', 'label' => $salesRule->__('Auto')],
            ],
            'simpleFreeShipping' => [
                ['value' => 0, 'label' => $salesRule->__('No')],
                ['value' => \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM, 'label' => $salesRule->__('For matching items only')],
                ['value' => \Mage_SalesRule_Model_Rule::FREE_SHIPPING_ADDRESS, 'label' => $salesRule->__('For shipment with matching items')],
            ],
            'couponFormats' => $formats,
            'couponDefaults' => [
                'length' => (int) $couponHelper->getDefaultLength(),
                'format' => $defaultFormat,
                'prefix' => (string) $couponHelper->getDefaultPrefix(),
                'suffix' => (string) $couponHelper->getDefaultSuffix(),
                'dash' => (int) $couponHelper->getDefaultDashInterval(),
            ],
        ];
    }
}
