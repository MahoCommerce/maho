<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

/** @var Mage_Core_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

$installer->getConnection()->insertMultiple(
    $installer->getTable('admin/permission_variable'),
    [
        ['variable_name' => 'trans_email/ident_support/name', 'is_allowed' => 1],
        ['variable_name' => 'trans_email/ident_support/email','is_allowed' =>  1],
        ['variable_name' => 'web/unsecure/base_url','is_allowed' =>  1],
        ['variable_name' => 'web/secure/base_url','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_general/name','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_general/email', 'is_allowed' => 1],
        ['variable_name' => 'trans_email/ident_sales/name','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_sales/email','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_custom1/name','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_custom1/email','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_custom2/name','is_allowed' =>  1],
        ['variable_name' => 'trans_email/ident_custom2/email','is_allowed' =>  1],
        ['variable_name' => 'general/store_information/name', 'is_allowed' => 1],
        ['variable_name' => 'general/store_information/phone','is_allowed'  => 1],
        ['variable_name' => 'general/store_information/address', 'is_allowed' => 1],
    ],
);

$installer->getConnection()->insertMultiple(
    $installer->getTable('admin/permission_block'),
    [
        ['block_name' => 'core/template', 'is_allowed' => 1],
        ['block_name' => 'catalog/product_new', 'is_allowed' => 1],
    ],
);

$installer->endSetup();
