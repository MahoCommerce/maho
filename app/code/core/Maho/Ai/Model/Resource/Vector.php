<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Resource_Vector extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/vector', 'vector_id');
    }

    /**
     * Upsert a vector for an entity. Overwrites any existing vector for the same
     * entity_type / entity_id / store_id combination.
     *
     * @param float[] $vector
     */
    public function saveForEntity(
        string $entityType,
        int $entityId,
        int $storeId,
        array $vector,
        int $dimensions,
        string $platform,
        string $model,
    ): void {
        $connection = $this->_getWriteAdapter();
        $table      = $this->getMainTable();
        $now        = Mage::app()->getLocale()->formatDateForDb('now');

        $connection->insertOnDuplicate(
            $table,
            [
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'store_id'    => $storeId,
                'platform'    => $platform,
                'model'       => $model,
                'dimensions'  => $dimensions,
                'vector'      => Mage::helper('core')->jsonEncode($vector),
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            ['platform', 'model', 'dimensions', 'vector', 'updated_at'],
        );
    }

    /**
     * Fetch vector data for an entity.
     *
     * @return array{vector: float[], model: string, platform: string, dimensions: int, updated_at: string}|null
     */
    public function getForEntity(string $entityType, int $entityId, int $storeId): ?array
    {
        $connection = $this->_getReadAdapter();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('entity_type = ?', $entityType)
            ->where('entity_id = ?', $entityId)
            ->where('store_id = ?', $storeId)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        $decoded = Mage::helper('core')->jsonDecode((string) $row['vector']);
        $vector = is_array($decoded) ? array_map('floatval', array_values($decoded)) : [];

        return [
            'vector'     => $vector,
            'model'      => (string) $row['model'],
            'platform'   => (string) $row['platform'],
            'dimensions' => (int) $row['dimensions'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
