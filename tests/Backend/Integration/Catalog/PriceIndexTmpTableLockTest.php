<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('holds the tmp table lock while a full price reindex writes the main table', function () {
    $resource = new class extends Mage_Catalog_Model_Resource_Product_Indexer_Price {
        public ?bool $heldInSyncData = null;

        #[\Override]
        public function syncData()
        {
            $this->heldInSyncData = Mage::getSingleton('core/lock')->isHeld(Mage_Index_Model_Resource_Abstract::TMP_TABLE_LOCK);
            return parent::syncData();
        }
    };

    $resource->reindexAll();

    expect($resource->heldInSyncData)->toBeTrue();
    expect(Mage::getSingleton('core/lock')->isHeld(Mage_Index_Model_Resource_Abstract::TMP_TABLE_LOCK))->toBeFalse();
});
