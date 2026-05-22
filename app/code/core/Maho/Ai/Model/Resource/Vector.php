<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
     * @return array{vector: float[], model: string, platform: string, dimensions: int}|null
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

        return [
            'vector'     => Mage::helper('core')->jsonDecode($row['vector']) ?? [],
            'model'      => $row['model'],
            'platform'   => $row['platform'],
            'dimensions' => (int) $row['dimensions'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
