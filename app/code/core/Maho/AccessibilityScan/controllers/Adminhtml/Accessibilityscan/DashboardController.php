<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Adminhtml_Accessibilityscan_DashboardController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/tools/accessibilityscan';

    #[Maho\Config\Route('/admin/accessibilityscan_dashboard/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Accessibility Scan'));

        $this->loadLayout();
        $this->_setActiveMenu('system/tools/accessibilityscan');
        $this->_addBreadcrumb($this->__('Accessibility Scan'), $this->__('Accessibility Scan'));

        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/accessibilityscan_dashboard/grid')]
    public function gridAction(): void
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }
}
