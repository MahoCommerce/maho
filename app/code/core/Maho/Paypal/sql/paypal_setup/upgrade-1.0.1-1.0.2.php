<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$conn = $installer->getConnection();

foreach (['paypal_api_debug', 'paypal_settlement_report_row', 'paypal_settlement_report', 'paypal_cert'] as $tbl) {
    if ($conn->isTableExists($tbl)) {
        $conn->dropTable($tbl);
    }
}

$installer->endSetup();
