<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Catalog_Model_Observer image warm-up', function () {
    beforeEach(function (): void {
        $this->warmer = new class extends Mage_Catalog_Model_Product_Image_Warmer {
            /** @var list<list<int|string>> */
            public array $queued = [];

            #[\Override]
            public function queue(array $productIds): void
            {
                $this->queued[] = $productIds;
            }
        };
        Mage::register('_singleton/catalog/product_image_warmer', $this->warmer);
        $this->observer = new Mage_Catalog_Model_Observer();

        $this->importEvent = function (string $behavior): \Maho\Event\Observer {
            $adapter = $this->createMock(Mage_ImportExport_Model_Import_Entity_Product::class);
            $adapter->method('getBehavior')->willReturn($behavior);
            $adapter->method('getAffectedEntityIds')->willReturn([7, 9]);
            return new \Maho\Event\Observer(['event' => new \Maho\Event(['adapter' => $adapter])]);
        };
        $this->saveEvent = function (array $origData, array $data): \Maho\Event\Observer {
            $product = Mage::getModel('catalog/product')->setId(5);
            foreach ($origData as $key => $value) {
                $product->setOrigData($key, $value);
            }
            $product->addData($data);
            return new \Maho\Event\Observer(['event' => new \Maho\Event(['product' => $product])]);
        };
    });

    it('queues the products of an import', function (): void {
        $this->observer->queueImportedProductImageWarmUp(($this->importEvent)(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND));

        expect($this->warmer->queued)->toBe([[7, 9]]);
    });

    it('queues nothing for an import that deletes products', function (): void {
        $this->observer->queueImportedProductImageWarmUp(($this->importEvent)(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE));

        expect($this->warmer->queued)->toBe([]);
    });

    it('queues a saved product only when a role image changed', function (array $origData, array $data, array $queued): void {
        $this->observer->queueProductImageWarmUp(($this->saveEvent)($origData, $data));

        expect($this->warmer->queued)->toBe($queued);
    })->with([
        'a new small image' => [['small_image' => '/a/b/old.jpg'], ['small_image' => '/a/b/new.jpg'], [[5]]],
        'the same images' => [['image' => '/a/b/old.jpg'], ['image' => '/a/b/old.jpg', 'name' => 'New name'], []],
    ]);
});
