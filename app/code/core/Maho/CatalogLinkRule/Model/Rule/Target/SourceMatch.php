<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Target Product Source Match Condition
 *
 * Allows matching target products based on source product attributes
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Rule_Target_SourceMatch extends Mage_Rule_Model_Condition_Abstract
{
    /**
     * Initialize the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_target_sourceMatch');
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    #[\Override]
    public function loadAttributeOptions()
    {
        $attributes = [
            'category_ids' => Mage::helper('cataloglinkrule')->__('Category'),
            'attribute_set_id' => Mage::helper('cataloglinkrule')->__('Attribute Set'),
        ];

        // Add all product attributes
        $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('is_visible', 1)
            ->setOrder('frontend_label', 'ASC');

        foreach ($productAttributes as $attribute) {
            $attributes[$attribute->getAttributeCode()] = $attribute->getFrontendLabel();
        }

        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Get input type
     *
     * @return string
     */
    #[\Override]
    public function getInputType()
    {
        return 'select';
    }

    /**
     * Get value element type
     *
     * @return string
     */
    #[\Override]
    public function getValueElementType()
    {
        return 'select';
    }

    /**
     * Load operator options
     *
     * @return $this
     */
    #[\Override]
    public function loadOperatorOptions()
    {
        $this->setOperatorOption([
            '==' => Mage::helper('cataloglinkrule')->__('matches source'),
            '!=' => Mage::helper('cataloglinkrule')->__('does not match source'),
        ]);
        return $this;
    }

    /**
     * Get value select options (not used for source matching)
     *
     * @return array
     */
    #[\Override]
    public function getValueSelectOptions()
    {
        return [];
    }

    /**
     * Collect validated attributes
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        $attribute = $this->getAttribute();
        if ($attribute === 'category_ids') {
            $productCollection->addCategoryIds();
        } elseif ($attribute !== 'attribute_set_id') {
            $productCollection->addAttributeToSelect($attribute);
        }
        return $this;
    }

    /**
     * Validate product against source product
     *
     * @param Maho\DataObject $object Target product
     */
    #[\Override]
    public function validate(Maho\DataObject $object): bool
    {
        $sourceProduct = $this->getRule()->getSourceProduct();

        if (!$sourceProduct) {
            // If no source product is set, we can't validate
            return false;
        }

        $attributeCode = $this->getAttribute();
        $operator = $this->getOperator();

        // Special handling for category_ids
        if ($attributeCode === 'category_ids') {
            return $this->validateCategoryMatch($object, $sourceProduct, $operator);
        }

        // Get values from both products
        $targetValue = $object->getData($attributeCode);
        $sourceValue = $sourceProduct->getData($attributeCode);
        // Compare values
        if ($operator === '==') {
            return $targetValue == $sourceValue;
        }

        if ($operator === '!=') {
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
        if ($operator === '==') {
            return $hasCommonCategory;
        }

        if ($operator === '!=') {
            return !$hasCommonCategory;
        }

        return false;
    }

    /**
     * Get value element HTML
     */
    #[\Override]
    public function getValueElementHtml(): string
    {
        // No value input needed for source matching
        return '';
    }

    public function getValueAfterElementHtml(): string
    {
        return '<div style="margin-top: 5px; font-style: italic; color: #666;">'
            . Mage::helper('cataloglinkrule')->__('Target product attribute will be compared to source product')
            . '</div>';
    }

    #[\Override]
    public function asArray(array $arrAttributes = []): array
    {
        $out = parent::asArray($arrAttributes);
        $out['value'] = ''; // No value for source matching
        return $out;
    }
}
