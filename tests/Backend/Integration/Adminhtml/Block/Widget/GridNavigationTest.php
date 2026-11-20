<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Save the navigation of the order grid sorted by $column, and return the IDs in the order of the grid.
 *
 * @return list<int>
 */
function storeOrderGridNavigation(string $column, string $direction): array
{
    $collection = Mage::getResourceModel('sales/order_grid_collection');
    $collection->getSelect()->order("main_table.{$column} {$direction}");
    $grid = new class extends Mage_Adminhtml_Block_Widget_Grid {
        public function storeNavigation(): void
        {
            $this->_storeNavigationQuery();
        }
    };
    $grid->setId('navigation_test_grid')->setCollection($collection);
    $grid->storeNavigation();

    $select = clone $collection->getSelect();
    $select->reset(Maho\Db\Select::COLUMNS)->columns(['entity_id'])->order("entity_id {$direction}");
    return array_map('intval', Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol($select));
}

/** @return array{prev_id: ?int, next_id: ?int, position: int, total: int}|null */
function orderGridNavigation(int $orderId): ?array
{
    Mage::app()->getRequest()->setParam('order_id', $orderId);
    $container = new class extends Mage_Adminhtml_Block_Widget_Form_Container {
        protected ?string $_gridNavigationId = 'navigation_test_grid';
        protected string $_objectId = 'order_id';
    };
    $navigation = $container->getGridNavigation();
    return $navigation === null ? null : array_intersect_key($navigation, array_flip(['prev_id', 'next_id', 'position', 'total']));
}

it('finds the previous and the next order of the grid sort, also around the guest orders that have no customer', function (string $direction) {
    $ids = storeOrderGridNavigation('customer_id', $direction);
    $guests = (int) Mage::getSingleton('core/resource')->getConnection('core_read')->fetchOne(
        'SELECT COUNT(*) FROM ' . Mage::getSingleton('core/resource')->getTableName('sales/order_grid') . ' WHERE customer_id IS NULL',
    );
    expect($guests)->toBeGreaterThan(0)->and(count($ids))->toBeGreaterThan($guests);

    foreach ($ids as $position => $id) {
        expect(orderGridNavigation($id))->toBe([
            'prev_id' => $ids[$position - 1] ?? null,
            'next_id' => $ids[$position + 1] ?? null,
            'position' => $position + 1,
            'total' => count($ids),
        ], "order {$id}");
    }
})->with(['ASC', 'DESC']);
