<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const TABLERATE_FREE_ITEMS_COUNTRY = 'IS';
const TABLERATE_FREE_ITEMS_ZIP = 'TRFS1';

function tablerateFreeItemsQuoteItem(float $rowTotal, bool $freeShipping): Mage_Sales_Model_Quote_Item
{
    $product = Mage::getModel('catalog/product')
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setWeight(1);

    return Mage::getModel('sales/quote_item')
        ->setData('product', $product)
        ->setData('qty', 1)
        ->setData('weight', 1)
        ->setData('base_row_total', $rowTotal)
        ->setData('free_shipping', $freeShipping);
}

/**
 * @param list<Mage_Sales_Model_Quote_Item> $items
 */
function tablerateFreeItemsCollect(array $items, ?float &$packageValueAfter = null): Mage_Shipping_Model_Rate_Result
{
    $packageValue = 0.0;
    $freeWeight = 0.0;
    $allFree = true;
    foreach ($items as $item) {
        $packageValue += (float) $item->getBaseRowTotal();
        if (!$item->getFreeShipping()) {
            $freeWeight += (float) $item->getWeight();
            $allFree = false;
        }
    }

    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setAllItems($items)
        ->setWebsiteId(1)
        ->setStoreId(1)
        ->setDestCountryId(TABLERATE_FREE_ITEMS_COUNTRY)
        ->setDestRegionId(0)
        ->setDestPostcode(TABLERATE_FREE_ITEMS_ZIP)
        ->setPackageValue($packageValue)
        ->setPackageWeight((float) count($items))
        ->setPackageQty((float) count($items))
        ->setFreeMethodWeight($freeWeight)
        // The address ships free when every item does
        ->setFreeShipping($allFree);

    /** @var Mage_Shipping_Model_Carrier_Tablerate $carrier */
    $carrier = Mage::getModel('shipping/carrier_tablerate');
    $carrier->setStore(1);

    $result = $carrier->collectRates($request);
    $packageValueAfter = (float) $request->getPackageValue();

    return $result;
}

function tablerateFreeItemsPrice(Mage_Shipping_Model_Rate_Result $result): ?float
{
    foreach ($result->getAllRates() as $rate) {
        if ($rate instanceof Mage_Shipping_Model_Rate_Result_Method) {
            return (float) $rate->getPrice();
        }
    }
    return null;
}

describe('table rates by order subtotal with free shipping items', function () {
    beforeEach(function () {
        $store = Mage::app()->getStore(1);
        $this->tablerateConfig = [];
        foreach (['active' => '1', 'condition_name' => 'package_value', 'handling_fee' => ''] as $field => $value) {
            $path = 'carriers/tablerate/' . $field;
            $this->tablerateConfig[$path] = $store->getConfig($path);
            $store->setConfig($path, $value);
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('shipping/tablerate');
        // Subtotal 0 and above: 10, 100 and above: 5, 200 and above: free
        foreach ([0 => 10, 100 => 5, 200 => 0] as $conditionValue => $price) {
            $adapter->insert($table, [
                'website_id' => 1,
                'dest_country_id' => TABLERATE_FREE_ITEMS_COUNTRY,
                'dest_region_id' => 0,
                'dest_zip' => TABLERATE_FREE_ITEMS_ZIP,
                'condition_name' => 'package_value',
                'condition_value' => $conditionValue,
                'price' => $price,
                'cost' => 0,
            ]);
        }
    });

    afterEach(function () {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->delete(Mage::getSingleton('core/resource')->getTableName('shipping/tablerate'), [
            'dest_country_id = ?' => TABLERATE_FREE_ITEMS_COUNTRY,
            'dest_zip = ?' => TABLERATE_FREE_ITEMS_ZIP,
        ]);

        $store = Mage::app()->getStore(1);
        foreach ($this->tablerateConfig as $path => $value) {
            $store->setConfig($path, $value);
        }
    });

    test('charges the rate of the subtotal without free shipping items', function () {
        // 80 ships free, so the 150 of the other item picks the 100 and above rate
        $result = tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(80.0, true),
            tablerateFreeItemsQuoteItem(150.0, false),
        ]);

        expect(tablerateFreeItemsPrice($result))->toBe(5.0);
    });

    test('offers the rate when the free shipping items cost more than the other items', function () {
        // 150 ships free, so the 60 of the other item picks the 0 and above rate
        $result = tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(150.0, true),
            tablerateFreeItemsQuoteItem(60.0, false),
        ]);

        expect(tablerateFreeItemsPrice($result))->toBe(10.0);
    });

    test('charges the rate of the whole subtotal when no item ships free', function () {
        // 80 + 60 picks the 100 and above rate
        $result = tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(80.0, false),
            tablerateFreeItemsQuoteItem(60.0, false),
        ]);

        expect(tablerateFreeItemsPrice($result))->toBe(5.0);
    });

    test('ships for free when every item ships free', function () {
        $result = tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(80.0, true),
            tablerateFreeItemsQuoteItem(150.0, true),
        ]);

        expect(tablerateFreeItemsPrice($result))->toBe(0.0);
    });

    test('ships for free when every item ships free and no rate starts at 0', function () {
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->delete(Mage::getSingleton('core/resource')->getTableName('shipping/tablerate'), [
            'dest_zip = ?' => TABLERATE_FREE_ITEMS_ZIP,
            'condition_value = ?' => 0,
        ]);

        $result = tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(80.0, true),
            tablerateFreeItemsQuoteItem(150.0, true),
        ]);

        expect(tablerateFreeItemsPrice($result))->toBe(0.0);
    });

    test('leaves the package value of the request as it was', function () {
        tablerateFreeItemsCollect([
            tablerateFreeItemsQuoteItem(80.0, true),
            tablerateFreeItemsQuoteItem(150.0, false),
        ], $packageValueAfter);

        expect($packageValueAfter)->toBe(230.0);
    });
});
