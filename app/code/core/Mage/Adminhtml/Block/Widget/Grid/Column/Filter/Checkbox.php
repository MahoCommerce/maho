<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Checkbox grid column filter
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Checkbox extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    /**
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        return '<span class="head-massaction">' . parent::getHtml() . '</span>';
    }

    /**
     * @return array[]
     */
    #[\Override]
    protected function _getOptions()
    {
        return [
            [
                'label' => Mage::helper('adminhtml')->__('Any'),
                'value' => ''
            ],
            [
                'label' => Mage::helper('adminhtml')->__('Yes'),
                'value' => 1
            ],
            [
                'label' => Mage::helper('adminhtml')->__('No'),
                'value' => 0
            ],
        ];
    }

    /**
     * @return array|null
     */
    #[\Override]
    public function getCondition()
    {
        if ($this->getValue()) {
            return $this->getColumn()->getValue();
        } else {
            return [
                ['neq' => $this->getColumn()->getValue()],
                ['is' => new Zend_Db_Expr('NULL')]
            ];
        }
    }
}
