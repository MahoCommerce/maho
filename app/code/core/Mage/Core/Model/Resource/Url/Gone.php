<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Resource_Url_Gone extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('core/url_gone', 'gone_id');
    }

    /**
     * Record request paths as gone. An existing row for the same path and store is refreshed.
     *
     * @param list<array{request_path: string, store_id: int}> $rows
     * @return int number of rows written
     */
    public function markGone(array $rows, string $entityType): int
    {
        $deletedAt = Mage::app()->getLocale()->formatDateForDb('now');
        $data = [];
        foreach ($rows as $row) {
            $requestPath = trim((string) ($row['request_path'] ?? ''));
            if ($requestPath === '') {
                continue;
            }
            $data[] = [
                'store_id' => (int) ($row['store_id'] ?? 0),
                'request_path' => $requestPath,
                'entity_type' => $entityType,
                'deleted_at' => $deletedAt,
            ];
        }
        if ($data === []) {
            return 0;
        }

        return $this->_getWriteAdapter()->insertOnDuplicate(
            $this->getMainTable(),
            $data,
            ['entity_type', 'deleted_at'],
        );
    }

    /**
     * Check whether any of the request path candidates was recorded as gone for the store
     *
     * @param list<string> $requestPaths
     */
    public function isGone(array $requestPaths, int $storeId): bool
    {
        $paths = [];
        foreach ($requestPaths as $requestPath) {
            $requestPath = trim((string) $requestPath);
            if ($requestPath !== '') {
                $paths[] = mb_strtolower($requestPath);
            }
        }
        if ($paths === []) {
            return false;
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['gone_id'])
            ->where('request_path IN (?)', $paths)
            ->where('store_id IN (?)', [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId])
            ->limit(1);

        return $adapter->fetchOne($select) !== false;
    }

    /**
     * Remove the gone record for a path that a live rewrite claims again
     */
    public function forget(string $requestPath, int $storeId): void
    {
        $requestPath = trim($requestPath);
        if ($requestPath === '') {
            return;
        }

        $this->_getWriteAdapter()->delete($this->getMainTable(), [
            'request_path = ?' => $requestPath,
            'store_id = ?' => $storeId,
        ]);
    }

    public function purgeOlderThan(\DateTimeInterface $cutoff): int
    {
        return $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            ['deleted_at < ?' => $cutoff->format('Y-m-d H:i:s')],
        );
    }
}
