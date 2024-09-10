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
 * Adminhtml Report Customers Reviews renderer
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Report_Grid_Column_Renderer_Customer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        $id   = $row->getCustomerId();

        if (!$id) {
            return Mage::helper('adminhtml')->__('Show Reviews');
        }

        return sprintf(
            '<a href="%s">%s</a>',
            $this->getUrl('*/catalog_product_review', ['customerId' => $id]),
            Mage::helper('adminhtml')->__('Show Reviews')
        );
    }
}
