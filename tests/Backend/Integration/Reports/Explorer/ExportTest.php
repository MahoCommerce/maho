<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    Mage::app()->getCacheInstance()->banUse(Mage_Reports_Model_Explorer_Runner::CACHE_TYPE);
});

it('writes the export file of the queue to the private mount and sends a notice with its link', function () {
    $adminUserId = (int) Mage::getResourceModel('admin/user_collection')->setPageSize(1)->getFirstItem()->getId();
    $request = ['dataset' => 'sales_orders', 'metrics' => ['orders', 'net_sales'], 'dimensions' => ['period:day'], 'range' => 'last_30_days'];
    $lastNotice = (int) Mage::getResourceModel('adminnotification/inbox_collection')->getSize();

    (new Mage_Reports_Model_Queue_ExplorerExportHandler())(new Mage_Reports_Model_Queue_ExplorerExport(
        $request,
        Mage_Reports_Model_Explorer_Access::full()->toArray(),
        $adminUserId,
    ));

    $notice = Mage::getResourceModel('adminnotification/inbox_collection')
        ->setOrder('notification_id', 'DESC')->setPageSize(1)->getFirstItem();
    expect((int) Mage::getResourceModel('adminnotification/inbox_collection')->getSize())->toBe($lastNotice + 1)
        ->and($notice->getTitle())->toBe('The report export is ready');

    preg_match('/report_\d+_\d{8}_\d{6}_[a-f0-9]{16}\.csv/', (string) $notice->getUrl() . ' ' . $notice->getDescription(), $match);
    $export = Mage::getModel('reports/explorer_export');
    $path = $export->getStoragePath($match[0] ?? '', $adminUserId);
    try {
        expect($path)->not->toBeNull()
            ->and($export->getStorage()->fileExists($path))->toBeTrue();
        $lines = explode("\n", trim($export->getStorage()->read($path)));
        // The days of the period fill the empty buckets, so the file has one line for each day after the header
        expect($lines[0])->toBe('"Order date",Orders,"Net sales"')
            ->and(count($lines))->toBe(31)
            ->and($export->getStoragePath((string) $match[0], $adminUserId + 1))->toBeNull()
            ->and($export->getStoragePath('../' . $match[0], $adminUserId))->toBeNull();
    } finally {
        if ($path !== null) {
            $export->getStorage()->delete($path);
        }
        $notice->delete();
    }
});

it('keeps every field of an access through the array of the queue', function () {
    $access = new Mage_Reports_Model_Explorer_Access(['sales_orders'], false, [2], ['sales_orders']);
    $copy = Mage_Reports_Model_Explorer_Access::fromArray(json_decode(json_encode($access->toArray()), true));

    expect($copy)->toEqual($access)
        ->and($copy->toArray())->toBe($access->toArray());
});

it('queues an export above the page limit and streams it when no administrator is given', function () {
    // The runner reads the limit in the admin scope, and export() in the current store
    $stores = [Mage::app()->getStore(), Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID)];
    $path = Mage_Reports_Model_Explorer_Runner::XML_PATH . 'max_rows';
    $original = $stores[1]->getConfig($path);
    foreach ($stores as $store) {
        $store->setConfig($path, '1');
    }
    \Maho\Queue\QueueManager::reset();
    clearQueueTable();
    $request = Maho\Report\Request::fromArray(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:day'], 'range' => 'last_30_days', 'limit' => 1]);
    $service = Mage::getService('reports/explorer');
    $access = Mage_Reports_Model_Explorer_Access::full();

    try {
        expect($service->export($request, $access, 1))->toBe(['queued' => true]);
        $rows = fetchQueueRows();
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['queue'])->toBe(Mage_Reports_Model_Queue_ExplorerExport::QUEUE_NAME)
            ->and($rows[0]['message_class'])->toBe(Mage_Reports_Model_Queue_ExplorerExport::class);

        clearQueueTable();
        $result = $service->export($request, $access, null);
        expect($result['queued'])->toBeFalse()
            ->and(fetchQueueRows())->toBe([]);
        fclose($result['stream']);
    } finally {
        foreach ($stores as $store) {
            $store->setConfig($path, $original);
        }
        clearQueueTable();
        \Maho\Queue\QueueManager::reset();
    }
});
