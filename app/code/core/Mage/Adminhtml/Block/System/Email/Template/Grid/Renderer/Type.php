<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Email_Template_Grid_Renderer_Type extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected static $_types = [
        Mage_Core_Model_Template::TYPE_HTML => 'HTML',
        Mage_Core_Model_Template::TYPE_TEXT => 'Text',
    ];

    /**
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $str = self::$_types[$row->getTemplateType()] ?? Mage::helper('adminhtml')->__('Unknown');
        return Mage::helper('adminhtml')->__($str);
    }
}
