<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The CSV import of products and categories writes the tables directly. Its row validation calls the checks of the
 * stock service, the product model and the category service, so a row that the admin and the API refuse is a row
 * error of the import (#1348).
 */

/**
 * Validate one product row and return the error messages of the import.
 *
 * @param array<string, string> $row
 * @return list<string>
 */
function importSharedChecksProductErrors(array $row): array
{
    /** @var Mage_ImportExport_Model_Import_Entity_Product $import */
    $import = Mage::getModel('importexport/import_entity_product');
    $import->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);
    $import->validateRow($row + [
        'sku' => 'PEST-IMPORT-CHECK-' . uniqid(),
        '_type' => 'simple',
        '_attribute_set' => 'Default',
        'name' => 'Pest Import Check',
        'description' => 'Pest',
        'short_description' => 'Pest',
        'price' => '10',
        'weight' => '1',
        'status' => '1',
        'visibility' => '4',
        'tax_class_id' => '0',
    ], 0);
    return array_keys($import->getErrorMessages());
}

/**
 * Validate the category rows $rows, with the column names in the first row, and return the error messages.
 *
 * @param list<list<string>> $rows
 * @return list<string>
 */
function importSharedChecksCategoryErrors(array $rows): array
{
    /** @var Mage_ImportExport_Model_Import_Entity_Category $import */
    $import = Mage::getModel('importexport/import_entity_category');
    $import->setSource(Mage_ImportExport_Model_Import_Adapter::createArrayAdapter($rows));
    $import->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);
    $import->validateData();
    return array_keys($import->getErrorMessages());
}

describe('the product import', function (): void {
    it('refuses the stock values that the stock service refuses', function (array $columns, string $field): void {
        try {
            Mage::getService('cataloginventory/stock')->prepare($columns);
            $service = [];
        } catch (Mage_Core_Exception_Input $e) {
            $service = array_column($e->getErrors(), 'message');
        }

        $import = importSharedChecksProductErrors(array_map(strval(...), $columns));

        expect($service)->toHaveCount(1)
            ->and($service[0])->toContain($field)
            ->and($import)->toContain($service[0]);
    })->with([
        'a negative minimum quantity' => [['qty' => 5, 'min_qty' => -1], 'min_qty'],
        'a quantity above the maximum' => [['qty' => 1000000000], 'Quantity'],
        'an unknown backorders value' => [['qty' => 5, 'backorders' => 7], 'backorders'],
    ]);

    it('refuses a date range that starts after it ends, as the product model does', function (): void {
        $import = importSharedChecksProductErrors(['special_from_date' => '2030-02-01', 'special_to_date' => '2030-01-01']);

        expect($import)->toContain('The From Date value should be less than or equal to the To Date value.');
    });

    it('accepts valid stock values and a valid date range', function (): void {
        $import = importSharedChecksProductErrors([
            'qty' => '5', 'min_qty' => '0', 'backorders' => '1',
            'special_from_date' => '2030-01-01', 'special_to_date' => '2030-02-01',
        ]);

        expect(array_filter($import, static fn(string $message): bool => str_contains($message, 'cannot be negative')
            || str_contains($message, 'Quantity must be')
            || str_contains($message, 'backorders must be')
            || str_contains($message, 'From Date')))->toBe([]);
    });
});

describe('the category import', function (): void {
    it('refuses a sort order that the category service refuses', function (string $available, string $default, string $code): void {
        $import = importSharedChecksCategoryErrors([
            ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active', 'available_sort_by', 'default_sort_by'],
            ['', '2', '', 'Pest Import Sort', 'pest-import-sort-' . uniqid(), '1', $available, $default],
        ]);
        $service = Mage::getService('catalog/category')->getSortByErrors(
            $available === '' ? [] : explode(',', $available),
            $default,
        );

        expect($service)->toHaveCount(1)
            ->and(reset($service))->toContain("Invalid sort-by code \"{$code}\"")
            ->and($import)->toBe(array_values($service));
    })->with([
        'an unknown available sort order' => ['name,pestnosuchcode', '', 'pestnosuchcode'],
        'an unknown default sort order' => ['', 'pestnosuchcode', 'pestnosuchcode'],
    ]);

    it('accepts the sort orders of the store', function (): void {
        $import = importSharedChecksCategoryErrors([
            ['category_id', 'parent_id', '_store', 'name', 'url_key', 'is_active', 'available_sort_by', 'default_sort_by'],
            ['', '2', '', 'Pest Import Sort', 'pest-import-sort-' . uniqid(), '1', 'name,price', 'name'],
        ]);

        expect($import)->toBe([]);
    });
});
