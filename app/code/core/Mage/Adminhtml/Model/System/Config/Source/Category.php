<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Category
{
    public function toOptionArray($addEmpty = true)
    {
        $tree = Mage::getResourceModel('catalog/category_tree');

        $collection = Mage::getResourceModel('catalog/category_collection');

        $collection->addAttributeToSelect('name')
            ->addRootLevelFilter()
            ->load();

        $options = [];

        if ($addEmpty) {
            $options[] = [
                'label' => Mage::helper('adminhtml')->__('-- Please Select a Category --'),
                'value' => '',
            ];
        }
        foreach ($collection as $category) {
            $options[] = [
                'label' => $category->getName(),
                'value' => $category->getId(),
            ];
        }

        return $options;
    }
}
