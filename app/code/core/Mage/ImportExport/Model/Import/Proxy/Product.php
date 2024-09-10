<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Import proxy product model
 *
 * @category   Mage
 * @package    Mage_ImportExport
 */
class Mage_ImportExport_Model_Import_Proxy_Product extends Mage_Catalog_Model_Product
{
    /**
     * DO NOT Initialize resources.
     */
    #[\Override]
    protected function _construct()
    {
    }

    /**
     * Retrieve object id
     *
     * @return int
     */
    #[\Override]
    public function getId()
    {
        return $this->_getData('id');
    }
}
