<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

/**
 * @method $this setCols(int $int)
 * @method $this setRows(int $int)
 */
class Textarea extends AbstractElement
{
    /**
     * Textarea constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('textarea');
        $this->setExtType('textarea');
        $this->setRows(2);
        $this->setCols(15);
    }

    /**
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['title', 'class', 'style', 'onclick', 'onchange', 'rows', 'cols', 'readonly', 'disabled', 'onkeyup', 'tabindex'];
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('textarea');
        $html = '<textarea id="' . $this->getHtmlId() . '" name="' . $this->getName() . '" ' . $this->serialize($this->getHtmlAttributes()) . ' >';
        $html .= $this->getEscapedValue();
        $html .= '</textarea>';
        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
