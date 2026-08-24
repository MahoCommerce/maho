<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/** @var Mage_Catalog_Model_Resource_Setup $this */

// Website prices are derived from the currency rate on read from this version on, and the
// save-time conversion that wrote store-scoped rows is gone. Whatever rows exist now are the
// merchant's own, including the converted copies an earlier version wrote, so the merchant is
// told once, here, at the boundary where that changed.
Mage::helper('catalog')->noticeWebsitePriceRows();

// The price index was built under the old rule and is stale from now on. Mage_CatalogRule marks
// its own rule prices, in data-upgrade-2.0.0-2.0.1.php of that module.
$indexProcess = Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price');
if ($indexProcess) {
    $indexProcess->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);
}
