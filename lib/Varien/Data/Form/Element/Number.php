<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form number element
 *
 * @category   Varien
 * @package    Varien_Data
 */
class Varien_Data_Form_Element_Number extends Varien_Data_Form_Element_Abstract
{
    /**
     * Varien_Data_Form_Element_Number constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('number');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        $this->addClass('input-text');
        return parent::getHtml();
    }

    /**
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'onclick', 'onchange', 'onkeyup', 'disabled', 'readonly', 'min', 'max', 'step', 'tabindex'];
    }
}
