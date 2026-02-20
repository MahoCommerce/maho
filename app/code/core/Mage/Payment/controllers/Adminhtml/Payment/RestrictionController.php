<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restrictions admin controller
 */
class Mage_Payment_Adminhtml_Payment_RestrictionController extends Mage_Adminhtml_Controller_Action
{
    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/payment_restrictions')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Sales'),
                Mage::helper('adminhtml')->__('Sales'),
            )
            ->_addBreadcrumb(
                Mage::helper('payment')->__('Payment Restrictions'),
                Mage::helper('payment')->__('Payment Restrictions'),
            );
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))
            ->_title($this->__('Payment Restrictions'));

        $this->_initAction()
            ->renderLayout();
    }

    public function newAction(): void
    {
        $this->_title($this->__('Sales'))
            ->_title($this->__('Payment Restrictions'))
            ->_title($this->__('New Restriction'));

        // Create new empty model for the form
        $model = Mage::getModel('payment/restriction');
        Mage::register('payment_restriction', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                Mage::helper('payment')->__('New Restriction'),
                Mage::helper('payment')->__('New Restriction'),
            )
            ->renderLayout();
    }

    public function editAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('payment/restriction');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('payment')->__('This restriction no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($this->__('Sales'))
            ->_title($this->__('Payment Restrictions'));

        if ($model->getId()) {
            $this->_title($model->getName());
        } else {
            $this->_title($this->__('New Restriction'));
        }

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('payment_restriction', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('payment')->__('Edit Restriction')
                    : Mage::helper('payment')->__('New Restriction'),
                $id ? Mage::helper('payment')->__('Edit Restriction')
                    : Mage::helper('payment')->__('New Restriction'),
            )
            ->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $model = Mage::getModel('payment/restriction');
            $id = $this->getRequest()->getParam('id');

            if ($id) {
                $model->load($id);
            }

            // Process array fields
            if (isset($data['payment_methods']) && is_array($data['payment_methods'])) {
                $data['payment_methods'] = implode(',', $data['payment_methods']);
            }
            if (isset($data['websites']) && is_array($data['websites'])) {
                $data['websites'] = implode(',', $data['websites']);
            }
            if (isset($data['customer_groups']) && is_array($data['customer_groups'])) {
                $data['customer_groups'] = implode(',', $data['customer_groups']);
            }

            // Process conditions using the rule model
            $rule = Mage::getModel('payment/restriction_rule');

            // Set up the form for the rule
            $form = new \Maho\Data\Form();
            $form->setHtmlIdPrefix('rule_');
            $rule->setForm($form);

            // Load existing data if editing
            if ($id && $model->getConditionsSerialized()) {
                $rule->setConditionsSerialized($model->getConditionsSerialized());
                $rule->getConditions(); // This will deserialize existing conditions
            }

            // Extract conditions from nested rule structure (like SalesRule does)
            if (isset($data['rule']['conditions'])) {
                $data['conditions'] = $data['rule']['conditions'];
            }
            unset($data['rule']);

            // Load the posted data
            $rule->loadPost($data);

            // Encode the conditions as JSON
            $conditionsArray = $rule->getConditions()->asArray();
            try {
                $data['conditions_serialized'] = Mage::helper('core')->jsonEncode($conditionsArray);
            } catch (\JsonException $e) {
                Mage::logException($e);
                throw $e;
            }

            $model->setData($data);

            try {
                $model->save();

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('payment')->__('The payment restriction has been saved.'),
                );
                Mage::getSingleton('adminhtml/session')->setFormData(false);

                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                    return;
                }
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', ['id' => $this->getRequest()->getParam('id')]);
                return;
            }
        }
        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('payment/restriction');
                $model->load($id);
                $model->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('payment')->__('The payment restriction has been deleted.'),
                );
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('payment')->__('Unable to find a restriction to delete.'),
        );
        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $restrictionIds = $this->getRequest()->getParam('restriction');
        if (!is_array($restrictionIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('payment')->__('Please select restriction(s).'),
            );
        } else {
            try {
                foreach ($restrictionIds as $restrictionId) {
                    $restriction = Mage::getModel('payment/restriction')->load($restrictionId);
                    $restriction->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('payment')->__('Total of %d record(s) were deleted.', count($restrictionIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function massStatusAction(): void
    {
        $restrictionIds = $this->getRequest()->getParam('restriction');
        if (!is_array($restrictionIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Please select restriction(s).'),
            );
        } else {
            try {
                $status = $this->getRequest()->getParam('status');
                foreach ($restrictionIds as $restrictionId) {
                    $restriction = Mage::getModel('payment/restriction')->load($restrictionId);
                    $restriction->setStatus($status);
                    $restriction->save();
                }
                $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) were updated.', count($restrictionIds)),
                );
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    public function newConditionHtmlAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $typeArr = explode('|', str_replace('-', '/', $this->getRequest()->getParam('type')));
        $type = $typeArr[0];


        // Create the rule and ensure it has a form
        $rule = Mage::getModel('payment/restriction_rule');

        // Ensure the rule has a form (should auto-create if null)
        $rule->getForm();

        $model = Mage::getModel($type)
            ->setId($id)
            ->setType($type)
            ->setRule($rule)
            ->setPrefix('conditions');
        if (!empty($typeArr[1])) {
            $model->setData('attribute', $typeArr[1]);
        }

        if ($model instanceof Mage_Rule_Model_Condition_Abstract) {
            $model->setJsFormObject($this->getRequest()->getParam('form'));
            $html = $model->asHtmlRecursive();
        } else {
            $html = '';
        }
        $this->getResponse()->setBody($html);
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/payment_restrictions');
    }
}
