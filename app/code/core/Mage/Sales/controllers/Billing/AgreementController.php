<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method int getAgreementId()
 */
use Maho\Config\Route;

class Mage_Sales_Billing_AgreementController extends Mage_Core_Controller_Front_Action
{
    /**
     * View billing agreements
     */
    #[Route('/sales/billing_agreement', name: 'sales.billing_agreement.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->_title($this->__('Billing Agreements'));
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    /**
     * Action predispatch
     *
     * Check customer authentication
     *
     * @return $this|void
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!$this->getRequest()->isDispatched()) {
            return;
        }
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }
        if (!$this->_getSession()->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
        return $this;
    }

    /**
     * View billing agreement
     */
    #[Route('/sales/billing_agreement/view/{agreement}', name: 'sales.billing_agreement.view', methods: ['GET'], requirements: ['agreement' => '\d+'])]
    public function viewAction(): void
    {
        $agreement = $this->_initAgreement();
        if (!$agreement) {
            $this->_redirect('*/*/index');
            return;
        }

        $customerIdSession = $this->_getSession()->getCustomer()->getId();
        if (!$agreement->canPerformAction($customerIdSession)) {
            $this->_redirect('*/*/index');
            return;
        }

        $this->_title($this->__('Billing Agreements'))
            ->_title($this->__('Billing Agreement # %s', $agreement->getReferenceId()));
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $navigationBlock = $this->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('sales/billing_agreement/');
        }
        $this->renderLayout();
    }

    /**
     * Wizard start action
     *
     * @return $this|void
     */
    #[Route('/sales/billing_agreement/startWizard', name: 'sales.billing_agreement.startWizard', methods: ['GET'])]
    public function startWizardAction()
    {
        $agreement = Mage::getModel('sales/billing_agreement');
        $paymentCode = $this->getRequest()->getParam('payment_method');
        if ($paymentCode) {
            try {
                $agreement->setStoreId(Mage::app()->getStore()->getId())
                    ->setMethodCode($paymentCode)
                    ->setReturnUrl(Mage::getUrl('*/*/returnWizard', ['payment_method' => $paymentCode]))
                    ->setCancelUrl(Mage::getUrl('*/*/cancelWizard', ['payment_method' => $paymentCode]));

                $this->_redirectUrl($agreement->initToken());
                return $this;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('Failed to start billing agreement wizard.'));
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Wizard return action
     */
    #[Route('/sales/billing_agreement/returnWizard', name: 'sales.billing_agreement.returnWizard', methods: ['GET'])]
    public function returnWizardAction(): void
    {
        $agreement = Mage::getModel('sales/billing_agreement');
        $paymentCode = $this->getRequest()->getParam('payment_method');
        $token = $this->getRequest()->getParam('token');
        if ($token && $paymentCode) {
            try {
                $agreement->setStoreId(Mage::app()->getStore()->getId())
                    ->setToken($token)
                    ->setMethodCode($paymentCode)
                    ->setCustomer(Mage::getSingleton('customer/session')->getCustomer())
                    ->place();
                $this->_getSession()->addSuccess(
                    $this->__('The billing agreement "%s" has been created.', $agreement->getReferenceId()),
                );
                $this->_redirect('*/*/view', ['agreement' => $agreement->getId()]);
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('Failed to finish billing agreement wizard.'));
            }
            $this->_redirect('*/*/index');
        }
    }

    /**
     * Wizard cancel action
     */
    #[Route('/sales/billing_agreement/cancelWizard', name: 'sales.billing_agreement.cancelWizard', methods: ['GET'])]
    public function cancelWizardAction(): void
    {
        $this->_redirect('*/*/index');
    }

    /**
     * Cancel action
     * Set billing agreement status to 'Canceled'
     */
    #[Route('/sales/billing_agreement/cancel', name: 'sales.billing_agreement.cancel', methods: ['POST'])]
    public function cancelAction(): void
    {
        $agreement = $this->_initAgreement();
        if (!$agreement) {
            $this->_redirect('*/*/view', ['_current' => true]);
            return;
        }

        $customerIdSession = $this->_getSession()->getCustomer()->getId();
        if (!$agreement->canPerformAction($customerIdSession)) {
            $this->_redirect('*/*/view', ['_current' => true]);
            return;
        }

        if ($agreement->canCancel()) {
            try {
                $agreement->cancel();
                $this->_getSession()->addNotice($this->__('The billing agreement "%s" has been canceled.', $agreement->getReferenceId()));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_getSession()->addError($this->__('Failed to cancel the billing agreement.'));
            }
        }
        $this->_redirect('*/*/view', ['_current' => true]);
    }

    /**
     * Init billing agreement model from request
     *
     * @return Mage_Sales_Model_Billing_Agreement|false
     */
    protected function _initAgreement()
    {
        $agreementId = $this->getRequest()->getParam('agreement');
        $billingAgreement = false;

        if ($agreementId) {
            $billingAgreement = Mage::getModel('sales/billing_agreement')->load($agreementId);
            if (!$billingAgreement->getAgreementId()) {
                $this->_getSession()->addError($this->__('Wrong billing agreement ID specified.'));
                $this->_redirect('*/*/');
                return false;
            }
        }
        Mage::register('current_billing_agreement', $billingAgreement);
        return $billingAgreement;
    }

    /**
     * Retrieve customer session model
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }
}
