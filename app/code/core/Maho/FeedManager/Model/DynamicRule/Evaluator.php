<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Evaluator
 *
 * Evaluates a dynamic rule against a product using the standard Maho rules engine.
 */
class Maho_FeedManager_Model_DynamicRule_Evaluator
{
    protected Maho_FeedManager_Model_DynamicRule $_rule;

    public function __construct(Maho_FeedManager_Model_DynamicRule $rule)
    {
        $this->_rule = $rule;
    }

    /**
     * Evaluate the rule against a product
     *
     * @param array $rawData Product data array (for backward compatibility)
     * @param Mage_Catalog_Model_Product|null $product Product model
     * @return mixed The computed output value, or null if conditions don't match
     */
    public function evaluate(array $rawData, ?Mage_Catalog_Model_Product $product = null): mixed
    {
        if (!$this->_rule->isEnabled()) {
            return null;
        }

        // If we have a product model, use the new evaluation method
        if ($product) {
            return $this->_rule->evaluate($product);
        }

        // For raw data array, create a temporary data object for validation
        $dataObject = new \Maho\DataObject($rawData);

        // Check if conditions match
        if (!$this->_rule->getConditions()->validate($dataObject)) {
            return null;
        }

        // Return appropriate output based on type
        return match ($this->_rule->getOutputType()) {
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC => $this->_rule->getOutputValue(),
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_ATTRIBUTE => $rawData[$this->_rule->getOutputAttribute()] ?? null,
            Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_COMBINED => $this->_rule->getOutputValue() . ($rawData[$this->_rule->getOutputAttribute()] ?? ''),
            default => null,
        };
    }

    /**
     * Get supported operators for UI (kept for backward compatibility)
     */
    public static function getOperatorOptions(): array
    {
        return [
            'eq' => Mage::helper('feedmanager')->__('Equals'),
            'neq' => Mage::helper('feedmanager')->__('Not Equals'),
            'gt' => Mage::helper('feedmanager')->__('Greater Than'),
            'gteq' => Mage::helper('feedmanager')->__('Greater or Equal'),
            'lt' => Mage::helper('feedmanager')->__('Less Than'),
            'lteq' => Mage::helper('feedmanager')->__('Less or Equal'),
            '{}' => Mage::helper('feedmanager')->__('Contains'),
            '!{}' => Mage::helper('feedmanager')->__('Does Not Contain'),
            '()' => Mage::helper('feedmanager')->__('Is One Of'),
            '!()' => Mage::helper('feedmanager')->__('Is Not One Of'),
        ];
    }
}
