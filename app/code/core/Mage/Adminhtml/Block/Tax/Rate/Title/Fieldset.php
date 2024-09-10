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
 * Tax Rate Titles Fieldset
 *
 */
class Mage_Adminhtml_Block_Tax_Rate_Title_Fieldset extends Varien_Data_Form_Element_Fieldset
{
    #[\Override]
    public function getChildrenHtml()
    {
        return Mage::getBlockSingleton('adminhtml/tax_rate_title')->toHtml();
    }
}
