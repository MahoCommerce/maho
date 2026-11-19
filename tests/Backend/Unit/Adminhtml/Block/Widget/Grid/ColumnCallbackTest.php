<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * GHSA-8m3h-7cgh-jf96: layout XML can give a column option as a string, such as "Class::method", but not
 * as an object. A grid column calls only a closure, an invokable object or an [object, method] callback.
 */
final class GridColumnCallbackProbe
{
    public static bool $called = false;

    public static function frame(string $value): string
    {
        self::$called = true;
        return 'framed';
    }

    public static function get(Maho\DataObject $row): string
    {
        self::$called = true;
        return 'got';
    }

    public function frameBound(string $value): string
    {
        return 'framed';
    }
}

function gridColumnCallbackColumn(array $data): Mage_Adminhtml_Block_Widget_Grid_Column
{
    /** @var Mage_Adminhtml_Block_Widget_Grid_Column $column */
    $column = Mage::app()->getLayout()->createBlock('adminhtml/widget_grid_column');
    $column->setData($data + ['index' => 'name', 'renderer' => 'adminhtml/widget_grid_column_renderer_text']);
    $column->setId('name');
    return $column;
}

/**
 * Return a static [class, method] callback, as layout XML could give it.
 *
 * @return array{class-string, string}
 */
function gridColumnCallbackStatic(string $method): array
{
    return [GridColumnCallbackProbe::class, $method];
}

beforeEach(function () {
    GridColumnCallbackProbe::$called = false;
});

it('does not call a frame callback that is not bound to an object', function (mixed $callback) {
    $column = gridColumnCallbackColumn(['frame_callback' => $callback]);
    $row = new Maho\DataObject(['name' => 'value']);

    expect($column->getRowField($row))->toBe('value')
        ->and($column->getRowFieldExport($row))->toBe('value')
        ->and(GridColumnCallbackProbe::$called)->toBeFalse();
})->with([
    'Class::method string' => ['GridColumnCallbackProbe::frame'],
    '[Class, method] array' => [gridColumnCallbackStatic('frame')],
]);

it('calls a frame callback that is bound to an object', function (callable $callback) {
    $column = gridColumnCallbackColumn(['frame_callback' => $callback]);

    expect($column->getRowField(new Maho\DataObject(['name' => 'value'])))->toBe('framed');
})->with([
    'closure' => [fn(string $value): string => 'framed'],
    'first-class callable' => [new GridColumnCallbackProbe()->frameBound(...)],
    '[object, method] array' => [[new GridColumnCallbackProbe(), 'frameBound']],
]);

it('does not call a getter that is not bound to an object', function () {
    $column = gridColumnCallbackColumn(['getter' => gridColumnCallbackStatic('get')]);

    expect($column->getRowField(new Maho\DataObject(['name' => 'value'])))->toBe('')
        ->and(GridColumnCallbackProbe::$called)->toBeFalse();
});

it('calls a getter that is a closure', function () {
    $column = gridColumnCallbackColumn(['getter' => fn(Maho\DataObject $row): string => 'got']);

    expect($column->getRowField(new Maho\DataObject(['name' => 'value'])))->toBe('got');
});
