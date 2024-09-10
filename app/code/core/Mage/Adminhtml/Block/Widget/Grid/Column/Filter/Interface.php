<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Grid column filter interface
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
interface Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Interface
{
    public function getColumn();
    public function setColumn($column);
    public function getHtml();
}
