<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('ignores a currency in the price filter that is not a string', function (): void {
    /** @var Mage_Adminhtml_Block_Widget_Grid_Column $column */
    $column = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column');
    $column->setData(['index' => 'price', 'currency_code' => 'USD']);

    /** @var Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Price $filter */
    $filter = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column_filter_price');
    $filter->setColumn($column);
    $filter->setValue(['from' => '10', 'currency' => ['x']]);

    expect($filter->getCondition()['from'])->toBe('10.000000');
});
