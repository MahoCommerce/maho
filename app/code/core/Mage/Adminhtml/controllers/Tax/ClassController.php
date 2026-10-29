<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Tax_ClassController extends Mage_Adminhtml_Controller_Action
{
    /**
     * save class action
     */
    #[Maho\Config\Route('/admin/tax_class/save')]
    public function saveAction(): void
    {
        if ($postData = $this->getRequest()->getPost()) {
            $model = Mage::getModel('tax/class')->setData($postData);

            try {
                $model->save();
                $classType  = $model->getClassType();
                $classUrl   = '*/tax_class_' . strtolower($classType);

                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('tax')->__('The tax class has been saved.'),
                );
                $this->_redirect($classUrl);

                return;
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setClassData($postData);
                $this->_redirectReferer();
            } catch (Exception) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('tax')->__('An error occurred while saving this tax class.'),
                );
                Mage::getSingleton('adminhtml/session')->setClassData($postData);
                $this->_redirectReferer();
            }

            $this->_redirectReferer();
            return;
        }
        $this->getResponse()->setRedirect($this->getUrl('*/tax_class'));
    }

    /**
     * Initialize action
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    protected function _initAction()
    {
        $classType = strtolower($this->getRequest()->getParam('classType'));
        $this->loadLayout()
            ->_setActiveMenu('sales/tax/classes_' . $classType)
            ->_addBreadcrumb(Mage::helper('tax')->__('Sales'), Mage::helper('tax')->__('Sales'))
            ->_addBreadcrumb(Mage::helper('tax')->__('Tax'), Mage::helper('tax')->__('Tax'))
        ;

        return $this;
    }

    #[\Override]
    protected function _isAllowed()
    {
        $allowedTypes = $this->_getAllowedClassTypes();
        if ($allowedTypes === []) {
            return false;
        }

        $request = $this->getRequest();
        if (strtolower($request->getActionName()) !== 'save' || !$request->isPost()) {
            return true;
        }

        // Both class types use this save action, so check the posted type and the type of the saved class
        $types = [];
        $postedType = $request->getPost('class_type');
        if ($postedType !== null) {
            $types[] = is_string($postedType) ? $postedType : '';
        }

        $classId = (int) $request->getPost('class_id');
        if ($classId > 0) {
            $types[] = (string) $this->_getClassType($classId);
        }

        return $types !== [] && array_diff($types, $allowedTypes) === [];
    }

    /**
     * Return the tax class types that the admin can manage.
     *
     * @return list<string>
     */
    protected function _getAllowedClassTypes(): array
    {
        $session = Mage::getSingleton('admin/session');
        $types = [];
        if ($session->isAllowed('sales/tax/classes_product')) {
            $types[] = Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT;
        }
        if ($session->isAllowed('sales/tax/classes_customer')) {
            $types[] = Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER;
        }

        return $types;
    }

    /**
     * Return the type of a saved tax class, or null when the class does not exist.
     */
    protected function _getClassType(int $classId): ?string
    {
        $class = Mage::getModel('tax/class')->load($classId);
        return $class->getId() ? (string) $class->getClassType() : null;
    }
}
