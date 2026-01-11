<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Search_Grid_Renderer_Price extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Price
{
    /**
     * Render minimal price for downloadable products
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($row->getTypeId() == 'downloadable') {
            $row->setPrice($row->getPrice());
        }
        return parent::render($row);
    }
}
