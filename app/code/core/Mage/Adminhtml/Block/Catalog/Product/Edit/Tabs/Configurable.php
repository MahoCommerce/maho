<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * admin edit tabs for configurable products
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs_Configurable extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    #[\Override]
    protected function _prepareLayout()
    {
        $this->addTab('super_settings', [
            'label'     => Mage::helper('catalog')->__('Configurable Product Settings'),
            'content'   => $this->getLayout()->createBlock('adminhtml/catalog_product_edit_tab_super_settings')->toHtml(),
            'active'    => true
        ]);

        return $this;
    }
}
