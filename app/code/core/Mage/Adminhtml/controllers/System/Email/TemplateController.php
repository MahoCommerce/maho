<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_System_Email_TemplateController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/email_template';

    /**
     * Index action
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Transactional Emails'));

        if ($this->getRequest()->getQuery('ajax')) {
            $this->_forward('grid');
            return;
        }

        $this->loadLayout();
        $this->_setActiveMenu('system/email_template');
        $this->_addBreadcrumb(
            Mage::helper('adminhtml')->__('Transactional Emails'),
            Mage::helper('adminhtml')->__('Transactional Emails'),
        );

        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_email_template', 'template'));
        $this->renderLayout();
    }

    /**
     * Grid action
     */
    public function gridAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/system_email_template_grid')->toHtml(),
        );
    }

    /**
     * New transactional email action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit transactional email action
     */
    public function editAction()
    {
        $this->loadLayout();
        $template = $this->_initTemplate('id');
        $this->_setActiveMenu('system/email_template');
        $this->_addBreadcrumb(
            Mage::helper('adminhtml')->__('Transactional Emails'),
            Mage::helper('adminhtml')->__('Transactional Emails'),
            $this->getUrl('*/*'),
        );

        if ($this->getRequest()->getParam('id')) {
            $this->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Edit Template'),
                Mage::helper('adminhtml')->__('Edit System Template'),
            );
        } else {
            $this->_addBreadcrumb(
                Mage::helper('adminhtml')->__('New Template'),
                Mage::helper('adminhtml')->__('New System Template'),
            );
        }

        $this->_title($template->getId() ? $template->getTemplateCode() : $this->__('New Template'));
        $this->_addContent(
            $this->getLayout()->createBlock('adminhtml/system_email_template_edit', 'template_edit')->setEditMode(
                (bool) $this->getRequest()->getParam('id'),
            ),
        );
        $this->renderLayout();
    }

    /**
     * Save action
     *
     * @throws Mage_Core_Exception
     */
    public function saveAction()
    {
        $request = $this->getRequest();
        $id = $this->getRequest()->getParam('id');

        $template = $this->_initTemplate('id');
        if (!$template->getId() && $id) {
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('adminhtml')->__('This Email template no longer exists.'),
            );
            $this->_redirect('*/*/');
            return;
        }

        try {
            $allowedHtmlTags = ['template_text', 'styles', 'variables'];
            if (Mage::helper('adminhtml')->hasTags($request->getParams(), $allowedHtmlTags)) {
                Mage::throwException(Mage::helper('adminhtml')->__('Invalid template data.'));
            }

            $template->setTemplateSubject($request->getParam('template_subject'))
                ->setTemplateCode($request->getParam('template_code'))
                ->setTemplateText($request->getParam('template_text'))
                ->setTemplateStyles($request->getParam('template_styles'))
                ->setModifiedAt(Mage::getSingleton('core/date')->gmtDate())
                ->setOrigTemplateCode($request->getParam('orig_template_code'))
                ->setOrigTemplateVariables($request->getParam('orig_template_variables'));

            if (!$template->getId()) {
                $template->setAddedAt(Mage::getSingleton('core/date')->gmtDate());
                $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_HTML);
            }

            if ($request->getParam('_change_type_flag')) {
                $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_TEXT);
                $template->setTemplateStyles('');
            }

            $template->save();
            Mage::getSingleton('adminhtml/session')->setFormData(false);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('adminhtml')->__('The email template has been saved.'),
            );
            $this->_redirect('*/*');
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->setData(
                'email_template_form_data',
                $this->getRequest()->getParams(),
            );
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            $this->_forward('new');
        }
    }

    /**
     * Delete action
     */
    public function deleteAction()
    {
        $template = $this->_initTemplate('id');
        if ($template->getId()) {
            try {
                $template->delete();
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__('The email template has been deleted.'),
                );
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError(
                    Mage::helper('adminhtml')->__('An error occurred while deleting email template data. Please review log and try again.'),
                );
                Mage::logException($e);
                $this->_redirect('*/*/edit', ['id' => $template]);
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(
            Mage::helper('adminhtml')->__('Unable to find a Email Template to delete.'),
        );
        $this->_redirect('*/*/');
    }

    /**
     * Preview action
     */
    public function previewAction()
    {
        $this->loadLayout('systemPreview');
        $this->renderLayout();
    }

    /**
     * Set template data to retrieve it in template info form
     */
    public function defaultTemplateAction()
    {
        $template = $this->_initTemplate('id');
        $templateCode = $this->getRequest()->getParam('code');

        $template->loadDefault($templateCode, $this->getRequest()->getParam('locale'));
        $template->setData('orig_template_code', $templateCode);
        $template->setData('template_variables', Mage::helper('core')->jsonEncode($template->getVariablesOptionArray(true)));

        $templateBlock = $this->getLayout()->createBlock('adminhtml/system_email_template_edit');
        $template->setData('orig_template_used_default_for', $templateBlock->getUsedDefaultForPaths(false));

        $this->getResponse()->setBodyJson($template);
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

    /**
     * Load email template from request
     *
     * @param string $idFieldName
     * @return Mage_Adminhtml_Model_Email_Template
     */
    protected function _initTemplate($idFieldName = 'template_id')
    {
        $this->_title($this->__('System'))->_title($this->__('Transactional Emails'));

        $id = (int) $this->getRequest()->getParam($idFieldName);
        $model = Mage::getModel('adminhtml/email_template');
        if ($id) {
            $model->load($id);
        }
        if (!Mage::registry('email_template')) {
            Mage::register('email_template', $model);
        }
        if (!Mage::registry('current_email_template')) {
            Mage::register('current_email_template', $model);
        }
        return $model;
    }
}
