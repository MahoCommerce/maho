<?php

/**
 * Maho
 *
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getBeforeElementHtml()
 */
class Varien_Data_Form_Element_Link extends Varien_Data_Form_Element_Abstract
{
    /**
     * Varien_Data_Form_Element_Link constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('link');
    }

    /**
     * Generates element html
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = $this->getBeforeElementHtml();
        $html .= '<a id="' . $this->getHtmlId() . '" ' . $this->serialize($this->getHtmlAttributes()) . '>' . $this->getEscapedValue() . "</a>\n";
        $html .= $this->getAfterElementHtml();
        return $html;
    }

    /**
     * Prepare array of anchor attributes
     *
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['charset', 'coords', 'href', 'hreflang', 'rel', 'rev', 'name',
            'shape', 'target', 'accesskey', 'class', 'dir', 'lang', 'style',
            'tabindex', 'title', 'xml:lang', 'onblur', 'onclick', 'ondblclick',
            'onfocus', 'onmousedown', 'onmousemove', 'onmouseout', 'onmouseover',
            'onmouseup', 'onkeydown', 'onkeypress', 'onkeyup'];
    }
}
