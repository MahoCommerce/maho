<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Tax_ClassController extends Mage_Adminhtml_Controller_Action
{
    #[Maho\Config\Route('/admin/tax_class/save')]
    public function saveAction(): void
    {
        $postData = $this->getRequest()->getPost();
        if (!$postData) {
            $this->_redirect('*/tax_class');
            return;
        }

        $session = Mage::getSingleton('adminhtml/session');
        $service = $this->classService();
        try {
            $classId = (int) $this->getRequest()->getPost('class_id');
            $class = $classId ? $service->getById($classId) : Mage::getModel('tax/class');
            $class->setClassName((string) $this->getRequest()->getPost('class_name'));
            if ($this->getRequest()->getPost('class_type') !== null) {
                $class->setClassType((string) $this->getRequest()->getPost('class_type'));
            }
            $service->save($class);
            $session->addSuccess(Mage::helper('tax')->__('The tax class has been saved.'));
            $this->_redirect('*/tax_class_' . strtolower((string) $class->getClassType()));
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('tax')->__('An error occurred while saving this tax class.'));
        }
        $session->setClassData($postData);
        $this->_redirectReferer();
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

    protected function classService(): Mage_Tax_Service_Class
    {
        return Mage::getService('tax/class');
    }
}
