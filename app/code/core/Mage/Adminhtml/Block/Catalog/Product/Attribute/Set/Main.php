<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml Catalog Product Attribute Set Edit Form
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Main extends Mage_Eav_Block_Adminhtml_Attribute_Set_Edit
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplateIfExists('catalog/product/attribute/set/main.phtml');
    }

    #[\Override]
    public function getGroupTreeJson()
    {
        $configurable = Mage::getResourceModel('catalog/product_type_configurable_attribute')
            ->getUsedAttributes($this->_getSetId());

        $items = $this->getGroupTree();

        foreach ($items as &$item) {
            foreach ($item['children'] as &$child) {
                $child['is_configurable'] = (int) in_array($child['id'], $configurable);
            }
        }

        return Mage::helper('core')->jsonEncode($items);
    }
}
