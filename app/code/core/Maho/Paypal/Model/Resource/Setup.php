<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Paypal_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
    public function __construct($resourceName)
    {
        parent::__construct($resourceName);
        if ($resourceName === 'paypal_setup') {
            $this->_migrateLegacyResourceRow();
        }
    }

    /**
     * Reconcile core_resource entries from previous incarnations of this module.
     *
     * - Pre-#877 Maho_Paypal registered its setup resource as `maho_paypal_setup`. The schema
     *   for the new `paypal_setup` matches what `maho_paypal_setup` produced, so simply renaming
     *   the row preserves history without re-running install scripts.
     * - The deleted Mage_Paypal module also owned the `paypal_setup` resource at version 1.6.x.
     *   Drop its tables and reset the row so the new install pipeline runs cleanly.
     */
    private function _migrateLegacyResourceRow(): void
    {
        $conn = $this->getConnection();
        if (!$conn) {
            return;
        }
        $coreResource = Mage::getSingleton('core/resource')->getTableName('core_resource');
        if (!$conn->isTableExists($coreResource)) {
            return;
        }

        // Legacy Mage_Paypal: drop its tables and clear its core_resource row.
        $paypalRow = $conn->fetchRow(
            $conn->select()->from($coreResource)->where('code = ?', 'paypal_setup'),
        );
        if ($paypalRow && version_compare((string) ($paypalRow['version'] ?? '0'), '1.6.0.0', '>=')) {
            foreach (['paypal_api_debug', 'paypal_settlement_report_row', 'paypal_settlement_report', 'paypal_cert'] as $tbl) {
                if ($conn->isTableExists($tbl)) {
                    $conn->dropTable($tbl);
                }
            }
            $conn->delete($coreResource, ['code = ?' => 'paypal_setup']);
        }

        // Pre-#877 Maho_Paypal: rename maho_paypal_setup to paypal_setup.
        $mahoRow = $conn->fetchRow(
            $conn->select()->from($coreResource)->where('code = ?', 'maho_paypal_setup'),
        );
        if ($mahoRow) {
            $conn->delete($coreResource, ['code = ?' => 'paypal_setup']);
            $conn->update($coreResource, ['code' => 'paypal_setup'], ['code = ?' => 'maho_paypal_setup']);
        }
    }
}
