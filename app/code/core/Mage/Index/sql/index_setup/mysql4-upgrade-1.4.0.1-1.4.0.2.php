<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Index_Model_Resource_Setup $installer */
$installer = $this;

$installer->getConnection()->changeColumn(
    $this->getTable('index/process'),
    'status',
    'status',
    "enum('pending','working','require_reindex') DEFAULT 'pending' NOT NULL"
);
