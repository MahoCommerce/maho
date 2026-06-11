<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer           = $this;
$connection          = $installer->getConnection();

$rulesTable          = $installer->getTable('catalogrule/rule');
$websitesTable       = $installer->getTable('core/website');
$customerGroupsTable = $installer->getTable('customer/customer_group');
$rulesWebsitesTable  = $installer->getTable('catalogrule/website');
$rulesCustomerGroupsTable  = $installer->getTable('catalogrule/customer_group');

$installer->startSetup();

/**
 * Fill out relation table 'catalogrule/website' with website Ids previously
 * stored in catalogrule.website_ids (column removed by the declarative schema).
 */
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

/**
 * Fill out relation table 'catalogrule/customer_group' with customer group Ids
 * previously stored in catalogrule.customer_group_ids (column removed by the
 * declarative schema).
 */
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

$installer->endSetup();
