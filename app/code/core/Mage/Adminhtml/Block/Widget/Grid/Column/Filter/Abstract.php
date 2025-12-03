<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Interface
{
    /**
     * Column related to filter
     *
     * @var Mage_Adminhtml_Block_Widget_Grid_Column
     */
    protected $_column;

    /**
     * Set column related to filter
     *
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
     * Retrieve column related to filter
     *
     * @return Mage_Adminhtml_Block_Widget_Grid_Column
     */
    #[\Override]
    public function getColumn()
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
