<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Oauth_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$consumerTable = $installer->getTable('oauth/consumer');
$roleTable = $installer->getTable('api/role');

// Add api_role_id column (nullable FK to api_role)
if (!$connection->tableColumnExists($consumerTable, 'api_role_id')) {
    $connection->addColumn($consumerTable, 'api_role_id', [
        'type' => Maho\Db\Ddl\Table::TYPE_INTEGER,
        'unsigned' => true,
        'nullable' => true,
        'comment' => 'API Role ID for permission management',
    ]);

    $connection->addForeignKey(
        $installer->getFkName('oauth/consumer', 'api_role_id', 'api/role', 'role_id'),
        $consumerTable,
        'api_role_id',
        $roleTable,
        'role_id',
        Maho\Db\Ddl\Table::ACTION_SET_NULL,
        Maho\Db\Ddl\Table::ACTION_CASCADE,
    );
}

// Migrate existing consumers with admin_permissions to api_role/api_rule
$consumers = $connection->fetchAll(
    $connection->select()
        ->from($consumerTable, ['entity_id', 'name', 'admin_permissions'])
        ->where('admin_permissions IS NOT NULL')
        ->where("admin_permissions != ''"),
);

$ruleTable = $installer->getTable('api/rule');

foreach ($consumers as $consumer) {
    $permissions = json_decode($consumer['admin_permissions'], true);
    if (empty($permissions)) {
        continue;
    }

    // Create a role for this consumer
    $roleName = 'OAuth: ' . $consumer['name'];
    $connection->insert($roleTable, [
        'parent_id' => 0,
        'tree_level' => 1,
        'sort_order' => 0,
        'role_type' => 'G',
        'user_id' => 0,
        'role_name' => $roleName,
    ]);
    $roleId = (int) $connection->lastInsertId($roleTable);

    // Map old permission keys to new resource IDs
    $resourceMap = [
        'cms_pages' => 'admin/cms-pages',
        'cms_blocks' => 'admin/cms-blocks',
        'blog_posts' => 'admin/blog-posts',
        'media' => 'admin/media',
    ];

    foreach ($permissions as $resource => $level) {
        if (empty($level) || $level === 'none') {
            continue;
        }

        $newResourceId = $resourceMap[$resource] ?? $resource;

        // "write" or legacy "1" = read + write + delete
        if ($level === '1' || $level === 'write') {
            foreach (['read', 'write', 'delete'] as $op) {
                $connection->insert($ruleTable, [
                    'role_id' => $roleId,
                    'resource_id' => $newResourceId . '/' . $op,
                    'api_privileges' => null,
                    'assert_id' => 0,
                    'role_type' => 'G',
                    'api_permission' => 'allow',
                ]);
            }
        } elseif ($level === 'read') {
            $connection->insert($ruleTable, [
                'role_id' => $roleId,
                'resource_id' => $newResourceId . '/read',
                'api_privileges' => null,
                'assert_id' => 0,
                'role_type' => 'G',
                'api_permission' => 'allow',
            ]);
        }
    }

    // Assign role to consumer
    $connection->update(
        $consumerTable,
        ['api_role_id' => $roleId],
        ['entity_id = ?' => $consumer['entity_id']],
    );
}

// Drop admin_permissions column (migrated to api_role/api_rule)
if ($connection->tableColumnExists($consumerTable, 'admin_permissions')) {
    $connection->dropColumn($consumerTable, 'admin_permissions');
}

$installer->endSetup();
