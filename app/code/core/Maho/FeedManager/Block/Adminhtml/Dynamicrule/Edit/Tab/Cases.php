<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Cases Tab
 *
 * Multi-case editor with conditions and inline output configuration.
 */
class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tab_Cases extends Mage_Adminhtml_Block_Template
{
    protected $_template = 'maho/feedmanager/dynamicrule/cases.phtml';

    /**
     * Get the current rule
     */
    public function getRule(): Maho_FeedManager_Model_DynamicRule
    {
        return Mage::registry('current_dynamic_rule') ?: Mage::getModel('feedmanager/dynamicRule');
    }

    /**
     * Get cases from the rule
     */
    public function getCases(): array
    {
        $cases = $this->getRule()->getCases();

        // Ensure we have at least one case
        if (empty($cases)) {
            $cases = [
                [
                    'conditions' => null,
                    'output_type' => Maho_FeedManager_Model_DynamicRule::OUTPUT_TYPE_STATIC,
                    'output_value' => '',
                    'output_attribute' => '',
                    'combined_position' => Maho_FeedManager_Model_DynamicRule::COMBINED_POSITION_PREFIX,
                    'is_default' => true,
                ],
            ];
        }

        return $cases;
    }

    /**
     * Get output type options
     */
    public function getOutputTypeOptions(): array
    {
        return Maho_FeedManager_Model_DynamicRule::getOutputTypeOptions();
    }

    /**
     * Get combined position options
     */
    public function getCombinedPositionOptions(): array
    {
        return Maho_FeedManager_Model_DynamicRule::getCombinedPositionOptions();
    }

    /**
     * Get attribute options for output
     */
    public function getAttributeOptions(): array
    {
        $options = ['' => $this->__('-- Select Attribute --')];

        foreach (Maho_FeedManager_Model_DynamicRule::getOutputAttributeOptions() as $attr) {
            $options[$attr['value']] = $attr['label'];
        }

        return $options;
    }

    /**
     * Get URL for new condition HTML AJAX request
     */
    public function getNewConditionUrl(): string
    {
        return $this->getUrl('*/*/newConditionHtml', ['form' => 'FORM_PLACEHOLDER']);
    }

    /**
     * Get the prefix for a case's conditions
     * Note: Using underscores instead of brackets for JS compatibility
     */
    public function getConditionsPrefix(int $caseIndex): string
    {
        return 'case_' . $caseIndex . '_conditions';
    }

    /**
     * Get the form object ID for a case
     */
    public function getFormObjectId(int $caseIndex): string
    {
        return 'case_' . $caseIndex . '_conditions_fieldset';
    }

    /**
     * Render the conditions HTML for a case using the Combine model
     */
    public function renderConditionsHtml(int $caseIndex, ?array $conditionsData = null): string
    {
        $formId = $this->getFormObjectId($caseIndex);
        $prefix = $this->getConditionsPrefix($caseIndex);

        // Create a form for the conditions (no prefix - Combine model includes prefix in field names)
        $form = new \Maho\Data\Form();

        // Create the rule and set its form
        $rule = $this->getRule();
        $rule->setForm($form);

        // Create the Combine condition model
        /** @var Maho_FeedManager_Model_Rule_Condition_Combine $combine */
        $combine = Mage::getModel('feedmanager/rule_condition_combine');
        $combine->setPrefix($prefix);
        $combine->setId('1');
        $combine->setJsFormObject($formId);
        $combine->setRule($rule);
        $combine->setForm($form);

        // Load existing conditions if provided
        if ($conditionsData !== null && $conditionsData !== []) {
            $combine->loadArray($conditionsData, 'conditions');
        }

        return $combine->asHtmlRecursive();
    }

    /**
     * Get URL for fetching initial case conditions HTML
     */
    public function getCaseConditionsUrl(): string
    {
        return $this->getUrl('*/*/caseConditionsHtml');
    }

    /**
     * Get JSON config for JavaScript
     */
    public function getJsonConfig(): string
    {
        return Mage::helper('core')->jsonEncode([
            'newConditionUrl' => $this->getNewConditionUrl(),
            'caseConditionsUrl' => $this->getCaseConditionsUrl(),
            'outputTypes' => $this->getOutputTypeOptions(),
            'combinedPositions' => $this->getCombinedPositionOptions(),
            'attributes' => $this->getAttributeOptions(),
            'translations' => [
                'case' => $this->__('Case'),
                'fallback' => $this->__('Fallback'),
                'addCase' => $this->__('Add Case'),
                'removeCase' => $this->__('Remove'),
                'thenOutput' => $this->__('THEN Output:'),
                'value' => $this->__('Value'),
                'attribute' => $this->__('Attribute:'),
                'ifConditions' => $this->__('If ALL of these conditions are true:'),
                'fallbackNote' => $this->__('(Used when no other case matches)'),
            ],
        ]);
    }
}
