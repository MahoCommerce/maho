<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_ImportExport_Model_Import_Entity_Product getAffectedEntityIds()', function () {
    beforeEach(function (): void {
        $this->entity = function (): Mage_ImportExport_Model_Import_Entity_Product {
            $entity = new class extends Mage_ImportExport_Model_Import_Entity_Product {
                public function __construct() {}

                #[\Override]
                public function validateRow(array $rowData, $rowNum)
                {
                    return true;
                }

                public function seed(array $bunch, array $newSku): void
                {
                    $this->_newSku = $newSku;
                    $this->_dataSourceModel = new class ($bunch) {
                        private bool $served = false;

                        public function __construct(private readonly array $bunch) {}

                        public function getNextBunch(): ?array
                        {
                            if ($this->served) {
                                return null;
                            }
                            $this->served = true;
                            return $this->bunch;
                        }
                    };
                }
            };
            $entity->seed(
                [
                    0 => ['sku' => 'A', 'name' => 'x'],
                    1 => ['sku' => 'B', 'small_image' => '/b/b.jpg'],
                    2 => ['sku' => 'C'],
                    3 => ['sku' => '', '_media_image' => '/c/c.jpg'],
                ],
                ['A' => ['entity_id' => 11], 'B' => ['entity_id' => 12], 'C' => ['entity_id' => 13]],
            );
            return $entity;
        };
    });

    it('returns only the products whose rows set an image column', function (): void {
        expect(($this->entity)()->getAffectedEntityIds(true))->toBe([12, 13]);
    });

    it('returns every imported product by default', function (): void {
        expect(($this->entity)()->getAffectedEntityIds())->toBe([11, 12, 13]);
    });
});
