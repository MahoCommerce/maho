<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Helper_Product_Url_Rewrite extends Mage_Core_Helper_Abstract implements Mage_Catalog_Helper_Product_Url_Rewrite_Interface
{
    /**
     * Adapter instance
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_connection;

    /**
     * Resource instance
     *
     * @var Mage_Core_Model_Resource
     */
    protected $_resource;

    /**
     * Initialize resource and connection instances
     */
    public function __construct(array $args = [])
    {
        $this->_resource = Mage::getSingleton('core/resource');
        $this->_connection = empty($args['connection']) ? $this->_resource
            ->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE) : $args['connection'];
    }

    /**
     * Prepare and return select
     *
     * @param int $categoryId
     * @param int $storeId
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getTableSelect(array $productIds, $categoryId, $storeId)
    {
        return $this->_connection->select()
            ->from($this->_resource->getTableName('core/url_rewrite'), ['product_id', 'request_path'])
            ->where('store_id = ?', (int) $storeId)
            ->where('is_system = ?', 1)
            ->where('category_id = ? OR category_id IS NULL', (int) $categoryId)
            ->where('product_id IN(?)', $productIds)
            ->order('category_id ' . \Maho\Data\Collection::SORT_ORDER_DESC);
    }

    /**
     * Prepare url rewrite left join statement for given select instance and store_id parameter.
     *
     * @param int $storeId
     * @return Mage_Catalog_Helper_Product_Url_Rewrite_Interface
     */
    #[\Override]
    public function joinTableToSelect(\Maho\Db\Select $select, $storeId)
    {
        $select->joinLeft(
            ['url_rewrite' => $this->_resource->getTableName('core/url_rewrite')],
            'url_rewrite.product_id = main_table.entity_id AND url_rewrite.is_system = 1 AND ' .
                $this->_connection->quoteInto(
                    'url_rewrite.category_id IS NULL AND url_rewrite.store_id = ? AND ',
                    (int) $storeId,
                ) .
                $this->_connection->prepareSqlCondition('url_rewrite.id_path', ['like' => 'product/%']),
            ['request_path' => 'url_rewrite.request_path'],
        );
        return $this;
    }
}
