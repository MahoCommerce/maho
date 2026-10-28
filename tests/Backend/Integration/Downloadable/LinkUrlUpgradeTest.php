<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function linkUrlUpgradeRun(): void
{
    $script = Mage::getModuleDir('data', 'Mage_Downloadable') . '/downloadable_setup/data-upgrade-2.0.0-2.0.1.php';
    (function () use ($script): void {
        include $script;
    })->call(Mage::getResourceModel('catalog/setup', 'downloadable_setup'));
}

describe('Downloadable data upgrade 2.0.1', function (): void {
    it('percent-encodes a stored URL that the RFC 3986 parser refuses, and keeps the others', function (string $url, string $expected): void {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $table = $resource->getTableName('downloadable/link');
        $adapter->insert($table, [
            'product_id' => (int) $adapter->fetchOne($adapter->select()->from($resource->getTableName('catalog/product'), ['entity_id'])->limit(1)),
            'link_type' => Mage_Downloadable_Helper_Download::LINK_TYPE_URL,
            'link_url' => $url,
            'sample_type' => Mage_Downloadable_Helper_Download::LINK_TYPE_URL,
            'sample_url' => $url,
        ]);
        $linkId = (int) $adapter->lastInsertId($table);

        try {
            linkUrlUpgradeRun();
            $row = $adapter->fetchRow($adapter->select()->from($table, ['link_url', 'sample_url'])->where('link_id = ?', $linkId));
        } finally {
            $adapter->delete($table, ['link_id = ?' => $linkId]);
        }

        expect($row)->toBe(['link_url' => $expected, 'sample_url' => $expected]);
    })->with([
        ['https://files.example/guía.pdf', 'https://files.example/gu%C3%ADa.pdf'],
        ['https://files.example/a b.jpg?crop[x]=1', 'https://files.example/a%20b.jpg?crop%5Bx%5D=1'],
        ['https://files.example/ok%20already.pdf', 'https://files.example/ok%20already.pdf'],
        ['http://evil.example\\@good.example/', 'http://evil.example\\@good.example/'],
    ]);

    it('percent-encodes the URL in the sample and purchased item tables', function (): void {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $purchasedTable = $resource->getTableName('downloadable/link_purchased');
        $itemTable = $resource->getTableName('downloadable/link_purchased_item');
        $sampleTable = $resource->getTableName('downloadable/sample');

        $adapter->insert($purchasedTable, ['order_id' => null, 'customer_id' => null]);
        $purchasedId = (int) $adapter->lastInsertId($purchasedTable);
        $adapter->insert($itemTable, [
            'purchased_id' => $purchasedId,
            'order_item_id' => null,
            'link_type' => Mage_Downloadable_Helper_Download::LINK_TYPE_URL,
            'link_url' => 'https://files.example/guía.pdf',
        ]);
        $itemId = (int) $adapter->lastInsertId($itemTable);
        $adapter->insert($sampleTable, [
            'product_id' => (int) $adapter->fetchOne($adapter->select()->from($resource->getTableName('catalog/product'), ['entity_id'])->limit(1)),
            'sample_type' => Mage_Downloadable_Helper_Download::LINK_TYPE_URL,
            'sample_url' => 'https://files.example/a b.pdf',
        ]);
        $sampleId = (int) $adapter->lastInsertId($sampleTable);

        try {
            linkUrlUpgradeRun();
            $itemUrl = $adapter->fetchOne($adapter->select()->from($itemTable, ['link_url'])->where('item_id = ?', $itemId));
            $sampleUrl = $adapter->fetchOne($adapter->select()->from($sampleTable, ['sample_url'])->where('sample_id = ?', $sampleId));
        } finally {
            $adapter->delete($sampleTable, ['sample_id = ?' => $sampleId]);
            $adapter->delete($itemTable, ['item_id = ?' => $itemId]);
            $adapter->delete($purchasedTable, ['purchased_id = ?' => $purchasedId]);
        }

        expect($itemUrl)->toBe('https://files.example/gu%C3%ADa.pdf')
            ->and($sampleUrl)->toBe('https://files.example/a%20b.pdf');
    });
});
