<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Eav form boolean field helper
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Helper_Form_Boolean extends Varien_Data_Form_Element_Select
{
    public function __construct($attributes=[])
    {
        parent::__construct($attributes);
        $this->setValues([
            [
                'label' => Mage::helper('eav')->__('No'),
                'value' => 0,
            ],
            [
                'label' => Mage::helper('eav')->__('Yes'),
                'value' => 1,
            ],
        ]);
    }
}
