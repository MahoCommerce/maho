<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The preview of a content version lists the fields that a restore of the version changes.
 */

beforeEach(function (): void {
    $this->block = Mage::getModel('cms/block')
        ->setTitle('Changes test')
        ->setIdentifier('changes_test_' . uniqid())
        ->setStores([0])
        ->setIsActive(true)
        ->setContent("<p>One</p>\n<p>Two</p>")
        ->save();
});

afterEach(function (): void {
    Mage::getResourceModel('contentversion/version_collection')
        ->addFieldToFilter('entity_type', 'cms_block')
        ->addFieldToFilter('entity_id', (int) $this->block->getId())
        ->walk('delete');
    $this->block->delete();
});

it('lists each field that a restore changes, with the current value and the value of the version', function (): void {
    $blockId = (int) $this->block->getId();
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->update($this->block->getResource()->getMainTable(), ['update_time' => '2020-01-01 00:00:00'], ['block_id = ?' => $blockId]);
    Mage::getModel('cms/block')->load($blockId)
        ->setTitle('Changes test, edited')
        ->setContent("<p>One</p>\n<p>Three</p>")
        ->save();

    $version = Mage::getResourceModel('contentversion/version_collection')
        ->addFieldToFilter('entity_type', 'cms_block')
        ->addFieldToFilter('entity_id', $blockId)
        ->getFirstItem();

    $changes = Mage::helper('contentversion')->getRestoreChanges($version);

    expect($changes)->toHaveKeys(['title', 'content'])
        ->and($changes['title'])->toBe(['current' => 'Changes test, edited', 'version' => 'Changes test'])
        ->and($changes['content'])->toBe(['current' => "<p>One</p>\n<p>Three</p>", 'version' => "<p>One</p>\n<p>Two</p>"])
        ->and($changes)->not->toHaveKeys(['block_id', 'identifier', 'is_active', 'update_time']);
});
