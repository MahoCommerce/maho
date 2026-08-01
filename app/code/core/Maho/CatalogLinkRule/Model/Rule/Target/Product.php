<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Rule_Target_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    /**
     * Operators that compare the target product against the source product instead of a fixed
     * value. A condition using one of these makes the whole rule source-dependent, so
     * Maho_CatalogLinkRule_Model_Rule reads this list too: keep it the single source of truth.
     */
    public const SOURCE_MATCH_OPERATORS = ['~=', '~!'];

    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_target_product');
    }

    /**
     * Add status and visibility to special attributes for target products
     */
    #[\Override]
    protected function _addSpecialAttributes(array &$attributes): void
    {
        parent::_addSpecialAttributes($attributes);
        $attributes['status'] = Mage::helper('cataloglinkrule')->__('Status');
        $attributes['visibility'] = Mage::helper('cataloglinkrule')->__('Visibility');
    }

    /**
     * Add source matching operators to default operator options
     */
    #[\Override]
    public function getDefaultOperatorOptions(): array
    {
        $options = parent::getDefaultOperatorOptions();
        $options['~='] = Mage::helper('cataloglinkrule')->__('matches source');
        $options['~!'] = Mage::helper('cataloglinkrule')->__('does not match source');
        return $options;
    }

    /**
     * Whether this condition compares the target product against the source product.
     */
    public function isSourceMatchOperator(): bool
    {
        return in_array((string) $this->getOperator(), self::SOURCE_MATCH_OPERATORS, true);
    }

    /**
     * Add source matching operators to all input types
     */
    #[\Override]
    public function getDefaultOperatorInputByType(): array
    {
        $operators = parent::getDefaultOperatorInputByType();
        // Add source matching operators to all input types
        foreach (array_keys($operators) as $type) {
            foreach (self::SOURCE_MATCH_OPERATORS as $sourceMatchOperator) {
                $operators[$type][] = $sourceMatchOperator;
            }
        }
        return $operators;
    }

    /**
     * Override validate to handle source matching operators
     */
    #[\Override]
    public function validate(Maho\DataObject $object): bool
    {
        $operator = (string) $this->getOperator();

        // Handle source matching operators
        if ($this->isSourceMatchOperator()) {
            return $this->validateSourceMatch($object, $operator);
        }

        // Default validation for normal operators
        return parent::validate($object);
    }

    /**
     * Validate product against source product
     */
    protected function validateSourceMatch(Maho\DataObject $targetProduct, string $operator): bool
    {
        $sourceProduct = $this->getRule()->getSourceProduct();

        if (!$sourceProduct) {
            return false;
        }

        $attributeCode = $this->getAttribute();

        // Special handling for category_ids
        if ($attributeCode === 'category_ids') {
            return $this->validateCategoryMatch($targetProduct, $sourceProduct, $operator);
        }

        // Get values from both products
        $targetValue = $targetProduct->getData($attributeCode);
        $sourceValue = $sourceProduct->getData($attributeCode);
        // Compare values
        if ($operator === '~=') {
            return $targetValue == $sourceValue;
        }

        if ($operator === '~!') {
            return $targetValue != $sourceValue;
        }

        return false;
    }

    /**
     * Validate category match
     */
    protected function validateCategoryMatch(Maho\DataObject $targetProduct, Maho\DataObject $sourceProduct, string $operator): bool
    {
        $targetCategories = $targetProduct->getCategoryIds();
        $sourceCategories = $sourceProduct->getCategoryIds();

        if (!is_array($targetCategories)) {
            $targetCategories = $targetProduct->getCategoryCollection()->getAllIds();
        }
        if (!is_array($sourceCategories)) {
            $sourceCategories = $sourceProduct->getCategoryCollection()->getAllIds();
        }

        // Check if they share at least one category
        $hasCommonCategory = !empty(array_intersect($targetCategories, $sourceCategories));
        if ($operator === '~=') {
            return $hasCommonCategory;
        }

        if ($operator === '~!') {
            return !$hasCommonCategory;
        }

        return false;
    }

    /**
     * Hide value input for source matching operators
     */
    #[\Override]
    public function getValueElementHtml(): string
    {
        // No value input needed for source matching
        if ($this->isSourceMatchOperator()) {
            return '';
        }

        return parent::getValueElementHtml();
    }

    /**
     * Add explanatory text for source matching operators
     */
    #[\Override]
    public function getValueAfterElementHtml(): string
    {
        if ($this->isSourceMatchOperator()) {
            return '<div style="margin-top: 5px; font-style: italic; color: #666;">'
                . Mage::helper('cataloglinkrule')->__('Target product attribute will be compared to source product')
                . '</div>';
        }

        return parent::getValueAfterElementHtml();
    }

    /**
     * Don't include value in array output for source matching operators
     */
    #[\Override]
    public function asArray(array $arrAttributes = []): array
    {
        $out = parent::asArray($arrAttributes);

        if ($this->isSourceMatchOperator()) {
            $out['value'] = ''; // No value for source matching
        }

        return $out;
    }
}
