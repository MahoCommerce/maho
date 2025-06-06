<?php

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Tax_Model_Resource_Setup $this */

/**
 * Add new field to 'tax/tax_calculation_rule'
 */
$this->getConnection()
    ->addColumn(
        $this->getTable('tax/tax_calculation_rule'),
        'calculate_subtotal',
        [
            'TYPE' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'NULLABLE' => false,
            'COMMENT' => 'Calculate off subtotal option',
        ],
    );
