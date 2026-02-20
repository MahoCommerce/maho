<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Sales_Billing_AgreementController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Billing agreements
     */
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))
            ->_title($this->__('Billing Agreements'));

        $this->loadLayout()
            ->_setActiveMenu('sales/billing_agreement')
            ->renderLayout();
    }

    /**
     * Ajax action for billing agreements
     */
    public function gridAction(): void
    {
        $this->loadLayout(false)
            ->renderLayout();
    }

    /**
     * View billing agreement action
     */
    public function viewAction(): void
    {
        $agreementModel = $this->_initBillingAgreement();

        if ($agreementModel) {
            $this->_title($this->__('Sales'))
                ->_title($this->__('Billing Agreements'))
                ->_title(sprintf('#%s', $agreementModel->getReferenceId()));

            $this->loadLayout()
                ->_setActiveMenu('sales/billing_agreement')
                ->renderLayout();
            return;
        }

        $this->_redirect('*/*/');
    }

    /**
     * Related orders ajax action
     */
    public function ordersGridAction(): void
    {
        $this->_initBillingAgreement();
        $this->loadLayout(false)
            ->renderLayout();
    }

    /**
     * Cutomer billing agreements ajax action
     */
    public function customerGridAction(): void
    {
        $this->_initCustomer();
        $this->loadLayout(false)
            ->renderLayout();
    }

    /**
     * Cancel billing agreement action
     */
    public function cancelAction()
    {
        $agreementModel = $this->_initBillingAgreement();

        if ($agreementModel && $agreementModel->canCancel()) {
            try {
                $agreementModel->cancel();
                $this->_getSession()->addSuccess($this->__('The billing agreement has been canceled.'));
                $this->_redirect('*/*/view', ['_current' => true]);
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('Failed to cancel the billing agreement.'));
                Mage::logException($e);
            }
            $this->_redirect('*/*/view', ['_current' => true]);
        }
        return $this->_redirect('*/*/');
    }

    /**
     * Delete billing agreement action
     */
    public function deleteAction(): void
    {
        $agreementModel = $this->_initBillingAgreement();

        if ($agreementModel) {
            try {
                $agreementModel->delete();
                $this->_getSession()->addSuccess($this->__('The billing agreement has been deleted.'));
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError($this->__('Failed to delete the billing agreement.'));
                Mage::logException($e);
            }
            $this->_redirect('*/*/view', ['_current' => true]);
        }
        $this->_redirect('*/*/');
    }

    /**
     * Initialize billing agreement by ID specified in request
     *
     * @return Mage_Sales_Model_Billing_Agreement | false
     */
    protected function _initBillingAgreement()
    {
        $agreementId = $this->getRequest()->getParam('agreement');
        $agreementModel = Mage::getModel('sales/billing_agreement')->load($agreementId);

        if (!$agreementModel->getId()) {
            $this->_getSession()->addError($this->__('Wrong billing agreement ID specified.'));
            return false;
        }

        Mage::register('current_billing_agreement', $agreementModel);
        return $agreementModel;
    }

    /**
     * Initialize customer by ID specified in request
     *
     * @return $this
     */
    protected function _initCustomer()
    {
        $customerId = (int) $this->getRequest()->getParam('id');
        $customer = Mage::getModel('customer/customer');

        if ($customerId) {
            $customer->load($customerId);
        }

        Mage::register('current_customer', $customer);
        return $this;
    }

    /**
     * Retrieve adminhtml session
     *
     * @return Mage_Adminhtml_Model_Session
     */
    #[\Override]
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'index', 'grid', 'view' => Mage::getSingleton('admin/session')->isAllowed('sales/billing_agreement/actions/view'),
            'cancel', 'delete' => Mage::getSingleton('admin/session')->isAllowed('sales/billing_agreement/actions/manage'),
            default => Mage::getSingleton('admin/session')->isAllowed('sales/billing_agreement'),
        };
    }
}
