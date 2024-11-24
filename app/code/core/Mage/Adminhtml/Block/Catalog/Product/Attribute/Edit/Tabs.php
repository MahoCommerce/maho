<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml product attribute edit page tabs
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit_Tabs extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('product_attribute_tabs');
    }
}
