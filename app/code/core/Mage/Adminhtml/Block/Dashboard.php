<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Dashboard extends Mage_Adminhtml_Block_Template
{
    protected $_locale;

    /**
     * Location of the "Enable Chart" config param
     */
    public const XML_PATH_ENABLE_CHARTS = 'admin/dashboard/enable_charts';

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('dashboard/index.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'lastOrders',
            $this->getLayout()->createBlock('adminhtml/dashboard_orders_grid'),
        );

        $this->setChild(
            'totals',
            $this->getLayout()->createBlock('adminhtml/dashboard_totals'),
        );

        $this->setChild(
            'sales',
            $this->getLayout()->createBlock('adminhtml/dashboard_sales'),
        );

        $this->setChild(
            'lastSearches',
            $this->getLayout()->createBlock('adminhtml/dashboard_searches_last'),
        );

        $this->setChild(
            'topSearches',
            $this->getLayout()->createBlock('adminhtml/dashboard_searches_top'),
        );

        if (Mage::getStoreConfig(self::XML_PATH_ENABLE_CHARTS)) {
            $block = $this->getLayout()->createBlock('adminhtml/dashboard_diagrams');
        } else {
            $block = $this->getLayout()->createBlock('adminhtml/template')
                ->setTemplate('dashboard/graph/disabled.phtml')
                ->setConfigUrl($this->getUrl('adminhtml/system_config/edit', ['section' => 'admin']));
        }
        $this->setChild('diagrams', $block);

        $this->setChild(
            'grids',
            $this->getLayout()->createBlock('adminhtml/dashboard_grids'),
        );

        // Add visitor analytics blocks for left sidebar if visitor logging is enabled
        if (Mage::helper('log')->isVisitorLogEnabled()) {
            $this->setChild(
                'visitorStats',
                $this->getLayout()->createBlock('log/dashboard_stats'),
            );
            $this->setChild(
                'sessionMetrics',
                $this->getLayout()->createBlock('log/dashboard_session'),
            );
        }

        return parent::_prepareLayout();
    }

    public function getSwitchUrl()
    {
        if ($url = $this->getData('switch_url')) {
            return $url;
        }
        return $this->getUrl('*/*/*', ['_current' => true, 'period' => null]);
    }
}
