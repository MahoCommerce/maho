<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_CountryController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/directory/countries';

    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }

    protected function _initCountry(): Mage_Directory_Model_Country|false
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('directory/country');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                return false;
            }
        }

        Mage::register('current_country', $model);
        return $model;
    }

    protected function initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/countries')
            ->_addBreadcrumb(
                Mage::helper('directory')->__('System'),
                Mage::helper('directory')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('directory')->__('Directory Management'),
                Mage::helper('directory')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('directory')->__('Countries'),
                Mage::helper('directory')->__('Countries'),
            );
        return $this;
    }

    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Countries'));

        $this->initAction();
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $model = $this->_initCountry();

        if ($model === false) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('directory')->__('This country no longer exists.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->addData($data);
        }

        $this->initAction();

        if ($model->getOrigData('country_id')) {
            $this->_title($model->getName())
                ->_addBreadcrumb(
                    Mage::helper('directory')->__('Edit Country'),
                    Mage::helper('directory')->__('Edit Country'),
                );
        } else {
            $this->_title($this->__('New Country'))
                ->_addBreadcrumb(
                    Mage::helper('directory')->__('New Country'),
                    Mage::helper('directory')->__('New Country'),
                );
        }

        $this->renderLayout();
    }

    public function saveAction(): void
    {
        $model = $this->_initCountry();
        $data = $this->getRequest()->getPost();

        try {
            if (empty($data)) {
                Mage::throwException(Mage::helper('adminhtml')->__('Unable to complete this request.'));
            }

            if ($model === false) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('directory')->__('This country no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }

            $model->addData($data);

            $errors = $model->validate();
            if (is_array($errors)) {
                Mage::throwException(implode('<br>', $errors));
            }

            $model->save();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('directory')->__('The country has been saved.'),
            );
            Mage::getSingleton('adminhtml/session')->setFormData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $model->getId()]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Internal Error'));
            Mage::logException($e);
        }

        Mage::getSingleton('adminhtml/session')->setFormData($data);

        if ($model->getOrigData('country_id')) {
            $this->_redirect('*/*/edit', ['id' => $model->getId()]);
        } else {
            $this->_redirect('*/*/new');
        }
    }

    public function deleteAction(): void
    {
        $model = $this->_initCountry();

        try {
            if ($model === false || !$model->getId()) {
                Mage::throwException(
                    Mage::helper('directory')->__('This country no longer exists.'),
                );
            }

            $model->delete();

            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('directory')->__('The country has been deleted.'),
            );
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            Mage::getSingleton('adminhtml/session')->addError($error);
        }

        $this->_redirect('*/*/');
    }

    public function massDeleteAction(): void
    {
        $countryIds = $this->getRequest()->getPost('countries');

        try {
            if (!is_array($countryIds)) {
                Mage::throwException(
                    Mage::helper('directory')->__('Please select country(s).'),
                );
            }

            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($countryIds as $countryId) {
                $model = Mage::getModel('directory/country')->load($countryId);

                if (!$model->getId()) {
                    $skippedCount++;
                } else {
                    $model->delete();
                    $deletedCount++;
                }
            }

            if ($deletedCount > 0) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', $deletedCount),
                );
            }

            if ($skippedCount > 0) {
                Mage::getSingleton('adminhtml/session')->addWarning(
                    Mage::helper('directory')->__('%d country(s) were skipped because they no longer exist.', $skippedCount),
                );
            }
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('adminhtml')->__('Internal Error');
            Mage::logException($e);
        }

        if (isset($error)) {
            Mage::getSingleton('adminhtml/session')->addError($error);
        }
        $this->_redirect('*/*/');
    }
}
