<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Adminhtml_Accessibilityscan_DashboardController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/accessibilityscan/dashboard';

    #[Maho\Config\Route('/admin/accessibilityscan_dashboard/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Accessibility Scan'))
            ->_title($this->__('Dashboard'));

        $this->loadLayout();
        $this->_setActiveMenu('system/accessibilityscan/dashboard');
        $this->_addBreadcrumb($this->__('Accessibility Scan'), $this->__('Accessibility Scan'));

        $this->renderLayout();
    }
}
