<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Adminhtml_Feedmanager_DynamicruleController extends Mage_Adminhtml_Controller_Action
{
    use Maho_FeedManager_Controller_Adminhtml_JsonResponseTrait;

    public const ADMIN_RESOURCE = 'catalog/feedmanager/dynamicrules';

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
            // Handle rule_data from conditions tab
            if (isset($data['rule_data']) && is_string($data['rule_data'])) {
                $ruleData = Mage::helper('core')->jsonDecode($data['rule_data']);
                $rule->setRuleDataArray($ruleData);
                unset($data['rule_data']);
            }

            $rule->addData($data);

            // Validate
            $errors = $rule->validate();
            if (!empty($errors)) {
                foreach ($errors as $error) {
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
