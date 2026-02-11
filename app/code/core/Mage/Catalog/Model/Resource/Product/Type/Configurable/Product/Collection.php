<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Resource_Product_Type_Configurable_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    /**
     * Link table name
     *
     * @var string
     */
    protected $_linkTable;

    /**
     * Assign link table name
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_linkTable = $this->getTable('catalog/product_super_link');
    }

    /**
     * Init select
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->getSelect()->join(
            ['link_table' => $this->_linkTable],
            'link_table.product_id = e.entity_id',
            ['parent_id'],
        );

        return $this;
    }

    /**
     * Set Product filter to result
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function setProductFilter($product)
    {
        $this->getSelect()->where('link_table.parent_id = ?', (int) $product->getId());
        return $this;
    }

    /**
     * Retrieve is flat enabled flag
     * Return always false in admin context
     *
     * @return bool
     */
    #[\Override]
    public function isEnabledFlat()
    {
        return false;
    }
}
