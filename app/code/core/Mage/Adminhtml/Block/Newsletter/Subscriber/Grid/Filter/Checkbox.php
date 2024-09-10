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
 * Adminhtml newsletter subscribers grid filter checkbox
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Newsletter_Subscriber_Grid_Filter_Checkbox extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    #[\Override]
    public function getCondition()
    {
        return [];
    }

    #[\Override]
    public function getHtml()
    {
        return '<input type="checkbox" onclick="subscriberController.checkCheckboxes(this)"/>';
    }
}
