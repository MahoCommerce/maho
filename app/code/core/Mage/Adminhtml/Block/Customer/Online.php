<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml online customers page content block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Customer_Online extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('customer/online.phtml');
    }

    #[\Override]
    public function _beforeToHtml()
    {
        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/customer_online_grid', 'customer.grid'));
        return parent::_beforeToHtml();
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild('filterForm', $this->getLayout()->createBlock('adminhtml/customer_online_filter'));
        return parent::_prepareLayout();
    }

    public function getFilterFormHtml()
    {
        return $this->getChild('filterForm')->toHtml();
    }
}
