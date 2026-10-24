<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The report export actions unpack getCsv() and getExcel() into _prepareDownloadResponse().
 * With a file name and a limit, both methods return [file name, content, content type].
 * A string return type made each of these exports end in a TypeError.
 */
describe('report grid export', function () {
    it('returns the download arguments for a CSV export', function (string $alias) {
        $grid = Mage::app()->getLayout()->createBlock($alias);
        $result = $grid->getCsv('report.csv', -1);

        expect($result)->toBeArray()->toHaveCount(3)
            ->and($result[0])->toStartWith('report-')->toEndWith('.csv')
            ->and($result[1])->toBeString()
            ->and($result[2])->toBe('text/csv');
    })->with([
        'report grid' => 'adminhtml/report_customer_accounts_grid',
        'widget grid' => 'adminhtml/report_review_customer_grid',
    ]);

    it('returns the download arguments for an Excel export', function (string $alias) {
        $grid = Mage::app()->getLayout()->createBlock($alias);
        $result = $grid->getExcel('report.xml', -1);

        expect($result)->toBeArray()->toHaveCount(3)
            ->and($result[0])->toStartWith('report-')->toEndWith('.xml')
            ->and($result[1])->toBeString()
            ->and($result[2])->toBe('application/vnd.ms-excel');
    })->with([
        'report grid' => 'adminhtml/report_customer_accounts_grid',
        'widget grid' => 'adminhtml/report_review_customer_grid',
    ]);

    it('returns the content only without a file name', function () {
        $grid = Mage::app()->getLayout()->createBlock('adminhtml/report_review_customer_grid');

        expect($grid->getCsv())->toBeString();
    });
});
