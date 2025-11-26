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

class Mage_Adminhtml_Block_Dashboard_Diagrams extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('diagram_tab');
        $this->setDestElementId('diagram_tab_content');
        $this->setTemplate('widget/tabshoriz.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->addTab('orders', [
            'label'     => $this->__('Orders'),
            'content'   => $this->getLayout()->createBlock('adminhtml/dashboard_tab_orders')->toHtml(),
            'active'    => true,
        ]);

        $this->addTab('amounts', [
            'label'     => $this->__('Amounts'),
            'content'   => $this->getLayout()->createBlock('adminhtml/dashboard_tab_amounts')->toHtml(),
        ]);

        // Add Visitors tab if visitor logging is enabled
        if (Mage::helper('log')->isVisitorLogEnabled()) {
            $this->addTab('visitors', [
                'label'     => $this->__('Visitors'),
                'content'   => $this->getLayout()->createBlock('log/dashboard_trends')->toHtml(),
            ]);
        }

        return parent::_prepareLayout();
    }
}
