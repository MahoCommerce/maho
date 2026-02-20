<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Adminhtml_Billing_Agreement_View_Tab_Orders extends Mage_Adminhtml_Block_Sales_Order_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Initialize grid params
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('billing_agreement_orders');
    }

    /**
     * Prepare related orders collection
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('sales/order_grid_collection');
        $collection->addBillingAgreementsFilter(Mage::registry('current_billing_agreement')->getId());
        $this->setCollection($collection);
        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return $this->__('Related Orders');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->__('Related Orders');
    }

    /**
     * Can show tab in tabs
     *
     * @return bool
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return bool
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }

    /**
     * Retrieve grid url
     *
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/ordersGrid', ['_current' => true]);
    }

    /**
     * Remove import/export field from grid
     *
     * @return bool
     */
    #[\Override]
    public function getExportTypes()
    {
        return false;
    }

    /**
     * Disable massaction in grid
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        return $this;
    }
}
