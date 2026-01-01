<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogRule_Model_Action_Index_Refresh
{
    /**
     * Connection instance
     *
     * @var Maho\Db\Adapter\AdapterInterface
     */
    protected $_connection;

    /**
     * Core factory instance
     *
     * @var Mage_Core_Model_Factory
     */
    protected $_factory;

    /**
     * Resource instance
     *
     * @var Mage_Core_Model_Resource_Db_Abstract
     */
    protected $_resource;

    /**
     * Application instance
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    /**
     * Constructor with parameters
     * Array of arguments with keys
     *  - 'connection' Maho\Db\Adapter\AdapterInterface
     *  - 'factory' Mage_Core_Model_Factory
     *  - 'resource' Mage_Core_Model_Resource_Db_Abstract
     *  - 'app' Mage_Core_Model_App
     */
    public function __construct(array $args)
    {
        $this->_setConnection($args['connection']);
        $this->_setFactory($args['factory']);
        $this->_setResource($args['resource']);
        $this->_app = empty($args['app']) ? Mage::app() : $args['app'];
    }

    /**
     * Set connection
     */
    protected function _setConnection(Maho\Db\Adapter\AdapterInterface $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Set factory
     */
    protected function _setFactory(Mage_Core_Model_Factory $factory)
    {
        $this->_factory = $factory;
    }

    /**
     * Set resource
     */
    protected function _setResource(Mage_Core_Model_Resource_Db_Abstract $resource)
    {
        $this->_resource = $resource;
    }

    /**
     * Run reindex
     */
    public function execute()
    {
        $this->_app->dispatchEvent('catalogrule_before_apply', ['resource' => $this->_resource]);

        $coreDate = $this->_factory->getModel('core/date');
        if (!$coreDate instanceof Mage_Core_Model_Date) {
            throw new Mage_Core_Exception('Invalid core/date model');
        }
        $timestamp = $coreDate->gmtTimestamp();

        foreach ($this->_app->getWebsites(false) as $website) {
            if ($website->getDefaultStore()) {
                $this->_reindex($website, $timestamp);
            }
        }

        $this->_prepareGroupWebsite($timestamp);
        $this->_prepareAffectedProduct();
    }

    /**
     * Return temporary table name
     *
     * @return string
     */
    protected function _getTemporaryTable()
    {
        return $this->_resource->getTable('catalogrule/rule_product_price_tmp');
    }

    /**
     * Create temporary table
     */
    protected function _createTemporaryTable()
    {
        $this->_connection->dropTemporaryTable($this->_getTemporaryTable());
        $table = $this->_connection->newTable($this->_getTemporaryTable())
            ->addColumn(
                'grouped_id',
                Maho\Db\Ddl\Table::TYPE_VARCHAR,
                80,
                [],
                'Grouped ID',
            )
            ->addColumn(
                'product_id',
                Maho\Db\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                ],
                'Product ID',
            )
            ->addColumn(
                'customer_group_id',
                Maho\Db\Ddl\Table::TYPE_SMALLINT,
                5,
                [
                    'unsigned' => true,
                ],
                'Customer Group ID',
            )
            ->addColumn(
                'from_date',
                Maho\Db\Ddl\Table::TYPE_DATE,
                null,
                [],
                'From Date',
            )
            ->addColumn(
                'to_date',
                Maho\Db\Ddl\Table::TYPE_DATE,
                null,
                [],
                'To Date',
            )
            ->addColumn(
                'action_amount',
                Maho\Db\Ddl\Table::TYPE_DECIMAL,
                '12,4',
                [],
                'Action Amount',
            )
            ->addColumn(
                'action_operator',
                Maho\Db\Ddl\Table::TYPE_VARCHAR,
                10,
                [],
                'Action Operator',
            )
            ->addColumn(
                'action_stop',
                Maho\Db\Ddl\Table::TYPE_SMALLINT,
                6,
                [],
                'Action Stop',
            )
            ->addColumn(
                'sort_order',
                Maho\Db\Ddl\Table::TYPE_INTEGER,
                10,
                [
                    'unsigned' => true,
                ],
                'Sort Order',
            )
            ->addColumn(
                'price',
                Maho\Db\Ddl\Table::TYPE_DECIMAL,
                '12,4',
                [],
                'Product Price',
            )
            ->addColumn(
                'rule_product_id',
                Maho\Db\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                ],
                'Rule Product ID',
            )
            ->addColumn(
                'from_time',
                Maho\Db\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'nullable' => true,
                    'default'  => 0,
                ],
                'From Time',
            )
            ->addColumn(
                'to_time',
                Maho\Db\Ddl\Table::TYPE_INTEGER,
                null,
                [
                    'unsigned' => true,
                    'nullable' => true,
                    'default'  => 0,
                ],
                'To Time',
            )
            ->addIndex(
                $this->_connection->getIndexName($this->_getTemporaryTable(), 'grouped_id'),
                ['grouped_id'],
            )
            ->setComment('CatalogRule Price Temporary Table');
        $this->_connection->createTemporaryTable($table);
    }

    /**
     * Prepare temporary data
     *
     * @return Maho\Db\Select
     */
    protected function _prepareTemporarySelect(Mage_Core_Model_Website $website)
    {
        $catalogFlatHelper = $this->_factory->getHelper('catalog/product_flat');
        if (!$catalogFlatHelper instanceof Mage_Catalog_Helper_Product_Flat) {
            throw new Mage_Core_Exception('Invalid catalog product flat helper');
        }

        $eavConfig = $this->_factory->getSingleton('eav/config');
        if (!$eavConfig instanceof Mage_Eav_Model_Config) {
            throw new Mage_Core_Exception('Invalid eav config model');
        }
        $priceAttribute = $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'price');

        $select = $this->_connection->select()
            ->from(
                ['rp' => $this->_resource->getTable('catalogrule/rule_product')],
                [],
            )
            ->joinInner(
                ['r' => $this->_resource->getTable('catalogrule/rule')],
                'r.rule_id = rp.rule_id',
                [],
            )
            ->where('rp.website_id = ?', $website->getId())
            ->order(
                ['rp.product_id', 'rp.customer_group_id', 'rp.sort_order', 'rp.rule_product_id'],
            )
            ->joinLeft(
                [
                    'pg' => $this->_resource->getTable('catalog/product_attribute_group_price'),
                ],
                'pg.entity_id = rp.product_id AND pg.customer_group_id = rp.customer_group_id'
                    . ' AND pg.website_id = rp.website_id',
                [],
            )
            ->joinLeft(
                [
                    'pgd' => $this->_resource->getTable('catalog/product_attribute_group_price'),
                ],
                'pgd.entity_id = rp.product_id AND pgd.customer_group_id = rp.customer_group_id'
                    . ' AND pgd.website_id = 0',
                [],
            );

        $storeId = $website->getDefaultStore()->getId();

        if ($catalogFlatHelper->isEnabled() && $storeId && $catalogFlatHelper->isBuilt($storeId)) {
            $select->joinInner(
                ['p' => $this->_resource->getTable('catalog/product_flat') . '_' . $storeId],
                'p.entity_id = rp.product_id',
                [],
            );
            $priceColumn = $this->_connection->getIfNullSql(
                $this->_connection->getIfNullSql(
                    $this->_connection->getCheckSql(
                        'pg.is_percent = 1',
                        'p.price * (100 - pg.value)/100',
                        'pg.value',
                    ),
                    $this->_connection->getCheckSql(
                        'pgd.is_percent = 1',
                        'p.price * (100 - pgd.value)/100',
                        'pgd.value',
                    ),
                ),
                'p.price',
            );
        } else {
            $select->joinInner(
                [
                    'pd' => $this->_resource->getTable(['catalog/product', $priceAttribute->getBackendType()]),
                ],
                'pd.entity_id = rp.product_id AND pd.store_id = 0 AND pd.attribute_id = '
                        . $priceAttribute->getId(),
                [],
            )
                ->joinLeft(
                    [
                        'p' => $this->_resource->getTable(['catalog/product', $priceAttribute->getBackendType()]),
                    ],
                    'p.entity_id = rp.product_id AND p.store_id = ' . $storeId
                        . ' AND p.attribute_id = pd.attribute_id',
                    [],
                );
            $priceColumn = $this->_connection->getIfNullSql(
                $this->_connection->getIfNullSql(
                    $this->_connection->getCheckSql(
                        'pg.is_percent = 1',
                        $this->_connection->getIfNullSql(
                            'p.value',
                            'pd.value',
                        ) . ' * (100 - pg.value)/100',
                        'pg.value',
                    ),
                    $this->_connection->getCheckSql(
                        'pgd.is_percent = 1',
                        $this->_connection->getIfNullSql(
                            'p.value',
                            'pd.value',
                        ) . ' * (100 - pgd.value)/100',
                        'pgd.value',
                    ),
                ),
                $this->_connection->getIfNullSql(
                    'p.value',
                    'pd.value',
                ),
            );
        }

        $select->columns(
            [
                'grouped_id' => $this->_connection->getConcatSql(
                    ['rp.product_id', 'rp.customer_group_id'],
                    '-',
                ),
                'product_id'        => 'rp.product_id',
                'customer_group_id' => 'rp.customer_group_id',
                'from_date'         => 'r.from_date',
                'to_date'           => 'r.to_date',
                'action_amount'     => 'rp.action_amount',
                'action_operator'   => 'rp.action_operator',
                'action_stop'       => 'rp.action_stop',
                'sort_order'        => 'rp.sort_order',
                'price'             => $priceColumn,
                'rule_product_id'   => 'rp.rule_product_id',
                'from_time'         => 'rp.from_time',
                'to_time'           => 'rp.to_time',
            ],
        );

        return $select;
    }

    /**
     * Prepare price column
     *
     * @return Maho\Db\Expr
     */
    protected function _calculatePrice()
    {
        $toPercent = $this->_connection->quote('to_percent');
        $byPercent = $this->_connection->quote('by_percent');
        $toFixed   = $this->_connection->quote('to_fixed');
        $byFixed   = $this->_connection->quote('by_fixed');
        $nA        = $this->_connection->quote('N/A');

        return $this->_connection->getCaseSql(
            '',
            [
                $this->_connection->getIfNullSql(
                    new Maho\Db\Expr('@group_id'),
                    $nA,
                ) . ' != cppt.grouped_id' =>
                '@price := ' . $this->_connection->getCaseSql(
                    $this->_connection->quoteIdentifier('cppt.action_operator'),
                    [
                        $toPercent => new Maho\Db\Expr('cppt.price * cppt.action_amount/100'),
                        $byPercent => new Maho\Db\Expr('cppt.price * (1 - cppt.action_amount/100)'),
                        $toFixed   => $this->_connection->getCheckSql(
                            new Maho\Db\Expr('cppt.action_amount < cppt.price'),
                            new Maho\Db\Expr('cppt.action_amount'),
                            new Maho\Db\Expr('cppt.price'),
                        ),
                        $byFixed   => $this->_connection->getCheckSql(
                            new Maho\Db\Expr('0 > cppt.price - cppt.action_amount'),
                            new Maho\Db\Expr('0'),
                            new Maho\Db\Expr('cppt.price - cppt.action_amount'),
                        ),
                    ],
                ),
                $this->_connection->getIfNullSql(
                    new Maho\Db\Expr('@group_id'),
                    $nA,
                ) . ' = cppt.grouped_id AND '
                . $this->_connection->getIfNullSql(
                    new Maho\Db\Expr('@action_stop'),
                    new Maho\Db\Expr('0'),
                ) . ' = 0' => '@price := ' . $this->_connection->getCaseSql(
                    $this->_connection->quoteIdentifier('cppt.action_operator'),
                    [
                        $toPercent => new Maho\Db\Expr('@price * cppt.action_amount/100'),
                        $byPercent => new Maho\Db\Expr('@price * (1 - cppt.action_amount/100)'),
                        $toFixed   => $this->_connection->getCheckSql(
                            new Maho\Db\Expr('cppt.action_amount < @price'),
                            new Maho\Db\Expr('cppt.action_amount'),
                            new Maho\Db\Expr('@price'),
                        ),
                        $byFixed   => $this->_connection->getCheckSql(
                            new Maho\Db\Expr('0 > @price - cppt.action_amount'),
                            new Maho\Db\Expr('0'),
                            new Maho\Db\Expr('@price - cppt.action_amount'),
                        ),
                    ],
                ),
            ],
            '@price',
        );
    }

    /**
     * Prepare index select
     *
     * @param int|Maho\Db\Expr $time
     * @return Maho\Db\Select
     */
    protected function _prepareIndexSelect(Mage_Core_Model_Website $website, $time)
    {
        $nA = $this->_connection->quote('N/A');
        $this->_connection->query('SET @price := 0');
        $this->_connection->query('SET @group_id := NULL');
        $this->_connection->query('SET @action_stop := NULL');

        $indexSelect = $this->_connection->select()
            ->from(['cppt' => $this->_getTemporaryTable()], [])
            ->order(['cppt.grouped_id', 'cppt.sort_order', 'cppt.rule_product_id'])
            ->columns(
                [
                    'customer_group_id' => 'cppt.customer_group_id',
                    'product_id'        => 'cppt.product_id',
                    'rule_price'        => $this->_calculatePrice(),
                    'latest_start_date' => 'cppt.from_date',
                    'earliest_end_date' => 'cppt.to_date',
                    new Maho\Db\Expr(
                        $this->_connection->getCaseSql(
                            '',
                            [
                                $this->_connection->getIfNullSql(
                                    new Maho\Db\Expr('@group_id'),
                                    $nA,
                                ) . ' != cppt.grouped_id' => new Maho\Db\Expr('@action_stop := cppt.action_stop'),
                                $this->_connection->getIfNullSql(
                                    new Maho\Db\Expr('@group_id'),
                                    $nA,
                                ) . ' = cppt.grouped_id' => '@action_stop := '
                                    . $this->_connection->getIfNullSql(
                                        new Maho\Db\Expr('@action_stop'),
                                        new Maho\Db\Expr('0'),
                                    ) . ' + cppt.action_stop',
                            ],
                        ),
                    ),
                    new Maho\Db\Expr('@group_id := cppt.grouped_id'),
                    'from_time'         => 'cppt.from_time',
                    'to_time'           => 'cppt.to_time',
                ],
            );

        return $this->_connection->select()
            ->from($indexSelect, [])
            ->joinInner(
                [
                    'dates' => $this->_connection->select()->union(
                        [
                            new Maho\Db\Expr(
                                'SELECT ' . $this->_connection->getDateAddSql(
                                    $this->_connection->fromUnixtime($time),
                                    -1,
                                    Maho\Db\Adapter\AdapterInterface::INTERVAL_DAY,
                                ) . ' AS rule_date',
                            ),
                            new Maho\Db\Expr('SELECT ' . $this->_connection->fromUnixtime($time) . ' AS rule_date'),
                            new Maho\Db\Expr(
                                'SELECT ' . $this->_connection->getDateAddSql(
                                    $this->_connection->fromUnixtime($time),
                                    1,
                                    Maho\Db\Adapter\AdapterInterface::INTERVAL_DAY,
                                ) . ' AS rule_date',
                            ),
                        ],
                    ),
                ],
                '1=1',
                [],
            )
            ->columns(
                [
                    'rule_product_price_id' => new Maho\Db\Expr('NULL'),
                    'rule_date'             => 'dates.rule_date',
                    'customer_group_id'     => 'customer_group_id',
                    'product_id'            => 'product_id',
                    'rule_price'            => 'MIN(rule_price)',
                    'website_id'            => new Maho\Db\Expr($website->getId()),
                    'latest_start_date'     => 'latest_start_date',
                    'earliest_end_date'     => 'earliest_end_date',
                ],
            )
            ->where(new Maho\Db\Expr($this->_connection->getUnixTimestamp('dates.rule_date') . ' >= from_time'))
            ->where(
                $this->_connection->getCheckSql(
                    new Maho\Db\Expr('to_time = 0'),
                    new Maho\Db\Expr('1'),
                    new Maho\Db\Expr($this->_connection->getUnixTimestamp('dates.rule_date') . ' <= to_time'),
                ),
            )
            ->group(['customer_group_id', 'product_id', 'dates.rule_date', 'website_id']);
    }

    /**
     * Remove old index data
     */
    protected function _removeOldIndexData(Mage_Core_Model_Website $website)
    {
        $this->_connection->delete(
            $this->_resource->getTable('catalogrule/rule_product_price'),
            ['website_id = ?' => $website->getId()],
        );
    }

    /**
     * Fill Index Data
     *
     * @param int $time
     */
    protected function _fillIndexData(Mage_Core_Model_Website $website, $time)
    {
        $this->_connection->query(
            $this->_connection->insertFromSelect(
                $this->_prepareIndexSelect($website, $time),
                $this->_resource->getTable('catalogrule/rule_product_price'),
                [],
                Maho\Db\Adapter\AdapterInterface::INSERT_IGNORE,
            ),
        );
    }

    /**
     * Reindex catalog prices by website for timestamp
     *
     * @param int $timestamp
     */
    protected function _reindex(Mage_Core_Model_Website $website, $timestamp)
    {
        $this->_createTemporaryTable();
        $this->_connection->query(
            $this->_connection->insertFromSelect(
                $this->_prepareTemporarySelect($website),
                $this->_getTemporaryTable(),
            ),
        );
        $this->_removeOldIndexData($website);
        $this->_fillIndexData($website, $timestamp);
    }

    /**
     * Prepare data for group website relation
     * @param string $timestamp
     */
    protected function _prepareGroupWebsite($timestamp)
    {
        $this->_connection->delete($this->_resource->getTable('catalogrule/rule_group_website'), []);
        $select = $this->_connection->select()
            ->distinct()
            ->from(
                $this->_resource->getTable('catalogrule/rule_product'),
                ['rule_id', 'customer_group_id', 'website_id'],
            )
            ->where(new Maho\Db\Expr("{$timestamp} >= from_time"))
            ->where(
                $this->_connection->getCheckSql(
                    new Maho\Db\Expr('to_time = 0'),
                    new Maho\Db\Expr('1'),
                    new Maho\Db\Expr("{$timestamp} <= to_time"),
                ),
            );
        $query = $select->insertFromSelect($this->_resource->getTable('catalogrule/rule_group_website'));
        $this->_connection->query($query);
    }

    /**
     * Return data for affected product
     */
    protected function _getProduct()
    {
        return null;
    }

    /**
     * Prepare affected product
     */
    protected function _prepareAffectedProduct()
    {
        $modelCondition = $this->_factory->getModel('catalog/product_condition');

        $productCondition = $modelCondition->setTable($this->_resource->getTable('catalogrule/affected_product'))
            ->setPkFieldName('product_id');

        $this->_app->dispatchEvent(
            'catalogrule_after_apply',
            [
                'product' => $this->_getProduct(),
                'product_condition' => $productCondition,
            ],
        );

        $this->_connection->delete($this->_resource->getTable('catalogrule/affected_product'));
    }
}
