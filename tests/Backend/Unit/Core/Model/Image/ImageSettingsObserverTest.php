<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('the check for image settings whose files do not exist yet', function () {
    beforeEach(function (): void {
        $this->table = \Maho\Queue\QueueManager::tableName();
        $this->config = Mage::getSingleton('core/image_config');
        $this->config->reset();

        $this->resizeAllRows = fn(): array => queueAdapter()->fetchAll(queueAdapter()->select()
            ->from($this->table)
            ->where('message_class = ?', Mage_Core_Model_Image_ResizeAllMessage::class));
        $this->savedRows = ($this->resizeAllRows)();
        queueAdapter()->delete($this->table, ['message_class = ?' => Mage_Core_Model_Image_ResizeAllMessage::class]);

        $this->resizer = new class extends Mage_Core_Model_Image_Resizer {
            /** @var array<string, list<string>> */
            public array $missingFolders = [];

            #[\Override]
            public function getMissingFolders(string $entity): array
            {
                return $this->missingFolders[$entity] ?? [];
            }
        };
        $this->originalResizer = Mage::registry('_singleton/core/image_resizer');
        Mage::unregister('_singleton/core/image_resizer');
        Mage::register('_singleton/core/image_resizer', $this->resizer);
    });

    afterEach(function (): void {
        Mage::unregister('_singleton/core/image_resizer');
        if ($this->originalResizer !== null) {
            Mage::register('_singleton/core/image_resizer', $this->originalResizer);
        }
        $this->config->reset();
        queueAdapter()->delete($this->table, ['message_class = ?' => Mage_Core_Model_Image_ResizeAllMessage::class]);
        foreach ($this->savedRows as $row) {
            queueAdapter()->insert($this->table, $row);
        }
    });

    it('queues one message for an entity with new settings, also when the cache marker is gone', function (): void {
        $this->resizer->missingFolders = ['product' => ['0123456789ab/300']];
        $observer = new Mage_Core_Model_Image_Observer();

        $observer->queueNewSettings();
        Mage::app()->cleanCache([Mage_Core_Model_Image_Config::CACHE_TAG]);
        $observer->queueNewSettings();

        $rows = ($this->resizeAllRows)();
        expect($rows)->toHaveCount(1)
            ->and((string) $rows[0]['dedupe_key'])->toStartWith('image_resize_all_')
            ->and(unserialize((string) $rows[0]['body'])->entity)->toBe('product');
    });

    it('queues nothing when the files of every settings exist', function (): void {
        new Mage_Core_Model_Image_Observer()->queueNewSettings();

        expect(($this->resizeAllRows)())->toBe([]);
    });
});
