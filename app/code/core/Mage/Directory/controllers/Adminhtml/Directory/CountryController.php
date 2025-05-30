<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Adminhtml_Directory_CountryController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init actions
     */
    protected function _initAction(): self
    {
        $this->loadLayout()
            ->_setActiveMenu('system/directory/countries')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('System'),
                Mage::helper('adminhtml')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Directory Management'),
                Mage::helper('adminhtml')->__('Directory Management'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Countries'),
                Mage::helper('adminhtml')->__('Countries'),
            );
        return $this;
    }

    /**
     * Index action - show countries grid
     */
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Directory Management'))
            ->_title($this->__('Countries'));

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Grid action for AJAX requests
     */
    public function gridAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * New country action
     */
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit country action
     */
    public function editAction(): void
    {
        $id = $this->getRequest()->getParam('id');
        $model = Mage::getModel('directory/country');

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('adminhtml')->__('This country no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getName() : $this->__('New Country'));

        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('current_country', $model);

        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('adminhtml')->__('Edit Country') : Mage::helper('adminhtml')->__('New Country'),
                $id ? Mage::helper('adminhtml')->__('Edit Country') : Mage::helper('adminhtml')->__('New Country'),
            )
            ->renderLayout();
    }

    /**
     * Save country action
     */
    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            $id = $this->getRequest()->getParam('id');
            $model = Mage::getModel('directory/country');

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('This country no longer exists.'),
                    );
                    $this->_redirect('*/*/');
                    return;
                }
            }

            $model->setData($data);

            try {
                $model->save();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The country has been saved.'),
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

        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('adminhtml')->__('Unable to find country to save.'),
        );
        $this->_redirect('*/*/');
    }

    /**
     * Delete country action
     */
    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            try {
                $model = Mage::getModel('directory/country');
                $model->load($id);
                if (!$model->getId()) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('adminhtml')->__('Unable to find a country to delete.'),
                    );
                } else {
                    $model->delete();
                    Mage::getSingleton('adminhtml/session')->addSuccess(
                        Mage::helper('adminhtml')->__('The country has been deleted.'),
                    );
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/');
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction(): void
    {
        $countryIds = $this->getRequest()->getParam('country');
        if (!is_array($countryIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('Please select country(s).'),
            );
        } else {
            try {
                foreach ($countryIds as $countryId) {
                    $country = Mage::getModel('directory/country')->load($countryId);
                    $country->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('Total of %d record(s) were deleted.', count($countryIds)),
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Check ACL permissions
     */
    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/directory/countries');
    }
}
