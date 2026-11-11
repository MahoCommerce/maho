<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Tax_RuleController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'sales/tax/rules';

    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Tax');
    }

    /**
     * Index action
     *
     * @return $this
     */
    #[Maho\Config\Route('/admin/tax_rule/index')]
    public function indexAction()
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Rules'));

        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rule'))
            ->renderLayout();
        return $this;
    }

    /**
     * Redirect to edit action
     */
    #[Maho\Config\Route('/admin/tax_rule/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit action
     */
    #[Maho\Config\Route('/admin/tax_rule/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Manage Tax Rules'));

        $taxRuleId  = $this->getRequest()->getParam('rule');
        $ruleModel  = Mage::getModel('tax/calculation_rule');
        if ($taxRuleId) {
            try {
                $ruleModel = $this->ruleService()->getById((int) $taxRuleId);
            } catch (Mage_Core_Exception_NoSuchEntity $e) {
                Mage::getSingleton('adminhtml/session')->unsRuleData();
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
                return;
            }
        }

        $data = Mage::getSingleton('adminhtml/session')->getRuleData(true);
        if (!empty($data)) {
            $ruleModel->setData($data);
        }

        $this->_title($ruleModel->getId() ? sprintf('%s', $ruleModel->getCode()) : $this->__('New Rule'));

        Mage::register('tax_rule', $ruleModel);

        $this->_initAction()
            ->_addBreadcrumb(
                $taxRuleId ? $this->__('Edit Rule') : $this->__('New Rule'),
                $taxRuleId ? $this->__('Edit Rule') : $this->__('New Rule'),
            )
            ->_addContent($this->getLayout()->createBlock('adminhtml/tax_rule_edit')
                ->setData('action', $this->getUrl('*/tax_rule/save')))
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/tax_rule/save')]
    public function saveAction()
    {
        $request = $this->getRequest();
        $postData = $request->getPost();
        if (!$postData) {
            return $this->getResponse()->setRedirect($this->getUrl('*/tax_rule'));
        }

        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $ruleId = (int) $request->getParam('tax_calculation_rule_id');
            $rule = $ruleId ? $this->ruleService()->getById($ruleId) : Mage::getModel('tax/calculation_rule');
            $rule->setCode((string) $request->getPost('code'))
                ->setPriority((int) $request->getPost('priority'))
                ->setPosition((int) $request->getPost('position'))
                ->setCalculateSubtotal((int) $request->getParam('calculate_subtotal', 0))
                ->setTaxCustomerClass(array_map(intval(...), (array) $request->getPost('tax_customer_class')))
                ->setTaxProductClass(array_map(intval(...), (array) $request->getPost('tax_product_class')))
                ->setTaxRate(array_map(intval(...), (array) $request->getPost('tax_rate')));
            $this->ruleService()->save($rule);
            $session->addSuccess($this->__('The tax rule has been saved.'));

            if ($request->getParam('back')) {
                return $this->_redirect('*/*/edit', ['rule' => $rule->getId()]);
            }

            return $this->_redirect('*/*/');
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $this->__('An error occurred while saving this tax rule.'));
        }

        $session->setRuleData($postData);
        $this->_redirectReferer();
    }

    #[Maho\Config\Route('/admin/tax_rule/delete')]
    public function deleteAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        try {
            $this->ruleService()->delete($this->ruleService()->getById((int) $this->getRequest()->getParam('rule')));
            $session->addSuccess($this->__('The tax rule has been deleted.'));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $this->__('An error occurred while deleting this tax rule.'));
        }

        $this->_redirectReferer();
    }

    protected function ruleService(): Mage_Tax_Service_Rule
    {
        return Mage::getService('tax/rule');
    }

    /**
     * Initialize action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/tax/rules')
            ->_addBreadcrumb($this->__('Tax'), $this->__('Tax'))
            ->_addBreadcrumb($this->__('Tax Rules'), $this->__('Tax Rules'))
        ;
        return $this;
    }
}
