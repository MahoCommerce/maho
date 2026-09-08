<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */

// Catalog 2.0.1 converts a website price when it is read, and no longer at save time. The rows
// in catalogrule_product_price came from the old converted prices, so they are wrong now. Mark
// the rules dirty, and the admin asks the merchant to apply them again.
Mage::getModel('catalogrule/flag')->loadSelf()->setState(1)->save();
