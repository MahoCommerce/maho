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
     * @return array<string, array{store_id: int, destination_subdir: string, params: array<string, mixed>}>
     */
    public function loadAll(): array
    {
        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()->from($this->getMainTable(), ['path', 'store_id', 'destination_subdir', 'params']),
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
            ];
        }
        return $variants;
    }

    /**
     * Record a variant. A path that another request or node recorded first stays as it is.
     *
     * @param array<string, mixed> $params
     */
    public function add(string $path, int $storeId, string $destinationSubdir, array $params): void
    {
        $this->_getWriteAdapter()->insertIgnore($this->getMainTable(), [
            'path' => $path,
            'store_id' => $storeId,
            'destination_subdir' => $destinationSubdir,
            'params' => Mage::helper('core')->jsonEncode($params),
            'created_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);
    }
}
