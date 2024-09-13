<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form text element
 *
 * @category   Varien
 * @package    Varien_Data
 */
class Varien_Data_Form_Element_Obscure extends Varien_Data_Form_Element_Password
{
    /**
     * @var string
     */
    protected $_obscuredValue = '******';

    /**
     * Hide value to make sure it will not show in HTML
     *
     * @param string $index
     * @return string
     */
    #[\Override]
    public function getEscapedValue($index = null)
    {
        $value = parent::getEscapedValue($index);
        if (!empty($value)) {
            return $this->_obscuredValue;
        }
        return $value;
    }

    /**
     * Returns list of html attributes possible to output in HTML
     *
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'onclick', 'onchange', 'onkeyup', 'disabled', 'readonly', 'maxlength', 'tabindex'];
    }
}
