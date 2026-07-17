<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

/**
 * Data migration for the salesrule/website and salesrule/customer_group
 * relation tables introduced in 1.6.0.3. Original upgrade also created the
 * tables and dropped salesrule.website_ids / salesrule.customer_group_ids;
 * those structural changes now live in sql/schema.php. This file keeps the
 * data backfill so existing 1.6.0.2 installs migrate cleanly. On fresh
 * installs the source columns no longer exist and the migration is skipped.
 */

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer  = $this;
$connection = $installer->getConnection();

$rulesTable          = $installer->getTable('salesrule/rule');
$websitesTable       = $installer->getTable('core/website');
$customerGroupsTable = $installer->getTable('customer/customer_group');
$rulesWebsitesTable  = $installer->getTable('salesrule/website');
$rulesCustomerGroupsTable = $installer->getTable('salesrule/customer_group');

if ($connection->tableColumnExists($rulesTable, 'website_ids')) {
    $select = $connection->select()
        ->from(['sr' => $rulesTable], ['sr.rule_id', 'cw.website_id'])
        ->join(
            ['cw' => $websitesTable],
            $connection->prepareSqlCondition(
                'sr.website_ids',
                ['finset' =>  new Maho\Db\Expr('cw.website_id')],
            ),
            [],
        );
    $query = $select->insertFromSelect($rulesWebsitesTable, ['rule_id', 'website_id']);
    $connection->query($query);
}

if ($connection->tableColumnExists($rulesTable, 'customer_group_ids')) {
    $select = $connection->select()
        ->from(['sr' => $rulesTable], ['sr.rule_id', 'cg.customer_group_id'])
        ->join(
            ['cg' => $customerGroupsTable],
            $connection->prepareSqlCondition(
                'sr.customer_group_ids',
                ['finset' =>  new Maho\Db\Expr('cg.customer_group_id')],
            ),
            [],
        );
    $query = $select->insertFromSelect($rulesCustomerGroupsTable, ['rule_id', 'customer_group_id']);
    $connection->query($query);
}
