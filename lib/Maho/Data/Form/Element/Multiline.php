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
 * Form multiline text elements
 *
 * @method int getLineCount()
 * @method $this setLineCount(int $value)
 */
class Multiline extends AbstractElement
{
    /**
     * Multiline constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('text');
        $this->setLineCount(2);
    }

    /**
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'onclick', 'onchange', 'disabled', 'maxlength'];
    }

    /**
     * @param int $suffix
     * @return string
     */
    #[\Override]
    public function getLabelHtml($suffix = 0)
    {
        return parent::getLabelHtml($suffix);
    }

    /**
     * Get element HTML
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '';
        $lineCount = $this->getLineCount();

        for ($i = 0; $i < $lineCount; $i++) {
            if ($i == 0 && $this->getRequired()) {
                $this->setClass('input-text required-entry');
            } else {
                $this->setClass('input-text');
            }
            $html .= '<div class="multi-input"><input id="' . $this->getHtmlId() . $i . '" name="' . $this->getName()
                . '[' . $i . ']' . '" value="' . $this->getEscapedValue($i) . '" '
                . $this->serialize($this->getHtmlAttributes()) . ' />' . "\n";
            if ($i == 0) {
                $html .= $this->getAfterElementHtml();
            }
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getDefaultHtml()
    {
        $html = '';
        $lineCount = $this->getLineCount();

        for ($i = 0; $i < $lineCount; $i++) {
            $html .= ($this->getNoSpan() === true) ? '' : '<span class="field-row">' . "\n";
            if ($i == 0) {
                $html .= '<label for="' . $this->getHtmlId() . $i . '">' . $this->getLabel()
                    . ($this->getRequired() ? ' <span class="required">*</span>' : '') . '</label>' . "\n";
                if ($this->getRequired()) {
                    $this->setClass('input-text required-entry');
                }
            } else {
                $this->setClass('input-text');
                $html .= '<label>&nbsp;</label>' . "\n";
            }
            $html .= '<input id="' . $this->getHtmlId() . $i . '" name="' . $this->getName() . '[' . $i . ']'
                . '" value="' . $this->getEscapedValue($i) . '"' . $this->serialize($this->getHtmlAttributes()) . ' />' . "\n";
            if ($i == 0) {
                $html .= $this->getAfterElementHtml();
            }
            $html .= ($this->getNoSpan() === true) ? '' : '</span>' . "\n";
        }
        return $html;
    }
}
