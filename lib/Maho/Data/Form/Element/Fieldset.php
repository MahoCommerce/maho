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

use Maho\Data\Form;
use Maho\Data\Form\Element\AbstractElement;

/**
 * @method string getLegend()
 */
class Fieldset extends AbstractElement
{
    /**
     * Sort child elements by specified data key
     *
     * @var string
     */
    protected $_sortChildrenByKey = '';

    /**
     * Children sort direction
     *
     * @var int
     */
    protected $_sortChildrenDirection = SORT_ASC;

    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->_renderer = Form::getFieldsetRenderer();
        $this->setType('fieldset');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '<fieldset id="' . $this->getHtmlId() . '"' . $this->serialize(['class']) . '>' . "\n";
        if ($this->getLegend()) {
            $html .= '<legend>' . $this->getLegend() . '</legend>' . "\n";
        }
        $html .= $this->getChildrenHtml();
        $html .= '</fieldset></div>' . "\n";
        $html .= $this->getAfterElementHtml();
        return $html;
    }

    /**
     * @return string
     */
    public function getChildrenHtml()
    {
        $html = '';
        foreach ($this->getSortedElements() as $element) {
            if ($element->getType() != 'fieldset') {
                $html .= $element->toHtml();
            }
        }
        return $html;
    }

    /**
     * @return string
     */
    public function getSubFieldsetHtml()
    {
        $html = '';
        foreach ($this->getSortedElements() as $element) {
            if ($element->getType() == 'fieldset') {
                $html .= $element->toHtml();
            }
        }
        return $html;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getDefaultHtml()
    {
        $html = '<div><h4>' . $this->getLegend() . '</h4>' . "\n";
        $html .= $this->getElementHtml();
        return $html;
    }

    /**
     * @param string $elementId
     * @param string $type
     * @param array $config
     * @param string|false $after
     * @return AbstractElement
     */
    #[\Override]
    public function addField($elementId, $type, $config, $after = false)
    {
        $element = parent::addField($elementId, $type, $config, $after);
        if ($renderer = Form::getFieldsetElementRenderer()) {
            $element->setRenderer($renderer);
        }
        return $element;
    }

    /**
     * Commence sorting elements by values by specified data key
     *
     * @param string $key
     * @param int $direction
     * @return Fieldset
     */
    public function setSortElementsByAttribute($key, $direction = SORT_ASC)
    {
        $this->_sortChildrenByKey = $key;
        $this->_sortDirection = $direction;
        return $this;
    }

    /**
     * Get sorted elements as array
     *
     * @return array
     */
    public function getSortedElements()
    {
        $elements = [];
        // sort children by value by specified key
        if ($this->_sortChildrenByKey) {
            $sortKey = $this->_sortChildrenByKey;
            $uniqueIncrement = 0; // in case if there are elements with same values
            foreach ($this->getElements() as $e) {
                $key = '_' . $uniqueIncrement;
                if ($e->hasData($sortKey)) {
                    $key = $e->getDataUsingMethod($sortKey) . $key;
                }
                $elements[$key] = $e;
                $uniqueIncrement++;
            }
            ksort($elements, $this->_sortChildrenDirection);
            $elements = array_values($elements);
        } else {
            foreach ($this->getElements() as $element) {
                $elements[] = $element;
            }
        }
        return $elements;
    }
}
