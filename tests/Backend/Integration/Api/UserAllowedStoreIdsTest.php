<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Api_Model_User::save() rebuilds its data array from a fixed field list,
 * so the allowed_store_ids restriction (read back by the API JWT layer on every
 * request) must be carried over explicitly or every token silently becomes
 * unrestricted.
 */
it('persists allowed_store_ids through save and clears it with null', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $user = Mage::getModel('api/user')
        ->setUsername('storeids_' . $suffix)
        ->setFirstname('Store')
        ->setLastname('Restricted')
        ->setEmail("storeids_{$suffix}@example.com")
        ->setApiKey('StoreIds' . $suffix . 'Secret1')
        ->setIsActive(1);
    $user->setData('allowed_store_ids', '[1,2]');
    $user->save();
    $userId = (int) $user->getId();

    try {
        $reloaded = Mage::getModel('api/user')->load($userId);
        expect($reloaded->getData('allowed_store_ids'))->toBe('[1,2]');

        $reloaded->setData('allowed_store_ids', null);
        $reloaded->save();

        expect(Mage::getModel('api/user')->load($userId)->getData('allowed_store_ids'))->toBeNull();
    } finally {
        Mage::getModel('api/user')->load($userId)->delete();
    }
});
