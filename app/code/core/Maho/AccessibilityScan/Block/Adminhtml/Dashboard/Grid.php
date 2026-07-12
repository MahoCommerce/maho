<?php

/**
 * Recent-scans grid embedded in the accessibility dashboard.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Block_Adminhtml_Dashboard_Grid extends Maho_AccessibilityScan_Block_Adminhtml_Scan_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('accessibilityScanDashboardGrid');
        $this->setSaveParametersInSession(false);
    }

    /**
     * The dashboard route has no view/grid actions, so point row clicks and
     * AJAX reloads at the right controllers explicitly
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/accessibilityscan_scan/view', ['id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/accessibilityscan_dashboard/grid', ['_current' => true]);
    }
}
