<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Product_Attribute_Backend_Urlkey extends Mage_Catalog_Model_Product_Attribute_Backend_Urlkey
{
    /**
     * No need to validate url_key during import
     *
     * @param Mage_Catalog_Model_Product $object
     * @return $this
     */
    protected function _validateUrlKey($object)
    {
        return $this;
    }
}
