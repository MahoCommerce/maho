<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Adminhtml_Feedmanager_DynamicruleController extends Mage_Adminhtml_Controller_Action
{
    use Maho_FeedManager_Controller_Adminhtml_JsonResponseTrait;

    public const ADMIN_RESOURCE = 'catalog/feedmanager/dynamicrules';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions(['delete', 'save', 'massDelete', 'massStatus']);
        return parent::preDispatch();
    }

    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/feedmanager/dynamicrules')
            ->_addBreadcrumb($this->__('Catalog'), $this->__('Catalog'))
            ->_addBreadcrumb($this->__('Feed Manager'), $this->__('Feed Manager'));
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'))
            ->_title($this->__('Dynamic Rules'));

        $this->_initAction();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        $rule = Mage::getModel('feedmanager/dynamicRule');

        if ($id) {
            $rule->load($id);
            if (!$rule->getId()) {
                $this->_getSession()->addError($this->__('This rule no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        Mage::register('current_dynamic_rule', $rule);

        $this->_title($this->__('Catalog'))
            ->_title($this->__('Feed Manager'));

        if ($rule->getId()) {
            $this->_title($rule->getName());
        } else {
            $this->_title($this->__('New Dynamic Rule'));
        }

        $this->_initAction();
        $this->_addBreadcrumb(
            $id ? $this->__('Edit Rule') : $this->__('New Rule'),
            $id ? $this->__('Edit Rule') : $this->__('New Rule'),
        );

        $this->renderLayout();
    }

    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();

        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $id = (int) $this->getRequest()->getParam('id');
        $rule = Mage::getModel('feedmanager/dynamicRule');

        if ($id) {
            $rule->load($id);
            if (!$rule->getId()) {
                $this->_getSession()->addError($this->__('This rule no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        try {
            // Process cases data
            if (isset($data['cases']) && is_array($data['cases'])) {
                $cases = $this->_processCasesData($data['cases']);
                $rule->setCases($cases);
                unset($data['cases']);
            }

            $rule->addData($data);

            // Validate
            $validateResult = $rule->validateData(new \Maho\DataObject($data));
            if ($validateResult !== true) {
                foreach ($validateResult as $error) {
                    $this->_getSession()->addError($error);
                }
                $this->_getSession()->setFormData($this->getRequest()->getPost());
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }

            $rule->save();

            $this->_getSession()->addSuccess($this->__('The rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $rule->getId()]);
                return;
            }

            $this->_redirect('*/*/');
            return;

        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_getSession()->setFormData($data);
            $this->_redirect('*/*/edit', ['id' => $id]);
            return;
        }
    }

    /**
     * Process cases data from form submission
     */
    protected function _processCasesData(array $casesData): array
    {
        $cases = [];
        $ruleData = $this->getRequest()->getPost('rule', []);

        foreach ($casesData as $index => $caseData) {
            $case = [
                'output_type' => $caseData['output_type'] ?? 'static',
                'output_value' => $caseData['output_value'] ?? null,
                'output_attribute' => $caseData['output_attribute'] ?? null,
                'combined_position' => $caseData['combined_position'] ?? 'prefix',
                'is_default' => !empty($caseData['is_default']),
                'conditions' => null,
            ];

            // Process conditions if present (for non-default cases)
            // Conditions come in as rule[case_X_conditions] format
            if (!$case['is_default']) {
                $conditionsKey = 'case_' . $index . '_conditions';
                if (isset($ruleData[$conditionsKey]) && is_array($ruleData[$conditionsKey])) {
                    $case['conditions'] = $this->_buildConditionsArray($ruleData[$conditionsKey], $conditionsKey);
                }
            }

            $cases[] = $case;
        }

        return $cases;
    }

    /**
     * Build conditions array from form post data
     */
    protected function _buildConditionsArray(array $conditionsData, string $prefix = 'conditions'): array
    {
        // Convert flat form data to recursive structure (same logic as Mage_Rule_Model_Abstract)
        $arr = $this->_convertFlatToRecursive($conditionsData);

        if (empty($arr) || !isset($arr[1])) {
            return [];
        }

        // Create a temporary combine model to parse the data
        $conditionsModel = Mage::getModel('feedmanager/rule_condition_combine');
        $conditionsModel->setPrefix($prefix);
        $conditionsModel->setConditions([]);
        $conditionsModel->loadArray($arr[1], 'conditions');

        return $conditionsModel->asArray();
    }

    /**
     * Convert flat form conditions data to recursive array structure
     * This mirrors Mage_Rule_Model_Abstract::_convertFlatToRecursive
     *
     * @return array<int|string, mixed>
     */
    protected function _convertFlatToRecursive(array $data): array
    {
        /** @var array<string, mixed> $arr */
        $arr = ['conditions' => []];
        foreach ($data as $id => $itemData) {
            $path = explode('--', (string) $id);
            $node = &$arr;
            for ($i = 0, $l = count($path); $i < $l; $i++) {
                if (!array_key_exists('conditions', $node)) {
                    $node['conditions'] = [];
                }
                if (!array_key_exists($path[$i], $node['conditions'])) {
                    $node['conditions'][$path[$i]] = [];
                }
                $node = &$node['conditions'][$path[$i]];
            }
            foreach ($itemData as $k => $v) {
                $node[$k] = $v;
            }
        }
        return $arr['conditions'];
    }

    /**
     * Get initial conditions HTML for a new case (AJAX)
     */
    public function caseConditionsHtmlAction(): void
    {
        $caseIndex = max(0, (int) $this->getRequest()->getParam('case_index', 0));
        $formId = 'case_' . $caseIndex . '_conditions_fieldset';
        $prefix = 'case_' . $caseIndex . '_conditions';

        // Create a form for the conditions
        $form = new \Maho\Data\Form();

        // Create the rule and set its form
        $rule = Mage::getModel('feedmanager/dynamicRule');
        $rule->setForm($form);

        // Create the Combine condition model
        /** @var Maho_FeedManager_Model_Rule_Condition_Combine $combine */
        $combine = Mage::getModel('feedmanager/rule_condition_combine');
        $combine->setPrefix($prefix);
        $combine->setId('1');
        $combine->setJsFormObject($formId);
        $combine->setRule($rule);
        $combine->setForm($form);

        $html = $combine->asHtmlRecursive();

        $this->getResponse()->setBody($html);
    }

    /**
     * New condition HTML for AJAX (standard Maho rules engine)
     */
    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $formId = $this->getRequest()->getParam('form');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];

        // Determine prefix based on form ID (e.g., case_0_conditions_fieldset -> case_0_conditions)
        $prefix = 'conditions';
        if (preg_match('/^case_(\d+)_conditions_fieldset$/', $formId, $matches)) {
            $caseIndex = $matches[1];
            $prefix = 'case_' . $caseIndex . '_conditions';
        }

        $model = Mage::getModel($type);

        if (!$model instanceof Mage_Rule_Model_Condition_Abstract) {
            $this->getResponse()->setBody('');
            return;
        }

        $model->setId($id)
            ->setType($type)
            ->setRule(Mage::getModel('feedmanager/dynamicRule'))
            ->setPrefix($prefix);

        if (!empty($typeArr[1])) {
            $model->setAttribute($typeArr[1]);
        }

        $model->setJsFormObject($formId);
        $html = $model->asHtmlRecursive();

        $this->getResponse()->setBody($html);
    }

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');

        if (!$id) {
            $this->_getSession()->addError($this->__('Unable to find a rule to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $rule = Mage::getModel('feedmanager/dynamicRule')->load($id);

            if ($rule->getIsSystem()) {
                $this->_getSession()->addError(
                    $this->__('System rules cannot be deleted. You can disable them instead.'),
                );
                $this->_redirect('*/*/');
                return;
            }

            $rule->delete();

            $this->_getSession()->addSuccess($this->__('The rule has been deleted.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function massStatusAction(): void
    {
        $ruleIds = $this->getRequest()->getParam('rule_ids');
        $status = (int) $this->getRequest()->getParam('status');

        if (!is_array($ruleIds)) {
            $this->_getSession()->addError($this->__('Please select rules.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            foreach ($ruleIds as $ruleId) {
                $rule = Mage::getModel('feedmanager/dynamicRule')->load($ruleId);
                if ($rule->getId()) {
                    $rule->setIsEnabled($status)->save();
                }
            }

            $this->_getSession()->addSuccess(
                $this->__('%d rule(s) have been updated.', count($ruleIds)),
            );
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $ruleIds = $this->getRequest()->getParam('rule_ids');

        if (!is_array($ruleIds)) {
            $this->_getSession()->addError($this->__('Please select rules to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        $deleted = 0;
        $skipped = 0;

        try {
            foreach ($ruleIds as $ruleId) {
                $rule = Mage::getModel('feedmanager/dynamicRule')->load($ruleId);

                if ($rule->getIsSystem()) {
                    $skipped++;
                    continue;
                }

                if ($rule->getId()) {
                    $rule->delete();
                    $deleted++;
                }
            }

            if ($deleted) {
                $this->_getSession()->addSuccess(
                    $this->__('%d rule(s) have been deleted.', $deleted),
                );
            }
            if ($skipped) {
                $this->_getSession()->addNotice(
                    $this->__('%d system rule(s) were skipped and cannot be deleted.', $skipped),
                );
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/');
    }

    /**
     * Get available product attributes for AJAX request
     */
    public function getAttributesAction(): void
    {
        $attributes = [];

        // Get product attributes
        $collection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $code = $attribute->getAttributeCode();
            $label = $attribute->getFrontendLabel();
            if ($label) {
                $attributes[] = [
                    'value' => $code,
                    'label' => $label . ' (' . $code . ')',
                ];
            }
        }

        // Add common computed attributes
        $computed = [
            ['value' => 'qty', 'label' => 'Quantity (qty)'],
            ['value' => 'is_in_stock', 'label' => 'Is In Stock (is_in_stock)'],
            ['value' => 'type_id', 'label' => 'Product Type (type_id)'],
            ['value' => 'entity_id', 'label' => 'Product ID (entity_id)'],
            ['value' => 'parent_id', 'label' => 'Parent ID (parent_id)'],
        ];

        $attributes = array_merge($computed, $attributes);

        $this->_sendJsonResponse($attributes);
    }
}
