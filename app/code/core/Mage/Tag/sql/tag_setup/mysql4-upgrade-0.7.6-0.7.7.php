<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Tag
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2022 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/** @var Mage_Core_Model_Resource_Setup $installer */
$installer = $this;

$installer->getConnection()
    ->addKey(
        $this->getTable('tag/relation'),
        'UNQ_TAG_CUSTOMER_PRODUCT_STORE',
        ['tag_id', 'customer_id', 'product_id', 'store_id'],
        'unique'
    );
