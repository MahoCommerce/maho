<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Attribute_Source_Inputtype extends Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype
{
    #[\Override]
    public function toOptionArray(): array
    {
        $inputTypes = [
            [
                'value' => 'price',
                'label' => Mage::helper('catalog')->__('Price'),
            ],
            [
                'value' => 'media_image',
                'label' => Mage::helper('catalog')->__('Media Image'),
            ],
        ];

        $response = new \Maho\DataObject();
        $response->setTypes([]);
        Mage::dispatchEvent('adminhtml_product_attribute_types', ['response' => $response]);
        $_disabledTypes = [];
        $_hiddenFields = [];
        foreach ($response->getTypes() as $type) {
            $inputTypes[] = $type;
            if (isset($type['hide_fields'])) {
                $_hiddenFields[$type['value']] = $type['hide_fields'];
            }
            if (isset($type['disabled_types'])) {
                $_disabledTypes[$type['value']] = $type['disabled_types'];
            }
        }

        if (Mage::registry('attribute_type_hidden_fields') === null) {
            Mage::register('attribute_type_hidden_fields', $_hiddenFields);
        }
        if (Mage::registry('attribute_type_disabled_types') === null) {
            Mage::register('attribute_type_disabled_types', $_disabledTypes);
        }

        return array_merge(parent::toOptionArray(), $inputTypes);
    }
}
