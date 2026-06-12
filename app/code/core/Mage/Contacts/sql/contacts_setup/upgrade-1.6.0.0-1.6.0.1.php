<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

/**
 * Move contact-form API settings out of Maho_ApiPlatform and into Mage_Contacts.
 *
 * The settings used to live at apiplatform/contact/* because the API Platform
 * module owned the contact endpoint. They're really contact-form settings that
 * happen to apply to API submissions, so they belong alongside the rest of the
 * contact-form config under contacts/api/*.
 *
 * Any rows already populated in core_config_data get rewritten in place; new
 * installs come up at contacts/api/* directly via etc/config.xml defaults.
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$connection = $installer->getConnection();
$table = $installer->getTable('core_config_data');

$connection->update(
    $table,
    ['path' => new Maho\Db\Expr("REPLACE(path, 'apiplatform/contact/', 'contacts/api/')")],
    $connection->prepareSqlCondition('path', ['like' => 'apiplatform/contact/%']),
);

$installer->endSetup();
