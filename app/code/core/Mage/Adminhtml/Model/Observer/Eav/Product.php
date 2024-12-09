<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Catalog Product EAV Observer
 */
class Mage_Adminhtml_Model_Observer_Eav_Product
{
    /**
     * Ensure Mage::registry('entity_type') is set if user has overridden the admin product attribute or set controller
     */
    public function setEntityTypeRegistryIfNotExist(Varien_Event_Observer $observer): self
    {
        if (!Mage::registry('entity_type')) {
            Mage::register('entity_type', Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY));
        }
        return $this;
    }
}
