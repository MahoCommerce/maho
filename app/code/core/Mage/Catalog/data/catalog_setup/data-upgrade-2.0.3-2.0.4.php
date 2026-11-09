<?php

/**
 * Marks the is_dynamic category attribute as a system attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;

// The dynamic categories feature owns this attribute, so System > Attributes must not list or delete it
$installer->updateAttribute(Mage_Catalog_Model_Category::ENTITY, 'is_dynamic', 'is_user_defined', 0);
