<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


abstract class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Interface
{
    protected $_defaultWidth;
    protected $_column;

    /**
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @return $this
     */
    #[\Override]
    public function setColumn($column)
    {
        $this->_column = $column;
        return $this;
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid_Column
     */
    #[\Override]
    public function getColumn()
    {
        return $this->_column;
    }

    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($this->getColumn()->getEditable()) {
            $value = $this->_getValue($row);
            return $value
                   . ($this->getColumn()->getEditOnly() ? '' : ($value != '' ? '' : '&nbsp;'))
                   . $this->_getInputValueElement($row);
        }
        return $this->_getValue($row);
    }

    /**
     * Render column for export
     *
     * @return string
     */
    public function renderExport(\Maho\DataObject $row)
    {
        return $this->render($row);
    }

    /**
     * @return string|null
     */
    protected function _getValue(\Maho\DataObject $row)
    {
        if ($getter = $this->getColumn()->getGetter()) {
            if (is_string($getter)) {
                return $row->$getter();
            }
            if (is_callable($getter)) {
                return call_user_func($getter, $row);
            }
            return '';
        }
        if ($index = $this->getColumn()->getIndex()) {
            return $row->getData($index);
        }
        return null;
    }

    /**
     * @return string
     */
    public function _getInputValueElement(\Maho\DataObject $row)
    {
        return  '<input type="text" class="input-text '
                . $this->getColumn()->getValidateClass()
                . '" name="' . $this->getColumn()->getId()
                . '" value="' . $this->_getInputValue($row) . '"/>';
    }

    /**
     * @return string|null
     */
    protected function _getInputValue(\Maho\DataObject $row)
    {
        return $this->_getValue($row);
    }

    /**
     * @return string
     */
    public function renderHeader()
    {
        if ($this->getColumn()->getGrid()->getSortable() !== false && $this->getColumn()->getSortable() !== false) {
            $className = 'not-sort';
            $dir = strtolower((string) $this->getColumn()->getDir());
            $nDir = ($dir == 'asc') ? 'desc' : 'asc';
            if ($this->getColumn()->getDir()) {
                $className = 'sort-arrow-' . $dir;
            }
            $out = '<a href="#" name="' . $this->getColumn()->getId() . '" title="' . $nDir . '" class="' . $className . '"><span class="sort-title">'
                   . $this->escapeHtml($this->getColumn()->getHeader()) . '</span></a>';
        } else {
            $out = $this->escapeHtml($this->getColumn()->getHeader());
        }
        return $out;
    }

    /**
     * @return string
     */
    public function renderProperty()
    {
        $out = '';
        $width = $this->_defaultWidth;

        if ($this->getColumn()->hasData('width')) {
            $customWidth = $this->getColumn()->getData('width');
            if (($customWidth === null) || (preg_match('/^[0-9]+%?$/', (string) $customWidth))) {
                $width = $customWidth;
            } elseif (preg_match('/^([0-9]+)px$/', $customWidth, $matches)) {
                $width = (int) $matches[1];
            }
        }

        if ($width !== null) {
            $out .= ' width="' . $width . '"';
        }

        return $out;
    }

    /**
     * @return string|null
     */
    public function renderCss()
    {
        return $this->getColumn()->getCssClass();
    }

    /**
     * @return string|null
     */
    public function getCopyableText(\Maho\DataObject $row)
    {
        return $this->_getValue($row);
    }
}
