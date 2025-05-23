<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Catalog_Model_Resource_Setup $installer */
$installer = $this;

$installer->startSetup();

// Remove dynamic_last_update attribute since it's no longer needed
// Dynamic categories are now processed automatically by the indexer
$installer->removeAttribute('catalog_category', 'dynamic_last_update');

$installer->endSetup();