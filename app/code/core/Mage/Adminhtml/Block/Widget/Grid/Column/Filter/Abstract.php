<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Interface
{
    /**
     * Column related to filter
     */
    protected ?Mage_Adminhtml_Block_Widget_Grid_Column $_column = null;

    #[\Override]
    public function setColumn(Mage_Adminhtml_Block_Widget_Grid_Column $column): static
    {
        $this->_column = $column;
        return $this;
    }

    #[\Override]
    public function getColumn(): ?Mage_Adminhtml_Block_Widget_Grid_Column
    {
        return $this->_column;
    }

    /**
     * Retrieve html name of filter
     *
     * @return string
     */
    protected function _getHtmlName()
    {
        return $this->getColumn()->getId();
    }

    /**
     * Retrieve html id of filter
     *
     * @return string
     */
    protected function _getHtmlId()
    {
        return $this->getColumn()->getGrid()->getId() . '_'
            . $this->getColumn()->getGrid()->getVarNameFilter() . '_'
            . $this->getColumn()->getId();
    }

    /**
     * Keeps this filter out of any form it is rendered inside, see Grid::UNBOUND_FORM_ID
     */
    protected function _getUnboundFormAttribute(): string
    {
        return ' form="' . Mage_Adminhtml_Block_Widget_Grid::UNBOUND_FORM_ID . '"';
    }

    /**
     * Retrieve escaped value
     *
     * @param mixed $index
     * @return string
     */
    public function getEscapedValue($index = null)
    {
        return htmlspecialchars((string) $this->getValue($index));
    }

    /**
     * Retrieve condition
     *
     * @return array
     */
    public function getCondition()
    {
        $helper = Mage::getResourceHelper('core');
        $likeExpression = $helper->addLikeEscape($this->getValue(), ['position' => 'any']);
        return ['like' => $likeExpression];
    }

    /**
     * Retrieve filter html
     *
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        return '';
    }
}
