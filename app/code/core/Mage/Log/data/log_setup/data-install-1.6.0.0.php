<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;

$data = [
    [
        'type_id'     => 1,
        'type_code'   => 'hour',
        'period'      => 1,
        'period_type' => 'HOUR',
    ],

    [
        'type_id'     => 2,
        'type_code'   => 'day',
        'period'      => 1,
        'period_type' => 'DAY',
    ],
];

foreach ($data as $bind) {
    $installer->getConnection()->insertForce($installer->getTable('log/summary_type_table'), $bind);
}
