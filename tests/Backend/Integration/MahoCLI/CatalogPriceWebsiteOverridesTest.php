<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\CatalogPriceWebsiteOverrides;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

/**
 * Maho no longer converts a base price into every website's currency on save, so any store-scope
 * row left behind by that seeding now counts as an explicit price and stops following the rate.
 * The command lists those rows and flags the ones that are exactly the rate away from the default,
 * which is what the old seeding would have written. The currency is an ISO 4217 "X" code.
 */
const OVERRIDES_AUDIT_CODE = 'overrides_audit_ws';
const OVERRIDES_AUDIT_CURRENCY = 'XTO';
const OVERRIDES_AUDIT_PRICE = 100.0;
const OVERRIDES_AUDIT_RATE = 2.0;

function overridesAuditWebsite(): Mage_Core_Model_Website
{
    return createPriceWebsite(OVERRIDES_AUDIT_CODE, 95);
}

function overridesAuditConfigure(int $scope = 1): void
{
    if ($scope === 0) {
        restorePriceScope(OVERRIDES_AUDIT_CODE);
    } else {
        configurePriceWebsite(OVERRIDES_AUDIT_CODE, OVERRIDES_AUDIT_CURRENCY, $scope);
    }
}

function overridesAuditStoreId(): int
{
    return (int) Mage::app()->getStore(OVERRIDES_AUDIT_CODE)->getId();
}

function overridesAuditProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('overrides-audit', OVERRIDES_AUDIT_PRICE, overridesAuditWebsite());
}

/** Written straight to the table: the save no longer produces rows like these. */
function overridesAuditWriteRow(int $productId, int $storeId, float $value): void
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

function overridesAuditRun(array $input = []): CommandTester
{
    $command = new CatalogPriceWebsiteOverrides();
    (new Application())->add($command);

    $tester = new CommandTester($command);
    $tester->execute($input);

    return $tester;
}

beforeEach(function () {
    overridesAuditWebsite();
    overridesAuditConfigure();
    Mage::getModel('directory/currency')->saveRates([
        Mage::app()->getBaseCurrencyCode() => [OVERRIDES_AUDIT_CURRENCY => OVERRIDES_AUDIT_RATE],
    ]);
    $this->product = overridesAuditProduct();
});

afterEach(function () {
    if (isset($this->product) && $this->product->getId()) {
        $this->product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)->delete();
    }
    overridesAuditConfigure(0);
    dropCurrencyRates(OVERRIDES_AUDIT_CURRENCY);
    deletePriceWebsite(OVERRIDES_AUDIT_CODE);
});

it('flags a row that is exactly the rate away from the default value', function () {
    overridesAuditWriteRow(
        (int) $this->product->getId(),
        overridesAuditStoreId(),
        OVERRIDES_AUDIT_PRICE * OVERRIDES_AUDIT_RATE,
    );

    expect(overridesAuditRun()->getDisplay())
        ->toContain('1 of which match the current rate exactly');
});

it('leaves a row the merchant could have typed unflagged', function () {
    overridesAuditWriteRow((int) $this->product->getId(), overridesAuditStoreId(), 149.0);

    expect(overridesAuditRun()->getDisplay())
        ->toContain('0 of which match the current rate exactly');
});

/** Custom option prices were seeded by the same save, so the audit has to reach them too. */
it('lists a custom option price row', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $optionTable = $resource->getTableName('catalog/product_option');

    $adapter->insert($optionTable, [
        'product_id' => (int) $this->product->getId(),
        'type'       => 'field',
        'is_require' => 0,
        'sort_order' => 0,
    ]);
    $optionId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($optionTable, 'option_id')
            ->where('product_id = ?', (int) $this->product->getId())
            ->order('option_id DESC')
            ->limit(1),
    );

    $adapter->insert($resource->getTableName('catalog/product_option_price'), [
        'option_id'  => $optionId,
        'store_id'   => overridesAuditStoreId(),
        'price'      => 149.0,
        'price_type' => 'fixed',
    ]);

    expect(overridesAuditRun()->getDisplay())->toContain(sprintf('option #%d', $optionId));
});

it('lists a custom option value price row', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $optionTable = $resource->getTableName('catalog/product_option');
    $valueTable = $resource->getTableName('catalog/product_option_type_value');

    $adapter->insert($optionTable, [
        'product_id' => (int) $this->product->getId(),
        'type'       => 'drop_down',
        'is_require' => 0,
        'sort_order' => 0,
    ]);
    $optionId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($optionTable, 'option_id')
            ->where('product_id = ?', (int) $this->product->getId())
            ->order('option_id DESC')
            ->limit(1),
    );
    $adapter->insert($valueTable, ['option_id' => $optionId, 'sort_order' => 0]);
    $valueId = (int) $adapter->fetchOne(
        $adapter->select()
            ->from($valueTable, 'option_type_id')
            ->where('option_id = ?', $optionId)
            ->order('option_type_id DESC')
            ->limit(1),
    );

    $adapter->insert($resource->getTableName('catalog/product_option_type_price'), [
        'option_type_id' => $valueId,
        'store_id'       => overridesAuditStoreId(),
        'price'          => 149.0,
        'price_type'     => 'fixed',
    ]);

    expect(overridesAuditRun()->getDisplay())->toContain(sprintf('option value #%d', $valueId));
});

it('lists a downloadable link price row by website', function () {
    $link = Mage::getModel('downloadable/link')
        ->setProductId((int) $this->product->getId())
        ->setSortOrder(0)
        ->setNumberOfDownloads(0)
        ->setIsShareable(2)
        ->setLinkType('url')
        ->setLinkUrl('https://example.com/overrides-audit')
        ->setUseDefaultTitle(true)
        ->setUseDefaultPrice(true)
        ->save();

    $resource = Mage::getSingleton('core/resource');
    $priceTable = $resource->getTableName('downloadable/link_price');
    $resource->getConnection('core_write')->insert($priceTable, [
        'link_id'    => (int) $link->getId(),
        'website_id' => 0,
        'price'      => OVERRIDES_AUDIT_PRICE,
    ]);
    $resource->getConnection('core_write')->insert($priceTable, [
        'link_id'    => (int) $link->getId(),
        'website_id' => (int) overridesAuditWebsite()->getId(),
        'price'      => OVERRIDES_AUDIT_PRICE * OVERRIDES_AUDIT_RATE,
    ]);

    $display = overridesAuditRun()->getDisplay();
    expect($display)->toContain(sprintf('link #%d', (int) $link->getId()))
        ->and($display)->toContain(OVERRIDES_AUDIT_CODE)
        ->and($display)->toContain('1 of which match the current rate exactly');
});

it('lists only the flagged rows when asked', function () {
    overridesAuditWriteRow(
        (int) $this->product->getId(),
        overridesAuditStoreId(),
        OVERRIDES_AUDIT_PRICE * OVERRIDES_AUDIT_RATE,
    );

    expect(overridesAuditRun(['--suspect-only' => true])->getDisplay())
        ->toContain('1 row(s) listed, 1 of which match');
});
