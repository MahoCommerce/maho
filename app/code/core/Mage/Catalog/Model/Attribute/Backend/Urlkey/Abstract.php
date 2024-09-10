<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product url key attribute backend
 *
 * @category   Mage
 * @package    Mage_Catalog
 */
abstract class Mage_Catalog_Model_Attribute_Backend_Urlkey_Abstract extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Format url key attribute before save, also use product name as url key if it empty
     *
     * @param Varien_Object $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $attributeName = $this->getAttribute()->getName();

        $urlKey = $object->getData($attributeName);
        if ($urlKey === false) {
            return $this;
        }
        if ($urlKey == '') {
            $urlKey = $object->getName();
        }

        $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $object->getStoreId());
        $object->setData($attributeName, $object->formatUrlKey($urlKey, $locale));

        return $this;
    }

    /**
     * Executes after url attribute save.
     *
     * @param Varien_Object $object
     *
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        /**
         * This logic moved to Mage_Catalog_Model_Indexer_Url
         */
        return $this;
    }
}
