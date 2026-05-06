<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_System_Tools_HealthcheckController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/healthcheck';

    #[Maho\Config\Route('/admin/system_tools_healthcheck/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Tools'))
            ->_title($this->__('Health Check'));

        $this->loadLayout()
            ->_setActiveMenu('system/tools/healthcheck')
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('System'),
                Mage::helper('adminhtml')->__('System'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Tools'),
                Mage::helper('adminhtml')->__('Tools'),
            )
            ->_addBreadcrumb(
                Mage::helper('adminhtml')->__('Health Check'),
                Mage::helper('adminhtml')->__('Health Check'),
            );

        $this->renderLayout();
    }

    #[\Override]
    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed(self::ADMIN_RESOURCE);
    }
}
