<?php

/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form boolean element
 *
 * @category   Varien
 * @package    Varien_Data
 */
class Varien_Data_Form_Element_Boolean extends Varien_Data_Form_Element_Select
{
    /**
     * Varien_Data_Form_Element_Boolean constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setValues([
            [
                'label' => Mage::helper('core')->__('Yes'),
                'value' => 1,
            ],
            [
                'label' => Mage::helper('core')->__('No'),
                'value' => 0,
            ],
        ]);
    }
}
