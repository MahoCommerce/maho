<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_AddressController extends Mage_Core_Controller_Front_Action
{
    /**
     * Retrieve customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }
        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
        }
        return $this;
    }

    /**
     * Customer addresses list
     */
    #[Maho\Config\Route('/customer/address', name: 'customer.address.index', methods: ['GET'])]
    public function indexAction(): void
    {
        if (count($this->_getSession()->getCustomer()->getAddresses())) {
            $this->loadLayout();
            $this->_initLayoutMessages('customer/session');
            $this->_initLayoutMessages('catalog/session');

            $block = $this->getLayout()->getBlock('address_book');
            if ($block) {
                $block->setRefererUrl($this->_getRefererUrl());
            }
            $this->renderLayout();
        } else {
            $this->getResponse()->setRedirect(Mage::getUrl('*/*/new'));
        }
    }

    #[Maho\Config\Route('/customer/address/edit/{id}', name: 'customer.address.edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function editAction(): void
    {
        $this->_forward('form');
    }

    #[Maho\Config\Route('/customer/address/new', name: 'customer.address.new', methods: ['GET'])]
    public function newAction(): void
    {
        $this->_forward('form');
    }

    /**
     * Address book form
     */
    #[Maho\Config\Route('/customer/address/form', name: 'customer.address.form', methods: ['GET'])]
    public function formAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $navigationBlock = $this->getLayout()->getBlock('customer_account_navigation');
        if ($navigationBlock) {
            $navigationBlock->setActive('customer/address');
        }
        $this->renderLayout();
    }

    /**
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/customer/address/formPost', name: 'customer.address.formPost', methods: ['POST'])]
    public function formPostAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_redirectReferer();
            return;
        }

        $session = $this->_getSession();
        $service = $this->getService();
        $address = Mage::getModel('customer/address');
        try {
            $addressId = (int) $this->getRequest()->getParam('id');
            if ($addressId) {
                $address = $service->getByCustomer($addressId, (int) $session->getCustomerId());
            }

            /** @var Mage_Customer_Model_Form $addressForm */
            $addressForm = Mage::getModel('customer/form');
            $addressForm->setFormCode('customer_address_edit')->setEntity($address);
            $addressData = $addressForm->extractData($this->getRequest());
            $formErrors = $addressForm->validateData($addressData);
            if ($formErrors !== true) {
                $errors = new Mage_Core_Exception_Input();
                foreach ($formErrors as $message) {
                    $errors->addError('address', (string) $message);
                }
                throw $errors;
            }
            $addressForm->compactData($addressData);
            $address->setCustomerId((int) $session->getCustomerId())
                ->setIsDefaultBilling($this->getRequest()->getParam('default_billing') ? true : null)
                ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping') ? true : null);

            $service->save($address);
            $session->addSuccess($this->__('The address has been saved.'));
            $this->_redirectSuccess(Mage::getUrl('*/*/index', ['_secure' => true]));
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('Cannot save address.'));
        }

        $session->setAddressFormData($this->getRequest()->getPost());
        return $this->_redirectError(Mage::getUrl('*/*/edit', ['id' => $address->getId()]));
    }

    /**
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/customer/address/delete', name: 'customer.address.delete', methods: ['POST'])]
    public function deleteAction()
    {
        $addressId = (int) $this->getRequest()->getParam('id');
        if ($addressId) {
            $session = $this->_getSession();
            try {
                $service = $this->getService();
                $service->delete($service->getByCustomer($addressId, (int) $session->getCustomerId()));
                $session->addSuccess($this->__('The address has been deleted.'));
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Throwable $e) {
                Mage::logException($e);
                $session->addError($this->__('An error occurred while deleting the address.'));
            }
        }
        $this->getResponse()->setRedirect(Mage::getUrl('*/*/index'));
    }

    protected function getService(): Mage_Customer_Service_Address
    {
        return Mage::getService('customer/address');
    }
}
