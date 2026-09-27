<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function tmpTableLockIsHeld(): bool
{
    return Mage::getSingleton('core/lock')->isHeld(Mage_Index_Model_Resource_Abstract::TMP_TABLE_LOCK);
}

it('releases the lock when the callback throws', function () {
    expect(fn() => Mage_Index_Model_Resource_Abstract::runWithTmpTableLock(
        fn() => throw new RuntimeException('reindex failed'),
    ))->toThrow(RuntimeException::class, 'reindex failed');
    expect(tmpTableLockIsHeld())->toBeFalse();
});

it('holds the lock during the callback and releases it after', function () {
    $heldInside = null;
    Mage_Index_Model_Resource_Abstract::runWithTmpTableLock(function () use (&$heldInside): void {
        $heldInside = tmpTableLockIsHeld();
    });
    expect($heldInside)->toBeTrue();
    expect(tmpTableLockIsHeld())->toBeFalse();
});

it('keeps the lock of the outer call after a nested call returns', function () {
    $heldAfterNestedCall = null;
    Mage_Index_Model_Resource_Abstract::runWithTmpTableLock(function () use (&$heldAfterNestedCall): void {
        Mage_Index_Model_Resource_Abstract::runWithTmpTableLock(fn() => null);
        $heldAfterNestedCall = tmpTableLockIsHeld();
    });
    expect($heldAfterNestedCall)->toBeTrue();
    expect(tmpTableLockIsHeld())->toBeFalse();
});

it('does not take the lock when it is not needed', function () {
    $heldInside = null;
    Mage_Index_Model_Resource_Abstract::runWithTmpTableLock(function () use (&$heldInside): void {
        $heldInside = tmpTableLockIsHeld();
    }, needed: false);
    expect($heldInside)->toBeFalse();
});

it('marks only the price and EAV indexers as users of the _tmp tables', function () {
    expect(Mage::getModel('catalog/product_indexer_price')->usesTmpTables())->toBeTrue();
    expect(Mage::getModel('catalog/product_indexer_eav')->usesTmpTables())->toBeTrue();
    expect(Mage::getModel('catalog/indexer_url')->usesTmpTables())->toBeFalse();
    expect(Mage::getModel('catalogsearch/indexer_fulltext')->usesTmpTables())->toBeFalse();
});

it('needs the lock only when a matched process uses the _tmp tables', function () {
    $indexer = new class extends Mage_Index_Model_Indexer {
        public function needsLock(Closure $filter): bool
        {
            return $this->_anyProcessUsesTmpTables($filter);
        }
    };
    $matchesCode = fn(string $code): Closure => fn(Mage_Index_Model_Process $process): bool => $process->getIndexerCode() === $code;

    expect($indexer->needsLock($matchesCode('catalog_url')))->toBeFalse();
    expect($indexer->needsLock($matchesCode('catalogsearch_fulltext')))->toBeFalse();
    expect($indexer->needsLock($matchesCode('catalog_product_price')))->toBeTrue();
    expect($indexer->needsLock($matchesCode('catalog_product_attribute')))->toBeTrue();
    expect($indexer->needsLock(fn(Mage_Index_Model_Process $process): bool => false))->toBeFalse();
});
