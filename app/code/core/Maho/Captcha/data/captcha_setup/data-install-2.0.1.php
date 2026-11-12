<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Captcha
 */

declare(strict_types=1);

/**
 * The form that sends the confirmation link again now needs a CAPTCHA. A store that saved its own list of
 * selectors does not get the new default, so add the selector of that form to each saved list.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core/config_data');
$selector = '.customer-account-confirmation #form-validate';

$rows = $connection->fetchAll(
    $connection->select()
        ->from($table, ['config_id', 'value'])
        ->where('path = ?', Maho_Captcha_Helper_Data::XML_PATH_FRONTEND_SELECTORS),
);
foreach ($rows as $row) {
    $value = (string) $row['value'];
    if (trim($value) === '' || str_contains($value, $selector)) {
        continue;
    }
    $connection->update(
        $table,
        ['value' => rtrim($value) . "\n" . $selector],
        ['config_id = ?' => (int) $row['config_id']],
    );
}

$installer->endSetup();
