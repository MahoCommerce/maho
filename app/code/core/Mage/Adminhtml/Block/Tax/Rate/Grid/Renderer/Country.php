<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml tax rates grid item renderer country
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Tax_Rate_Grid_Renderer_Country extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Country
{
    /**
     * Render column for export
     *
     * @return string
     */
    #[\Override]
    public function renderExport(Varien_Object $row)
    {
        return $row->getData($this->getColumn()->getIndex());
    }
}
