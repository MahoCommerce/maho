<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Mage_Catalog_Helper_Product_Configuration_Interface
{
    /**
     * Retrieves product options list
     *
     * @return array
     */
    public function getOptions(Mage_Catalog_Model_Product_Configuration_Item_Interface $item);
}
