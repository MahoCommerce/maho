<?php

/**
 * The reports that the administrator pinned in Reports > Explorer, as cards on the admin dashboard.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Report_Explorer_Pinned extends Mage_Adminhtml_Block_Report_Explorer
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('report/explorer/pinned.phtml');
    }

    #[\Override]
    public function getConfig(): array
    {
        return ['mode' => 'dashboard'] + parent::getConfig();
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $adminUserId = (int) Mage::helper('adminnotification')->getCurrentAdminUserId();
        if (!Mage::getSingleton('admin/session')->isAllowed('report/explorer')
            || Mage::getService('reports/savedReport')->pinnedIds($adminUserId) === []
        ) {
            return '';
        }
        return parent::_toHtml();
    }
}
