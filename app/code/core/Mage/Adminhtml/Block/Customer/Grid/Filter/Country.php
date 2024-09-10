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
 * Country customer grid column filter
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Customer_Grid_Filter_Country extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    #[\Override]
    protected function _getOptions()
    {
        $options = Mage::getResourceModel('directory/country_collection')->load()->toOptionArray();
        array_unshift($options, ['value' => '', 'label' => Mage::helper('customer')->__('All countries')]);
        return $options;
    }
}
