<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogRule_Model_Action_Index_Refresh_Row extends Mage_CatalogRule_Model_Action_Index_Refresh
{
    /**
     * Product Id
     *
     * @var int
     */
    protected $_productId;

    /**
     * Constructor with parameters
     * Array of arguments with keys
     *  - 'connection' Maho\Db\Adapter\AdapterInterface
     *  - 'factory' Mage_Core_Model_Factory
     *  - 'resource' Mage_Core_Model_Resource_Db_Abstract
     *  - 'app' Mage_Core_Model_App
     *  - 'value' int|Mage_Catalog_Model_Product
     */
    public function __construct(array $args)
    {
        parent::__construct($args);
        $this->_productId = $args['value'] instanceof Mage_Catalog_Model_Product
            ? $args['value']->getId()
            : $args['value'];
    }

    /**
     * Do not recreate rule group website for row refresh
     * @param string $timestamp
     */
    #[\Override]
    protected function _prepareGroupWebsite($timestamp) {}

    /**
     * Prepare temporary data
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _prepareTemporarySelect(Mage_Core_Model_Website $website)
    {
        $select = parent::_prepareTemporarySelect($website);
        return $select->where('rp.product_id IN (?)', $this->_productId);
    }

    /**
     * Remove old index data
     */
    #[\Override]
    protected function _removeOldIndexData(Mage_Core_Model_Website $website)
    {
        $this->_connection->query(
            $this->_connection->deleteFromSelect(
                $this->_connection->select()
                    ->from($this->_resource->getTable('catalogrule/rule_product_price'))
                    ->where('product_id IN (?)', $this->_productId)
                    ->where('website_id = ?', $website->getId()),
                $this->_resource->getTable('catalogrule/rule_product_price'),
            ),
        );
    }

    /**
     * Return data for affected product
     *
     * @return int
     */
    #[\Override]
    protected function _getProduct()
    {
        return $this->_productId;
    }
}
