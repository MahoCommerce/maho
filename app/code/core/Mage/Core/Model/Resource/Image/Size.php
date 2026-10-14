<?php

/**
 * The rows of core_image_size: the width and the height of an image file on a mount.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Resource_Image_Size extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('core/image_size', 'path_hash');
    }

    /**
     * @return array{0: int, 1: int}|null the width and the height of the row of $hash, or null when there is no row
     */
    public function fetchSize(string $hash): ?array
    {
        $adapter = $this->_getReadAdapter();
        $row = $adapter->fetchRow($adapter->select()
            ->from($this->getMainTable(), ['width', 'height'])
            ->where('path_hash = ?', $hash));
        return $row ? [(int) $row['width'], (int) $row['height']] : null;
    }

    public function saveSize(string $hash, string $mount, string $path, int $width, int $height): void
    {
        $this->_getWriteAdapter()->insertOnDuplicate(
            $this->getMainTable(),
            ['path_hash' => $hash, 'mount' => $mount, 'path' => $path, 'width' => $width, 'height' => $height],
            ['mount', 'path', 'width', 'height'],
        );
    }

    /**
     * @param list<string> $hashes
     */
    public function deleteHashes(array $hashes): void
    {
        foreach (array_chunk($hashes, 500) as $chunk) {
            $this->_getWriteAdapter()->delete($this->getMainTable(), ['path_hash IN (?)' => $chunk]);
        }
    }

    /**
     * Delete the rows of the files below $folder on $mount.
     */
    public function deleteFolder(string $mount, string $folder): void
    {
        $prefix = $folder . '/';
        $adapter = $this->_getReadAdapter();
        // LIKE ignores the case on MySQL and reads "_" as any character, so PHP checks each path
        $rows = $adapter->fetchPairs($adapter->select()
            ->from($this->getMainTable(), ['path_hash', 'path'])
            ->where('mount = ?', $mount)
            ->where('path LIKE ?', $prefix . '%'));
        $this->deleteHashes(array_map(strval(...), array_keys(array_filter($rows, fn($path): bool => str_starts_with((string) $path, $prefix)))));
    }

    /**
     * The hashes of $hashes that have a row.
     *
     * @param list<string> $hashes
     * @return array<string, true>
     */
    public function fetchExistingHashes(array $hashes): array
    {
        $adapter = $this->_getReadAdapter();
        $existing = [];
        foreach (array_chunk($hashes, 1000) as $chunk) {
            foreach ($adapter->fetchCol($adapter->select()->from($this->getMainTable(), ['path_hash'])->where('path_hash IN (?)', $chunk)) as $hash) {
                $existing[(string) $hash] = true;
            }
        }
        return $existing;
    }

    /**
     * Up to $limit rows in the order of their hash, after the row of $afterHash.
     *
     * @return list<array{path_hash: string, mount: string, path: string}>
     */
    public function fetchBatch(string $afterHash, int $limit): array
    {
        $adapter = $this->_getReadAdapter();
        /** @var list<array{path_hash: string, mount: string, path: string}> */
        return $adapter->fetchAll($adapter->select()
            ->from($this->getMainTable(), ['path_hash', 'mount', 'path'])
            ->where('path_hash > ?', $afterHash)
            ->order('path_hash')
            ->limit($limit));
    }
}
