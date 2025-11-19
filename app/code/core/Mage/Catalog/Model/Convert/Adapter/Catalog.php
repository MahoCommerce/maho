<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Convert_Adapter_Catalog extends Mage_Dataflow_Model_Convert_Adapter_Abstract
{
    /**
     * @return object
     */
    #[\Override]
    public function getResource()
    {
        if (!$this->_resource) {
            $this->_resource = Mage::getResourceSingleton('catalog_entity/convert');
        }
        return $this->_resource;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function load()
    {
        $res = $this->getResource();

        $this->setData([
            'Products' => $res->exportProducts(),
            'Categories' => $res->exportCategories(),
            'Image Gallery' => $res->exportImageGallery(),
            'Product Links' => $res->exportProductLinks(),
            'Products in Categories' => $res->exportProductsInCategories(),
            'Products in Stores' => $res->exportProductsInStores(),
            'Attributes' => $res->exportAttributes(),
            'Attribute Sets' => $res->exportAttributeSets(),
            'Attribute Options' => $res->exportAttributeOptions(),
        ]);

        return $this;
    }

    /**
     * @return $this
     */
    #[\Override]
    public function save()
    {
        /*
        $res = $this->getResource();

        foreach (array('Attributes', 'Attribute Sets', 'Attribute Options', 'Products', 'Categories', ''))

        $this->setData

        echo "<pre>".print_r($this->getData(),1)."</pre>";

        */
        return $this;
    }
}
