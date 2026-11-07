<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('formats each report date column with its own pattern', function (): void {
    $row = new Maho\DataObject(['period' => '2026-11-07']);
    $year = new Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date();
    $month = new Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date();
    $year->setColumn(new Mage_Adminhtml_Block_Widget_Grid_Column(['index' => 'period', 'format' => 'yyyy']));
    $month->setColumn(new Mage_Adminhtml_Block_Widget_Grid_Column(['index' => 'period', 'format' => 'MM']));

    expect($year->render($row))->toBe('2026')
        ->and($month->render($row))->toBe('11')
        ->and($year->render($row))->toBe('2026');
});

it('shows the calendar month and year for each report period', function (): void {
    $row = new Maho\DataObject(['period' => '2018-12-31']);

    foreach (['year' => '2018', 'month' => '2018-12'] as $period => $expected) {
        $renderer = new Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date();
        $renderer->setColumn(new Mage_Adminhtml_Block_Widget_Grid_Column(['index' => 'period', 'period_type' => $period]));

        expect($renderer->render($row))->toBe($expected);
    }
});
