<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Resource_Product_Image_Variant extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('catalog/product_image_variant', 'variant_id');
    }

    /**
     * @return array<string, array{store_id: int, destination_subdir: string, params: array<string, mixed>, last_seen: string}>
     */
    public function loadAll(): array
    {
        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()->from($this->getMainTable(), [
                'path',
                'store_id',
                'destination_subdir',
                'params',
                'last_seen' => new \Maho\Db\Expr('COALESCE(last_seen_at, created_at)'),
            ]),
        );

        $variants = [];
        foreach ($rows as $row) {
            $params = json_decode((string) $row['params'], true);
            if (!is_array($params)) {
                continue;
            }
            $variants[(string) $row['path']] = [
                'store_id' => (int) $row['store_id'],
                'destination_subdir' => (string) $row['destination_subdir'],
                'params' => $params,
                'last_seen' => substr((string) $row['last_seen'], 0, 10),
            ];
        }
        return $variants;
    }

    /**
     * Record that a template rendered the variant now
     */
    public function touch(string $path): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['last_seen_at' => Mage::app()->getLocale()->formatDateForDb('now')],
            ['path = ?' => $path],
        );
    }

    /**
     * Delete the variants that no template rendered since $before
     *
     * @return int the number of deleted variants
     */
    public function deleteNotSeenSince(DateTimeImmutable $before): int
    {
        return $this->_getWriteAdapter()->delete(
            $this->getMainTable(),
            ['COALESCE(last_seen_at, created_at) < ?' => Mage::app()->getLocale()->formatDateForDb($before)],
        );
    }

    /**
     * Record a variant. A path that another request or node recorded first stays as it is.
     *
     * @param array<string, mixed> $params
     */
    public function add(string $path, int $storeId, string $destinationSubdir, array $params): void
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $this->_getWriteAdapter()->insertIgnore($this->getMainTable(), [
            'path' => $path,
            'store_id' => $storeId,
            'destination_subdir' => $destinationSubdir,
            'params' => Mage::helper('core')->jsonEncode($params),
            'created_at' => $now,
            'last_seen_at' => $now,
        ]);
    }
}
