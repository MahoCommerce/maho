<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Cms_PageController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Init actions
     *
     * @return $this
     */
    protected function _initAction()
    {
        // load layout, set active menu and breadcrumbs
        $this->loadLayout()
            ->_setActiveMenu('cms/page')
            ->_addBreadcrumb(Mage::helper('cms')->__('CMS'), Mage::helper('cms')->__('CMS'))
            ->_addBreadcrumb(Mage::helper('cms')->__('Manage Pages'), Mage::helper('cms')->__('Manage Pages'))
        ;
        return $this;
    }

    /**
     * Index action
     */
    public function indexAction()
    {
        $this->_title($this->__('CMS'))
             ->_title($this->__('Pages'))
             ->_title($this->__('Manage Content'));

        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * Create new CMS page
     */
    public function newAction()
    {
        // the same form is used to create and edit
        $this->_forward('edit');
    }

    /**
     * Edit CMS page
     */
    public function editAction()
    {
        $this->_title($this->__('CMS'))
             ->_title($this->__('Pages'))
             ->_title($this->__('Manage Content'));

        // 1. Get ID and create model
        $id = $this->getRequest()->getParam('page_id');
        $model = Mage::getModel('cms/page');

        // 2. Initial checking
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                Mage::getSingleton('adminhtml/session')->addError(
                    Mage::helper('cms')->__('This page no longer exists.'),
                );
                $this->_redirect('*/*/');
                return;
            }
        }

        $this->_title($model->getId() ? $model->getTitle() : $this->__('New Page'));

        // 3. Set entered data if was error when we do save
        $data = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if (!empty($data)) {
            $data['store_id'] = $data['stores'];
            $model->setData($data);
        }

        // 4. Register model to use later in blocks
        Mage::register('cms_page', $model);

        // 5. Build edit form
        $this->_initAction()
            ->_addBreadcrumb(
                $id ? Mage::helper('cms')->__('Edit Page')
                    : Mage::helper('cms')->__('New Page'),
                $id ? Mage::helper('cms')->__('Edit Page')
                : Mage::helper('cms')->__('New Page'),
            );

        $this->renderLayout();
    }

    /**
     * Save action
     */
    public function saveAction()
    {
        // check if data sent
        if ($data = $this->getRequest()->getPost()) {
            $data = $this->_filterPostData($data);
            //init model and set data
            $model = Mage::getModel('cms/page');

            if ($id = $this->getRequest()->getParam('page_id')) {
                $model->load($id);
            }

            $model->setData($data);

            Mage::dispatchEvent('cms_page_prepare_save', ['page' => $model, 'request' => $this->getRequest()]);

            //validating
            if (!$this->_validatePostData($data)) {
                $this->_redirect('*/*/edit', ['page_id' => $model->getId(), '_current' => true]);
                return;
            }

            // try to save it
            try {
                // save the data
                $model->save();

                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cms')->__('The page has been saved.'),
                );
                // clear previously saved data from session
                Mage::getSingleton('adminhtml/session')->setFormData(false);
                // check if 'Save and Continue'
                if ($this->getRequest()->getParam('back')) {
                    $this->_redirect('*/*/edit', ['page_id' => $model->getId(), '_current' => true]);
                    return;
                }
                // go to grid
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException(
                    $e,
                    Mage::helper('cms')->__('An error occurred while saving the page.'),
                );
            }

            if (empty($data['identifier'])) {
                $data['identifier'] = $model->getIdentifier();
            }

            $this->_getSession()->setFormData($data);
            $this->_redirect('*/*/edit', ['page_id' => $this->getRequest()->getParam('page_id')]);
            return;
        }
        $this->_redirect('*/*/');
    }

    /**
     * Delete action
     */
    public function deleteAction()
    {
        // check if we know what should be deleted
        if ($id = $this->getRequest()->getParam('page_id')) {
            $title = '';
            try {
                // init model and delete
                $model = Mage::getModel('cms/page');
                $model->load($id);
                $title = $model->getTitle();
                $model->delete();
                // display success message
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('cms')->__('The page has been deleted.'),
                );
                // go to grid
                Mage::dispatchEvent('adminhtml_cmspage_on_delete', ['title' => $title, 'status' => 'success']);
                $this->_redirect('*/*/');
                return;
            } catch (Exception $e) {
                Mage::dispatchEvent('adminhtml_cmspage_on_delete', ['title' => $title, 'status' => 'fail']);
                // display error message
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                // go back to edit form
                $this->_redirect('*/*/edit', ['page_id' => $id]);
                return;
            }
        }
        // display error message
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('cms')->__('Unable to find a page to delete.'));
        // go to grid
        $this->_redirect('*/*/');
    }

    /**
     * Controller pre-dispatch method
     *
     * @return Mage_Adminhtml_Controller_Action
     */
    #[\Override]
    public function preDispatch()
    {
        $this->_setForcedFormKeyActions('delete');
        return parent::preDispatch();
    }

    #[\Override]
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        return match ($action) {
            'new', 'save' => Mage::getSingleton('admin/session')->isAllowed('cms/page/save'),
            'delete' => Mage::getSingleton('admin/session')->isAllowed('cms/page/delete'),
            default => Mage::getSingleton('admin/session')->isAllowed('cms/page'),
        };
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data = $this->_filterDates($data, ['custom_theme_from', 'custom_theme_to']);
        return $data;
    }

    /**
     * Validate post data
     *
     * @param array $data
     * @return bool     Return FALSE if someone item is invalid
     */
    protected function _validatePostData($data)
    {
        $errorNo = true;
        if (!empty($data['layout_update_xml']) || !empty($data['custom_layout_update_xml'])) {
            /** @var Mage_Adminhtml_Model_LayoutUpdate_Validator $validatorCustomLayout */
            $validatorCustomLayout = Mage::getModel('adminhtml/layoutUpdate_validator');
            if (!empty($data['layout_update_xml']) && !$validatorCustomLayout->isValid($data['layout_update_xml'])) {
                $errorNo = false;
            }
            if (!empty($data['custom_layout_update_xml'])
                && !$validatorCustomLayout->isValid($data['custom_layout_update_xml'])
            ) {
                $errorNo = false;
            }
            foreach ($validatorCustomLayout->getMessages() as $message) {
                $this->_getSession()->addError($message);
            }
        }
        return $errorNo;
    }
}
