<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getMaxVersions(): int
    {
        return (int) Mage::getStoreConfig('general/contentversion/max_versions');
    }

    public function getMaxAgeDays(): int
    {
        return (int) Mage::getStoreConfig('general/contentversion/max_age_days');
    }

    /**
     * Create a version snapshot of the entity's original state (before modification).
     *
     * All model fields are captured automatically, excluding only the primary key.
     * Uses getOrigData() to capture the DB state before in-memory changes.
     * Falls back to getData() when origData is not available (e.g. restoreVersion).
     */
    public function createVersion(Mage_Core_Model_Abstract $model, string $entityType, string $editor): void
    {
        if (!$model->getId()) {
            return;
        }

        $idField = $model->getIdFieldName();
        $hasOrigData = $model->getOrigData() !== null;

        // Snapshot all fields except the primary key
        $sourceData = $hasOrigData ? $model->getOrigData() : $model->getData();
        $snapshot = array_diff_key($sourceData, [$idField => true]);

        // Skip if nothing meaningful has changed
        if ($hasOrigData) {
            $changed = false;
            foreach ($snapshot as $field => $origValue) {
                if ((string) $model->getData($field) !== (string) $origValue) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                return;
            }
        }

        $entityId = (int) $model->getId();

        /** @var Maho_ContentVersion_Model_Resource_Version $resource */
        $resource = Mage::getResourceSingleton('contentversion/version');
        $resource->insertWithNextVersionNumber([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'content_data' => Mage::helper('core')->jsonEncode($snapshot),
            'editor' => $editor,
        ]);

        $this->cleanExpired($entityType, $entityId);
    }

    /**
     * Restore entity from a version snapshot.
     * Creates a new version of the current state first (so restore is reversible).
     *
     * @return array{entity_type: string, entity_id: int}
     */
    public function restoreVersion(int $versionId): array
    {
        /** @var Maho_ContentVersion_Model_Version $version */
        $version = Mage::getModel('contentversion/version')->load($versionId);
        if (!$version->getId()) {
            Mage::throwException($this->__('Version not found.'));
        }

        $entityType = $version->getData('entity_type');
        $entityId = (int) $version->getData('entity_id');
        $model = $this->loadEntity($entityType, $entityId);

        if (!$model->getId()) {
            Mage::throwException($this->__('Source entity not found.'));
        }

        // Apply snapshot data and save — the observer will automatically
        // version the current state before the save completes
        $snapshot = $version->getContentDataDecoded();
        $model->addData($snapshot);
        $model->save();

        return ['entity_type' => $entityType, 'entity_id' => $entityId];
    }

    private function loadEntity(string $entityType, int $entityId): Mage_Core_Model_Abstract
    {
        return match ($entityType) {
            'cms_page' => Mage::getModel('cms/page')->load($entityId),
            'cms_block' => Mage::getModel('cms/block')->load($entityId),
            'blog_post' => $this->loadBlogPost($entityId),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    private function loadBlogPost(int $entityId): Mage_Core_Model_Abstract
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            Mage::throwException($this->__('Blog module is not enabled.'));
        }
        return Mage::getModel('blog/post')->load($entityId);
    }

    private function cleanExpired(string $entityType, int $entityId): void
    {
        $maxVersions = $this->getMaxVersions();
        $maxAgeDays = $this->getMaxAgeDays();

        /** @var Maho_ContentVersion_Model_Resource_Version $resource */
        $resource = Mage::getResourceSingleton('contentversion/version');
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = $resource->getMainTable();

        // Delete by version count
        if ($maxVersions > 0) {
            $keepSelect = $adapter->select()
                ->from($table, ['version_id'])
                ->where('entity_type = ?', $entityType)
                ->where('entity_id = ?', $entityId)
                ->order('version_number DESC')
                ->limit($maxVersions);

            $keepIds = $adapter->fetchCol($keepSelect);

            if (!empty($keepIds)) {
                $adapter->delete($table, [
                    'entity_type = ?' => $entityType,
                    'entity_id = ?' => $entityId,
                    'version_id NOT IN (?)' => $keepIds,
                ]);
            }
        }

        // Delete by age
        if ($maxAgeDays > 0) {
            $cutoff = new \DateTime("-{$maxAgeDays} days", new \DateTimeZone('UTC'));
            $adapter->delete($table, [
                'entity_type = ?' => $entityType,
                'entity_id = ?' => $entityId,
                'created_at < ?' => $cutoff->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
