<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * Move the store config, the ACL resources and the cron jobs off the maho_ai
 * namespace. An install file, not an upgrade file: Maho_Ai declared no setup
 * resource before 1.0.1, so core_resource holds no ai_setup version to upgrade
 * from. Every statement is a no-op on a fresh install.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->update(
    $installer->getTable('core/config_data'),
    ['path' => new Maho\Db\Expr("REPLACE(path, 'maho_ai/', 'ai/')")],
    ['path LIKE ?' => 'maho_ai/%'],
);

$installer->getConnection()->update(
    $installer->getTable('admin/rule'),
    ['resource_id' => new Maho\Db\Expr("REPLACE(resource_id, '/maho_ai', '/ai')")],
    ['resource_id LIKE ?' => '%/maho_ai%'],
);

$installer->getConnection()->delete(
    $installer->getTable('cron/schedule'),
    ['job_code IN (?)' => ['maho_ai_process_queue', 'maho_ai_aggregate_usage', 'maho_ai_cleanup_old_tasks']],
);

$installer->endSetup();
