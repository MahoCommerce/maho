<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('URL rewrite lookup by request path', function () {
    it('prefers the rewrite of the store view over the rewrite of store 0 for the same path', function () {
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $path = 'load-order-' . uniqid();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('core/url_rewrite');
        // The row of store 0 goes in first, so the database returns it first.
        foreach ([Mage_Core_Model_App::ADMIN_STORE_ID => 'default-page.html', $storeId => 'view-page.html'] as $rowStoreId => $target) {
            $write->insert($table, [
                'store_id' => $rowStoreId,
                'id_path' => "{$path}-{$rowStoreId}",
                'request_path' => $path,
                'target_path' => $target,
                'is_system' => 0,
            ]);
        }

        try {
            $rewrite = Mage::getModel('core/url_rewrite')->setStoreId($storeId);
            $rewrite->loadByRequestPath([$path, $path . '/']);

            expect($rewrite->getTargetPath())->toBe('view-page.html');
        } finally {
            $write->delete($table, ['request_path = ?' => $path]);
        }
    });
});
