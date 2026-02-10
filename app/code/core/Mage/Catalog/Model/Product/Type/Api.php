<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Catalog_Model_Product_Type_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve product type list
     *
     * @return array
     */
    public function items()
    {
        $result = [];

        foreach (Mage_Catalog_Model_Product_Type::getOptionArray() as $type => $label) {
            $result[] = [
                'type'  => $type,
                'label' => $label,
            ];
        }

        return $result;
    }
}
