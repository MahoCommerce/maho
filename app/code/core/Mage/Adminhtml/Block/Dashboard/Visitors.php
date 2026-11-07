<?php

/**
 * Dashboard tabs with the visitor log reports.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Dashboard_Visitors extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('visitor_tab');
        $this->setDestElementId('visitor_tab_content');
        $this->setTemplate('widget/tabshoriz.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        // Tabs in alphabetical order
        $this->addTab('devices_browsers', [
            'label'     => $this->__('Devices & Browsers'),
            'url'       => $this->getUrl('*/*/devices', ['_current' => true]),
            'class'     => 'ajax',
            'active'    => true,
        ]);

        $this->addTab('engagement', [
            'label'     => $this->__('Engagement'),
            'url'       => $this->getUrl('*/*/engagement', ['_current' => true]),
            'class'     => 'ajax',
        ]);

        $this->addTab('entry_exit_pages', [
            'label'     => $this->__('Entry & Exit Pages'),
            'url'       => $this->getUrl('*/*/entryExit', ['_current' => true]),
            'class'     => 'ajax',
        ]);

        $this->addTab('languages', [
            'label'     => $this->__('Languages'),
            'url'       => $this->getUrl('*/*/languages', ['_current' => true]),
            'class'     => 'ajax',
        ]);

        $this->addTab('top_pages', [
            'label'     => $this->__('Top Pages'),
            'url'       => $this->getUrl('*/*/topPages', ['_current' => true]),
            'class'     => 'ajax',
        ]);

        $this->addTab('traffic_sources', [
            'label'     => $this->__('Traffic Sources'),
            'url'       => $this->getUrl('*/*/trafficSources', ['_current' => true]),
            'class'     => 'ajax',
        ]);

        return parent::_prepareLayout();
    }
}
