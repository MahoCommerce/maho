<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Adminhtml_System_Tools_CronjobsController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('system/tools/cronjobs');
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('System'),
            Mage::helper('cron')->__('System'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Tools'),
            Mage::helper('cron')->__('Tools'),
        );
        $this->_addBreadcrumb(
            Mage::helper('cron')->__('Cron Jobs'),
            Mage::helper('cron')->__('Cron Jobs'),
        );
        $this->_addContent($this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs'));
        $this->renderLayout();
    }

    public function gridAction(): void
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('cron/adminhtml_system_tools_cronjobs_grid')->toHtml(),
        );
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/tools/cronjobs');
    }
}
