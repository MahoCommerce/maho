<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function searchHelperForQuery(string $queryText): Mage_CatalogSearch_Helper_Data
{
    Mage::app()->getRequest()->setParam(Mage_CatalogSearch_Helper_Data::QUERY_VAR_NAME, $queryText);
    return new Mage_CatalogSearch_Helper_Data();
}

describe('getQueryText', function () {
    it('keeps 4-byte characters, which the utf8mb4 query column stores', function () {
        $helper = searchHelperForQuery("\u{1F449}\u{1F3FB} acc6.top \u{1F448}\u{1F3FB} comprar");

        expect($helper->getQueryText())->toBe("\u{1F449}\u{1F3FB} acc6.top \u{1F448}\u{1F3FB} comprar");
    });

    it('keeps accented and non-latin characters', function () {
        $helper = searchHelperForQuery('membresía Ελλάδα');

        expect($helper->getQueryText())->toBe('membresía Ελλάδα');
    });
});
