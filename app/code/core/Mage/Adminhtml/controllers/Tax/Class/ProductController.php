<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Tax_Class_ProductController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'sales/tax/classes_product';

    /**
     * view grid
     */
    #[Maho\Config\Route('/admin/tax_class_product/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Product Tax Classes'));

        $this->_initAction()
            ->_addContent(
                $this->getLayout()->createBlock('adminhtml/tax_class')
                    ->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT),
            )
            ->renderLayout();
    }

    /**
     * new class action
     */
    #[Maho\Config\Route('/admin/tax_class_product/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * edit class action
     */
    #[Maho\Config\Route('/admin/tax_class_product/edit')]
    public function editAction(): void
    {
        $this->_title($this->__('Sales'))
             ->_title($this->__('Tax'))
             ->_title($this->__('Product Tax Classes'));

        $classId = $this->getRequest()->getParam('id');
        $model = Mage::getModel('tax/class');
        if ($classId) {
            try {
                $model = Mage::getService('tax/class')->getById((int) $classId, Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
            } catch (Mage_Core_Exception_NoSuchEntity $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getClassName() : $this->__('New Class'));

        $data = Mage::getSingleton('adminhtml/session')->getClassData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('tax_class', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $classId ? Mage::helper('tax')->__('Edit Class') : Mage::helper('tax')->__('New Class'),
                $classId ? Mage::helper('tax')->__('Edit Class') : Mage::helper('tax')->__('New Class'),
            )
            ->_addContent(
                $this->getLayout()->createBlock('adminhtml/tax_class_edit')
                    ->setData('action', $this->getUrl('*/tax_class/save'))
                    ->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT),
            )
            ->renderLayout();
    }

    #[Maho\Config\Route('/admin/tax_class_product/delete')]
    public function deleteAction(): void
    {
        $classId = (int) $this->getRequest()->getParam('id');
        $session = Mage::getSingleton('adminhtml/session');
        /** @var Mage_Tax_Service_Class $service */
        $service = Mage::getService('tax/class');
        try {
            $service->delete($service->getById($classId, Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT));
            $session->addSuccess(Mage::helper('tax')->__('The tax class has been deleted.'));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('tax')->__('An error occurred while deleting this tax class.'));
        }

        $this->_redirect('*/*/edit/', ['id' => $classId]);
    }

    /**
     * Initialize action
     *
     * @return $this
     */
    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('sales/tax/classes_product')
            ->_addBreadcrumb(Mage::helper('tax')->__('Sales'), Mage::helper('tax')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Tax'), Mage::helper('tax')->__('Tax'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Manage Product Tax Classes'), Mage::helper('tax')->__('Manage Product Tax Classes'))
        ;
        return $this;
    }
}
