<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

class Mage_CatalogRule_Model_Action_Index_Refresh
{
    protected const INDEX_INSERT_BATCH = 1000;

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

        $timestamp = time();

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
            // The website sells at its own row or at the default value converted, never at the raw
            // default; _reindex() already skipped a website without a rate
            $rate = (float) $this->_websitePriceRate($website);
            $converted = fn(string $column): string => (string) $this->_connection->getRoundSql("{$column} * {$rate}", 4);
            $price = $this->_connection->getCheckSql('p.value_id IS NOT NULL', 'p.value', $converted('pd.value'));
            $priceColumn = $this->_connection->getIfNullSql(
                $this->_connection->getIfNullSql(
                    $this->_connection->getCheckSql(
                        'pg.is_percent = 1',
                        "({$price}) * (100 - pg.value)/100",
                        'pg.value',
                    ),
                    $this->_connection->getCheckSql(
                        'pgd.is_percent = 1',
                        "({$price}) * (100 - pgd.value)/100",
                        $converted('pgd.value'),
                    ),
                ),
                $price,
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
     * Rules chain: within one product/customer group, each rule applies to the price the previous
     * one produced, until a rule with action_stop breaks the chain. That is a running calculation
     * over ordered rows, which used to be done with MySQL user variables (SET @price := ...) and
     * so only ever worked on MySQL. It is done here instead, once per row, in the same order.
     */
    protected function _fillIndexData(Mage_Core_Model_Website $website, int $time): void
    {
        $table = $this->_resource->getTable('catalogrule/rule_product_price');
        $websiteId = (int) $website->getId();
        $dates = $this->_getRuleDates($time);

        $statement = $this->_connection->query(
            $this->_connection->select()
                ->from(['cppt' => $this->_getTemporaryTable()])
                ->order(['cppt.grouped_id', 'cppt.sort_order', 'cppt.rule_product_id']),
        );

        $groupedId = null;
        $price = 0.0;
        $actionStop = 0;
        $group = [];
        $pending = [];

        $this->_connection->beginTransaction();
        try {
            while ($row = $statement->fetch()) {
                if ((string) $row['grouped_id'] !== $groupedId) {
                    $pending = array_merge($pending, array_values($group));
                    $group = [];
                    $groupedId = (string) $row['grouped_id'];
                    $price = $this->_applyRuleAction((float) $row['price'], $row);
                    $actionStop = (int) $row['action_stop'];
                } else {
                    if ($actionStop === 0) {
                        $price = $this->_applyRuleAction($price, $row);
                    }
                    $actionStop += (int) $row['action_stop'];
                }

                foreach ($dates as $date => $timestamp) {
                    if ($this->_isRuleActiveAt($row, $timestamp)) {
                        $this->_collectRulePrice($group, $date, $row, $price, $websiteId);
                    }
                }

                if (count($pending) >= self::INDEX_INSERT_BATCH) {
                    $this->_connection->insertMultiple($table, $pending);
                    $pending = [];
                }
            }

            $pending = array_merge($pending, array_values($group));
            if ($pending) {
                $this->_connection->insertMultiple($table, $pending);
            }

            $this->_connection->commit();
        } catch (Throwable $e) {
            $this->_connection->rollBack();
            throw $e;
        }
    }

    /**
     * Yesterday, today and tomorrow relative to the reindex time, as 'Y-m-d' => timestamp.
     *
     * Three days are indexed so a store stays correct across the timezone spread of its websites.
     *
     * @return array<string, int>
     */
    protected function _getRuleDates(int $time): array
    {
        $locale = $this->_app->getLocale();

        $dates = [];
        foreach ([$time - 86400, $time, $time + 86400] as $timestamp) {
            $dates[$locale->formatDateForDb($timestamp, withTime: false)] = $timestamp;
        }
        return $dates;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function _isRuleActiveAt(array $row, int $timestamp): bool
    {
        $toTime = (int) $row['to_time'];
        return $timestamp >= (int) $row['from_time'] && ($toTime === 0 || $timestamp <= $toTime);
    }

    /**
     * Apply one rule action to the price the chain has reached so far.
     *
     * @param array<string, mixed> $row
     */
    protected function _applyRuleAction(float $price, array $row): float
    {
        $amount = (float) $row['action_amount'];

        return match ($row['action_operator']) {
            'to_percent' => $price * $amount / 100,
            'by_percent' => $price * (1 - $amount / 100),
            'to_fixed'   => min($amount, $price),
            'by_fixed'   => max(0.0, $price - $amount),
            default      => $price,
        };
    }

    /**
     * Keep the cheapest price per date, with the widest start and narrowest end the rules allow.
     *
     * @param array<string, array<string, mixed>> $group
     * @param array<string, mixed> $row
     */
    protected function _collectRulePrice(array &$group, string $date, array $row, float $price, int $websiteId): void
    {
        if (!isset($group[$date])) {
            $group[$date] = [
                'rule_date'         => $date,
                'customer_group_id' => (int) $row['customer_group_id'],
                'product_id'        => (int) $row['product_id'],
                'rule_price'        => $price,
                'website_id'        => $websiteId,
                'latest_start_date' => $row['from_date'] ?: null,
                'earliest_end_date' => $row['to_date'] ?: null,
            ];
            return;
        }

        $bucket = &$group[$date];
        $bucket['rule_price'] = min($bucket['rule_price'], $price);

        if ($row['from_date']
            && ($bucket['latest_start_date'] === null || $row['from_date'] > $bucket['latest_start_date'])
        ) {
            $bucket['latest_start_date'] = $row['from_date'];
        }
        if ($row['to_date']
            && ($bucket['earliest_end_date'] === null || $row['to_date'] < $bucket['earliest_end_date'])
        ) {
            $bucket['earliest_end_date'] = $row['to_date'];
        }
    }

    /**
     * Reindex catalog prices by website for timestamp
     *
     * @param int $timestamp
     */
    protected function _reindex(Mage_Core_Model_Website $website, $timestamp)
    {
        // No rate, no price to discount: the website is not selling, so it keeps no rule prices either
        if ($this->_websitePriceRate($website) === null) {
            $this->_removeOldIndexData($website);
            return;
        }

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

    protected function _websitePriceRate(Mage_Core_Model_Website $website): ?float
    {
        $store = $website->getDefaultStore();
        $helper = $this->_factory->getHelper('catalog');
        if (!$store || !$helper instanceof Mage_Catalog_Helper_Data) {
            return null;
        }

        return $helper->getWebsitePriceRate($store);
    }

    /**
     * Prepare data for group website relation
     * @param int $timestamp
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
            ->where(new Maho\Db\Expr(((int) $timestamp) . ' >= from_time'))
            // A CASE returning either 1 or a comparison mixes integer and boolean, which PostgreSQL
            // rejects outright; the open-ended window is a plain disjunction anyway.
            ->where(new Maho\Db\Expr('to_time = 0 OR ' . ((int) $timestamp) . ' <= to_time'));
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
