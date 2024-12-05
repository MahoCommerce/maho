<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Index
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Index_Model_Resource_Setup $installer */
$installer = $this;

$installer->getConnection()->addColumn(
    $this->getTable('index/process'),
    'mode',
    "enum('real_time','manual') DEFAULT 'real_time' NOT NULL after `ended_at`"
);
