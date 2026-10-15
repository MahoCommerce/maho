<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('the check for image settings that are not active yet', function () {
    beforeEach(function (): void {
        $this->table = \Maho\Queue\QueueManager::tableName();
        $this->config = Mage::getSingleton('core/image_config');
        $this->config->reset();

        $this->resizeAllRows = fn(): array => queueAdapter()->fetchAll(queueAdapter()->select()
            ->from($this->table)
            ->where('message_class = ?', Mage_Core_Model_Image_ResizeAllMessage::class));
        $this->savedRows = ($this->resizeAllRows)();
        queueAdapter()->delete($this->table, ['message_class = ?' => Mage_Core_Model_Image_ResizeAllMessage::class]);

        $this->restore = [];
        foreach (array_keys($this->config->getEntities()) as $entity) {
            foreach ($this->config->getStoreIds() as $storeId) {
                $path = Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . $entity;
                $this->restore[] = [$storeId, $path, Mage::getStoreConfig($path, $storeId)];
            }
        }

        $this->activateAll = function (): void {
            foreach (array_keys($this->config->getEntities()) as $entity) {
                foreach ($this->config->getStoreIds() as $storeId) {
                    Mage::app()->getStore($storeId)->setConfig(
                        Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . $entity,
                        (string) json_encode($this->config->getLiveSettings($entity, $storeId)->toArray()),
                    );
                }
            }
            $this->config->reset();
        };
    });

    afterEach(function (): void {
        foreach ($this->restore as [$storeId, $path, $value]) {
            Mage::app()->getStore($storeId)->setConfig($path, $value);
        }
        $this->config->reset();
        queueAdapter()->delete($this->table, ['message_class = ?' => Mage_Core_Model_Image_ResizeAllMessage::class]);
        foreach ($this->savedRows as $row) {
            queueAdapter()->insert($this->table, $row);
        }
    });

    it('queues one message for a pending entity, also when the cache marker is gone', function (): void {
        ($this->activateAll)();
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $old = new Mage_Core_Model_Image_Settings([150], quality: 50);
        Mage::app()->getStore($storeId)->setConfig(Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'product', (string) json_encode($old->toArray()));
        $this->config->reset();
        $observer = new Mage_Core_Model_Image_Observer();

        $observer->queuePendingEntities();
        Mage::app()->cleanCache([Mage_Core_Model_Image_Config::CACHE_TAG]);
        $observer->queuePendingEntities();

        $rows = ($this->resizeAllRows)();
        expect($this->config->getPendingStoreIds('product'))->toBe([$storeId])
            ->and($rows)->toHaveCount(1)
            ->and((string) $rows[0]['dedupe_key'])->toStartWith('image_resize_all_')
            ->and(unserialize((string) $rows[0]['body'])->entity)->toBe('product');
    });

    it('queues nothing when no store view has pending settings', function (): void {
        ($this->activateAll)();

        new Mage_Core_Model_Image_Observer()->queuePendingEntities();

        expect(($this->resizeAllRows)())->toBe([]);
    });
});
