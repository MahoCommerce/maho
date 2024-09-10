<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Recurring profile information tab
 *
 * @category   Mage
 * @package    Mage_Sales
 *
 * @method string getLabel()
 */
class Mage_Sales_Block_Adminhtml_Recurring_Profile_View_Tab_Info extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Label getter
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('sales')->__('Profile Information');
    }

    /**
     * Also label getter :)
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->getLabel();
    }

    /**
     * @return bool
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
