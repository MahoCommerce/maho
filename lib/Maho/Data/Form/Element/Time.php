<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Time extends AbstractElement
{
    /**
     * Time constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('time');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getName()
    {
        $name = parent::getName();
        if (!str_contains($name, '[]')) {
            $name .= '[]';
        }
        return $name;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('select');

        $value_hrs = 0;
        $value_min = 0;
        $value_sec = 0;

        if ($value = $this->getValue()) {
            $values = explode(',', $value);
            if (count($values) === 3) {
                $value_hrs = $values[0];
                $value_min = $values[1];
                $value_sec = $values[2];
            }
        }

        $html = '<input type="hidden" id="' . $this->getHtmlId() . '" />';
        $html .= '<select name="' . $this->getName() . '" ' . $this->serialize($this->getHtmlAttributes()) . ' style="width:40px">' . "\n";
        for ($i = 0; $i < 24; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html .= '<option value="' . $hour . '" ' . (($value_hrs == $i) ? 'selected="selected"' : '') . '>' . $hour . '</option>';
        }
        $html .= '</select>' . "\n";

        $html .= '&nbsp;:&nbsp;<select name="' . $this->getName() . '" ' . $this->serialize($this->getHtmlAttributes()) . ' style="width:40px">' . "\n";
        for ($i = 0; $i < 60; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html .= '<option value="' . $hour . '" ' . (($value_min == $i) ? 'selected="selected"' : '') . '>' . $hour . '</option>';
        }
        $html .= '</select>' . "\n";

        $html .= '&nbsp;:&nbsp;<select name="' . $this->getName() . '" ' . $this->serialize($this->getHtmlAttributes()) . ' style="width:40px">' . "\n";
        for ($i = 0; $i < 60; $i++) {
            $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
            $html .= '<option value="' . $hour . '" ' . (($value_sec == $i) ? 'selected="selected"' : '') . '>' . $hour . '</option>';
        }
        $html .= '</select>' . "\n";
        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
