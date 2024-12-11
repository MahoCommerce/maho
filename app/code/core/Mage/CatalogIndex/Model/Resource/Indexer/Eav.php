<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Eav indexer resource model
 *
 * @category   Mage
 * @package    Mage_CatalogIndex
 */
class Mage_CatalogIndex_Model_Resource_Indexer_Eav extends Mage_CatalogIndex_Model_Resource_Indexer_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/eav', 'index_id');

        $this->_entityIdFieldName       = 'entity_id';
        $this->_attributeIdFieldName    = 'attribute_id';
        $this->_storeIdFieldName        = 'store_id';
    }
}
