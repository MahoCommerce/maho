<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Sales_Model_Entity_Setup $installer */
$installer = $this;

$installer->installEntities();
$installer->startSetup();
$installer->run("
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity_varchar')};
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity_int')};
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity_decimal')};
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity_datetime')};
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity_text')};
    DROP TABLE IF EXISTS {$this->getTable('sales_invoice_entity')};
");
$installer->endSetup();
