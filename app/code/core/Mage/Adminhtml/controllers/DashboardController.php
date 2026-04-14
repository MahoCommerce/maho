<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Config\Route;

class Mage_Adminhtml_DashboardController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'dashboard';

    #[Route('/admin/dashboard/index')]

    public function indexAction(): void
    {
        $this->_title($this->__('Dashboard'));

        $this->loadLayout();
        $this->_setActiveMenu('dashboard');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Dashboard'), Mage::helper('adminhtml')->__('Dashboard'));
        $this->renderLayout();
    }

    /**
     * Gets most viewed products list
     */
    #[Route('/admin/dashboard/productsViewed')]
    public function productsViewedAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets latest customers list
     */
    #[Route('/admin/dashboard/customersNewest')]
    public function customersNewestAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets the list of most active customers
     */
    #[Route('/admin/dashboard/customersMost')]
    public function customersMostAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    #[Route('/admin/dashboard/ajaxBlock')]

    public function ajaxBlockAction(): void
    {
        $output   = '';
        $blockTab = $this->getRequest()->getParam('block');
        if (in_array($blockTab, ['tab_orders', 'tab_amounts', 'totals'])) {
            $output = $this->getLayout()->createBlock('adminhtml/dashboard_' . $blockTab)->toHtml();
        }
        $this->getResponse()->setBody($output);
    }

    /**
     * Gets devices & browsers breakdown
     */
    #[Route('/admin/dashboard/devices')]
    public function devicesAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets engagement metrics
     */
    #[Route('/admin/dashboard/engagement')]
    public function engagementAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets entry & exit pages
     */
    #[Route('/admin/dashboard/entryExit')]
    public function entryExitAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets language breakdown
     */
    #[Route('/admin/dashboard/languages')]
    public function languagesAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets top pages
     */
    #[Route('/admin/dashboard/topPages')]
    public function topPagesAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Gets traffic sources
     */
    #[Route('/admin/dashboard/trafficSources')]
    public function trafficSourcesAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
