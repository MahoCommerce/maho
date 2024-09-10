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

/** @var Varien_Db_Adapter_Pdo_Mysql $conn */
$conn = $installer->getConnection();

$conn->addColumn($installer->getTable('sales_quote'), 'customer_dob', 'datetime after customer_suffix');
$installer->addAttribute('quote', 'customer_dob', ['type' => 'static', 'backend' => 'eav/entity_attribute_backend_datetime']);

$installer->addAttribute('order', 'customer_dob', ['type' => 'datetime', 'backend' => 'eav/entity_attribute_backend_datetime']);
