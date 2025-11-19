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

class Mage_Catalog_Model_Product_Option_Value_Api_V2 extends Mage_Catalog_Model_Product_Option_Value_Api
{
    /**
     * Retrieve values from specified option
     *
     * @param string $optionId
     * @param int|string|null $store
     * @return array
     */
    #[\Override]
    public function items($optionId, $store = null)
    {
        $result = parent::items($optionId, $store);
        foreach ($result as $key => $optionValue) {
            $result[$key] = Mage::helper('api')->wsiArrayPacker($optionValue);
        }
        return $result;
    }

    /**
     * Retrieve specified option value info
     *
     * @param string $valueId
     * @param int|string|null $store
     * @return array
     */
    #[\Override]
    public function info($valueId, $store = null)
    {
        return Mage::helper('api')->wsiArrayPacker(
            parent::info($valueId, $store),
        );
    }

    /**
     * Add new values to select option
     *
     * @param string $optionId
     * @param array $data
     * @param int|string|null $store
     * @return bool
     */
    #[\Override]
    public function add($optionId, $data, $store = null)
    {
        Mage::helper('api')->toArray($data);
        return parent::add($optionId, $data, $store);
    }

    /**
     * Update value to select option
     *
     * @param string $valueId
     * @param array $data
     * @param int|string|null $store
     * @return bool
     */
    #[\Override]
    public function update($valueId, $data, $store = null)
    {
        Mage::helper('api')->toArray($data);
        return parent::update($valueId, $data, $store);
    }

    /**
     * Delete value from select option
     *
     * @param int $valueId
     * @return bool
     */
    #[\Override]
    public function remove($valueId)
    {
        return parent::remove($valueId);
    }
}
