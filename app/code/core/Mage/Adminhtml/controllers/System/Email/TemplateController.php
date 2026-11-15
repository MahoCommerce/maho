<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
    #[Maho\Config\Route('/admin/system_email_template/index')]
    public function indexAction(): void
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
    #[Maho\Config\Route('/admin/system_email_template/grid')]
    public function gridAction(): void
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('adminhtml/system_email_template_grid')->toHtml(),
        );
    }

    /**
     * New transactional email action
     */
    #[Maho\Config\Route('/admin/system_email_template/new')]
    public function newAction(): void
    {
        $this->_forward('edit');
    }

    /**
     * Edit transactional email action
     */
    #[Maho\Config\Route('/admin/system_email_template/edit')]
    public function editAction(): void
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
     */
    #[Maho\Config\Route('/admin/system_email_template/save')]
    public function saveAction(): void
    {
        $request = $this->getRequest();
        $session = Mage::getSingleton('adminhtml/session');
        $service = $this->getEmailTemplateService();

        try {
            $id = (int) $request->getParam('id');
            $template = $id ? $service->getById($id) : $service->newTemplate();
            $template->setTemplateSubject($request->getParam('template_subject'))
                ->setTemplateCode($request->getParam('template_code'))
                ->setTemplateText($request->getParam('template_text'))
                ->setTemplateStyles($request->getParam('template_styles'))
                ->setOrigTemplateCode($request->getParam('orig_template_code'))
                ->setOrigTemplateVariables($request->getParam('orig_template_variables'));
            if ($request->getParam('_change_type_flag')) {
                $template->setTemplateType(Mage_Core_Model_Email_Template::TYPE_TEXT);
            }

            $service->save($template);
            $session->setFormData(false);
            $session->addSuccess(Mage::helper('adminhtml')->__('The email template has been saved.'));
            $this->_redirect('*/*');
            return;
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $session->addError(Mage::helper('adminhtml')->__('This Email template no longer exists.'));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('adminhtml')->__('An error occurred while saving email template data. Please review log and try again.'));
        }
        $session->setData('email_template_form_data', $request->getParams());
        $this->_forward('new');
    }

    /**
     * Delete action
     */
    #[Maho\Config\Route('/admin/system_email_template/delete')]
    public function deleteAction(): void
    {
        $session = $this->_getSession();
        if ($id = (int) $this->getRequest()->getParam('id')) {
            try {
                $service = $this->getEmailTemplateService();
                $service->delete($service->getById($id));
                $session->addSuccess(Mage::helper('adminhtml')->__('The email template has been deleted.'));
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception_NoSuchEntity) {
                // The message below covers a template that no longer exists
            } catch (Mage_Core_Exception $e) {
                $session->addError($e->getMessage());
            } catch (Exception $e) {
                $session->addError(
                    Mage::helper('adminhtml')->__('An error occurred while deleting email template data. Please review log and try again.'),
                );
                Mage::logException($e);
                $this->_redirect('*/*/edit', ['id' => $id]);
                return;
            }
        }
        $session->addError(Mage::helper('adminhtml')->__('Unable to find a Email Template to delete.'));
        $this->_redirect('*/*/');
    }

    /**
     * Preview action
     */
    #[Maho\Config\Route('/admin/system_email_template/preview')]
    public function previewAction(): void
    {
        $this->loadLayout('systemPreview');
        $this->renderLayout();
    }

    /**
     * Set template data to retrieve it in template info form
     */
    #[Maho\Config\Route('/admin/system_email_template/defaultTemplate')]
    public function defaultTemplateAction(): void
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

    protected function getEmailTemplateService(): Mage_Core_Service_Email_Template
    {
        return Mage::getService('core/email_template');
    }
}
