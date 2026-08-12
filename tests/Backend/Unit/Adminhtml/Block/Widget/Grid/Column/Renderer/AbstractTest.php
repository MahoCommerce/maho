<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The renderer only ever reads its column, so a bare column block (no grid) is enough.
 */
function makePositionColumnRenderer(array $columnData): Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Number
{
    /** @var Mage_Adminhtml_Block_Widget_Grid_Column $column */
    $column = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column');
    $column->setData($columnData + ['index' => 'position']);
    $column->setId('position');

    /** @var Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Number $renderer */
    $renderer = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column_renderer_number');
    return $renderer->setColumn($column);
}

describe('Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract::render', function () {
    it('renders only the input element for an editable column', function () {
        $html = makePositionColumnRenderer(['editable' => true])
            ->render(new Maho\DataObject(['position' => 7]));

        expect($html)->toStartWith('<input ')
            ->and($html)->toContain('name="position"')
            ->and($html)->toContain('value="7"')
            // the value used to be printed next to the input as well
            ->and(substr_count($html, '7'))->toBe(1);
    });

    it('still renders the input for an editable column with an empty value', function () {
        $html = makePositionColumnRenderer(['editable' => true])
            ->render(new Maho\DataObject(['position' => null]));

        expect($html)->toStartWith('<input ')
            ->and($html)->not->toContain('&nbsp;');
    });

    it('renders the plain value for a non-editable column', function () {
        $html = makePositionColumnRenderer([])
            ->render(new Maho\DataObject(['position' => 7]));

        expect($html)->toBe(7);
    });
});

describe('Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract::renderExport', function () {
    it('exports the raw value of an editable column, not its input markup', function () {
        $html = makePositionColumnRenderer(['editable' => true])
            ->renderExport(new Maho\DataObject(['position' => 7]));

        expect($html)->toBe('7');
    });
});
