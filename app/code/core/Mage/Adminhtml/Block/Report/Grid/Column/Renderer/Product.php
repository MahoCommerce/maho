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

class Mage_Adminhtml_Block_Report_Grid_Column_Renderer_Product extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $id   = $row->getId();

        return sprintf(
            '<a href="%s">%s</a>',
            $this->getUrl('*/catalog_product_review/', ['productId' => $id]),
            Mage::helper('adminhtml')->__('Show Reviews'),
        );
    }
}
