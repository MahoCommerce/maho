<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form note element
 *
 * @category   Varien
 * @package    Varien_Data
 *
 * @method string getText()
 */
class Varien_Data_Form_Element_Note extends Varien_Data_Form_Element_Abstract
{
    /**
     * Varien_Data_Form_Element_Note constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('note');
        //$this->setExtType('textfield');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '<span id="' . $this->getHtmlId() . '">' . $this->getText() . '</span>';
        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
