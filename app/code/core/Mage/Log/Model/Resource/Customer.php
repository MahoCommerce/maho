<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Log_Model_Resource_Customer extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Visitor data table name
     *
     * @var string
     */
    protected $_visitorTable;

    /**
     * Visitor info data table
     *
     * @var string
     */
    protected $_visitorInfoTable;

    /**
     * Customer data table
     *
     * @var string
     */
    protected $_customerTable;

    /**
     * Url info data table
     *
     * @var string
     */
    protected $_urlInfoTable;

    /**
     * Log URL data table name.
     *
     * @var string
     */
    protected $_urlTable;

    /**
     * Log quote data table name.
     *
     * @var string
     */
    protected $_quoteTable;

    #[\Override]
    protected function _construct()
    {
        $this->_init('log/customer', 'log_id');

        $this->_visitorTable        = $this->getTable('log/visitor');
        $this->_visitorInfoTable    = $this->getTable('log/visitor_info');
        $this->_urlTable            = $this->getTable('log/url_table');
        $this->_urlInfoTable        = $this->getTable('log/url_info_table');
        $this->_customerTable       = $this->getTable('log/customer');
        $this->_quoteTable          = $this->getTable('log/quote_table');
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Log_Model_Customer $object
     * @return Varien_Db_Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if ($field == 'customer_id') {
            // load additional data by last login
            $table  = $this->getMainTable();
            $select
                ->joinInner(
                    ['lvt' => $this->_visitorTable],
                    "lvt.visitor_id = {$table}.visitor_id",
                    ['last_visit_at'],
                )
                ->joinInner(
                    ['lvit' => $this->_visitorInfoTable],
                    'lvt.visitor_id = lvit.visitor_id',
                    ['http_referer', 'remote_addr'],
                )
                ->joinInner(
                    ['luit' => $this->_urlInfoTable],
                    'luit.url_id = lvt.last_url_id',
                    ['url'],
                )
                ->order("{$table}.login_at DESC")
                ->limit(1);
        }
        return $select;
    }
}
