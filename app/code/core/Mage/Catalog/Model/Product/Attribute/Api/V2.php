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

class Mage_Catalog_Model_Product_Attribute_Api_V2 extends Mage_Catalog_Model_Product_Attribute_Api
{
    /**
     * Create new product attribute
     *
     * @param array $data input data
     * @return int
     */
    #[\Override]
    public function create($data)
    {
        $helper = Mage::helper('api');
        $helper->v2AssociativeArrayUnpacker($data);
        Mage::helper('api')->toArray($data);
        return parent::create($data);
    }

    /**
     * Update product attribute
     *
     * @param string|int $attribute attribute code or ID
     * @param array $data
     * @return bool
     */
    #[\Override]
    public function update($attribute, $data)
    {
        $helper = Mage::helper('api');
        $helper->v2AssociativeArrayUnpacker($data);
        Mage::helper('api')->toArray($data);
        return parent::update($attribute, $data);
    }

    /**
     * Add option to select or multiselect attribute
     *
     * @param  int|string $attribute attribute ID or code
     * @param  array $data
     * @return bool
     */
    #[\Override]
    public function addOption($attribute, $data)
    {
        Mage::helper('api')->toArray($data);
        return parent::addOption($attribute, $data);
    }

    /**
     * Get full information about attribute with list of options
     *
     * @param int|string $attribute attribute ID or code
     * @return array
     */
    #[\Override]
    public function info($attribute)
    {
        $result = parent::info($attribute);
        if (!empty($result['additional_fields'])) {
            $keys = array_keys($result['additional_fields']);
            foreach ($keys as $key) {
                $result['additional_fields'][] = [
                    'key' => $key,
                    'value' => $result['additional_fields'][$key],
                ];
                unset($result['additional_fields'][$key]);
            }
        }
        return $result;
    }
}
