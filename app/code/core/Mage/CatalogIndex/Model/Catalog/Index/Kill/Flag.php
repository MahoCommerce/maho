<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_CatalogIndex
 */
class Mage_CatalogIndex_Model_Catalog_Index_Kill_Flag extends Mage_Core_Model_Flag
{
    protected $_flagCode = 'catalogindex_kill';

    /**
     * @return bool
     */
    public function checkIsThisProcess()
    {
        return ($this->getFlagData() == getmypid());
    }
}
