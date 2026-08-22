<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Maho no longer converts prices into a website's currency on save, so every website-scope row
 * left in the table now counts as a price the merchant set. The upgrade to 2.0.1 says so once, in
 * the admin inbox, and names the command that lists them.
 */

function priceNoticeWriteRow(int $productId, int $storeId, float $value): void
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->insert($attribute->getBackend()->getTable(), [
        'attribute_id' => (int) $attribute->getId(),
        'store_id'     => $storeId,
        'entity_id'    => $productId,
        'value'        => $value,
    ]);
}

function priceNoticeDeleteRows(int $productId): void
{
    $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'price');
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($attribute->getBackend()->getTable(), [
        'entity_id = ?'  => $productId,
        'store_id != ?'  => Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID,
    ]);
}

function priceNoticeWriteOptionRow(int $productId, int $storeId, string $priceType): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $optionTable = $resource->getTableName('catalog/product_option');

    $adapter->insert($optionTable, [
        'product_id' => $productId,
        'type'       => 'field',
        'is_require' => 0,
        'sort_order' => 0,
    ]);
    $optionId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($optionTable, 'option_id')
            ->where('product_id = ?', $productId)
            ->order('option_id DESC')
            ->limit(1),
    );

    $adapter->insert($resource->getTableName('catalog/product_option_price'), [
        'option_id'  => $optionId,
        'store_id'   => $storeId,
        'price'      => 5.0,
        'price_type' => $priceType,
    ]);

    return $optionId;
}

function priceNoticeDeleteOption(int $optionId): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('catalog/product_option'),
        ['option_id = ?' => $optionId],
    );
}

/** The data-upgrade script, run the way the setup runs it: with the setup model as $this. */
function priceNoticeRunUpgrade(): void
{
    $script = Mage::getModuleDir('data', 'Mage_Catalog') . '/catalog_setup/data-upgrade-2.0.0-2.0.1.php';
    (function () use ($script): void {
        include $script;
    })->call(Mage::getResourceModel('catalog/setup', 'catalog_setup'));
}

function priceNoticeClearInbox(): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete(
        $resource->getTableName('adminnotification/inbox'),
        ['title LIKE ?' => '%no longer converted%'],
    );
}

function priceNoticeLatestTitles(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchCol(
        $adapter->select()
            ->from($resource->getTableName('adminnotification/inbox'), ['title'])
            ->order('notification_id DESC')
            ->limit(5),
    );
}

function priceNoticeIndexStatus(): string
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (string) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('index/process'), ['status'])
            ->where('indexer_code = ?', 'catalog_product_price'),
    );
}

beforeEach(function () {
    $this->product = loadSimplePricedProduct();
    $this->storeId = (int) Mage::app()->getStore(1)->getId();
    $this->indexStatus = priceNoticeIndexStatus();
    $this->rulesDirty = (int) Mage::getModel('catalogrule/flag')->loadSelf()->getState();
});

afterEach(function () {
    priceNoticeDeleteRows((int) $this->product->getId());
    priceNoticeClearInbox();
    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price')->changeStatus($this->indexStatus);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState($this->rulesDirty)->save();
});

it('counts website-scope price rows', function () {
    $before = Mage_Catalog_Helper_Data::countWebsiteScopePriceRows();

    priceNoticeWriteRow((int) $this->product->getId(), $this->storeId, 12.0);

    expect(Mage_Catalog_Helper_Data::countWebsiteScopePriceRows())->toBe($before + 1);
});

/*
 * Custom option prices were seeded by the same save this release removed, so a merchant told to
 * audit their price rows has to be told about these as well.
 */
it('counts fixed custom option price rows too', function () {
    $before = Mage_Catalog_Helper_Data::countWebsiteScopePriceRows();

    $optionId = priceNoticeWriteOptionRow((int) $this->product->getId(), $this->storeId, 'fixed');

    try {
        expect(Mage_Catalog_Helper_Data::countWebsiteScopePriceRows())->toBe($before + 1);
    } finally {
        priceNoticeDeleteOption($optionId);
    }
});

/* A percentage carries no currency, so the rule change never reached it. */
it('leaves percentage option prices out of the count', function () {
    $before = Mage_Catalog_Helper_Data::countWebsiteScopePriceRows();

    $optionId = priceNoticeWriteOptionRow((int) $this->product->getId(), $this->storeId, 'percent');

    try {
        expect(Mage_Catalog_Helper_Data::countWebsiteScopePriceRows())->toBe($before);
    } finally {
        priceNoticeDeleteOption($optionId);
    }
});

it('notifies the merchant when rows are present', function () {
    priceNoticeWriteRow((int) $this->product->getId(), $this->storeId, 12.0);

    expect(Mage::helper('catalog')->noticeWebsitePriceRows())->toBeGreaterThan(0)
        ->and(priceNoticeLatestTitles())
        ->toContain('Website prices are no longer converted from the currency rate');
});

it('raises the notice from the upgrade to 2.0.1', function () {
    priceNoticeWriteRow((int) $this->product->getId(), $this->storeId, 12.0);

    priceNoticeRunUpgrade();

    expect(priceNoticeLatestTitles())
        ->toContain('Website prices are no longer converted from the currency rate');
});

/*
 * The index and the rule prices were built from the old rows and the old rule; both are stale
 * the moment the upgrade lands, whether or not there are leftover rows to report.
 */
it('marks the price index and the catalog rules stale from the upgrade', function () {
    Mage::getSingleton('index/indexer')->getProcessByCode('catalog_product_price')
        ->changeStatus(Mage_Index_Model_Process::STATUS_PENDING);
    Mage::getModel('catalogrule/flag')->loadSelf()->setState(0)->save();

    priceNoticeRunUpgrade();

    expect(priceNoticeIndexStatus())->toBe(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX)
        ->and((int) Mage::getModel('catalogrule/flag')->loadSelf()->getState())->toBe(1);
});

it('says nothing when there are none', function () {
    if (Mage_Catalog_Helper_Data::countWebsiteScopePriceRows() > 0) {
        test()->markTestSkipped('This install already holds website-scope price rows');
    }

    priceNoticeRunUpgrade();

    expect(priceNoticeLatestTitles())
        ->not->toContain('Website prices are no longer converted from the currency rate');
});
