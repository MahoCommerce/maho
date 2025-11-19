<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_System_DesignController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/design';

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

    public function indexAction(): void
    {
        $this
            ->_title($this->__('System'))->_title($this->__('Design'))
            ->loadLayout()
            ->_setActiveMenu('system/design')
            ->_addContent($this->getLayout()->createBlock('adminhtml/system_design'))
            ->renderLayout();
    }

    public function gridAction(): void
    {
        $this->getResponse()->setBody($this->getLayout()->createBlock('adminhtml/system_design_grid')->toHtml());
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $this
            ->_title($this->__('System'))
            ->_title($this->__('Design'))
            ->loadLayout()
            ->_setActiveMenu('system/design');

        $id = (int) $this->getRequest()->getParam('id');
        $design = Mage::getModel('core/design');

        if ($id) {
            $design->load($id);
        }

        $this->_title($design->getId() ? $this->__('Edit Design Change') : $this->__('New Design Change'));

        Mage::register('design', $design);

        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_design_edit'));
        $this->_addLeft($this->getLayout()->createBlock('adminhtml/system_design_edit_tabs', 'design_tabs'));

        $this->renderLayout();
    }

    public function saveAction(): void
    {
        if ($data = $this->getRequest()->getPost()) {
            if (!empty($data['design'])) {
                $data['design'] = $this->_filterDates($data['design'], ['date_from', 'date_to']);
            }

            $id = (int) $this->getRequest()->getParam('id');

            $design = Mage::getModel('core/design');
            if ($id) {
                $design->load($id);
            }

            $design->setData($data['design']);
            if ($id) {
                $design->setId($id);
            }
            try {
                $design->save();

                Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The design change has been saved.'));
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')
                    ->addError($e->getMessage())
                    ->setDesignData($data);
                $this->_redirect('*/*/edit', ['id' => $design->getId()]);
                return;
            }
        }

        $this->_redirect('*/*/');
    }

    public function deleteAction(): void
    {
        if ($id = $this->getRequest()->getParam('id')) {
            $design = Mage::getModel('core/design')->load($id);

            try {
                $design->delete();

                Mage::getSingleton('adminhtml/session')
                    ->addSuccess($this->__('The design change has been deleted.'));
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('adminhtml/session')
                    ->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')
                    ->addException($e, $this->__('Cannot delete the design change.'));
            }
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }
}
