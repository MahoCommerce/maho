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

class Maho_ContentVersion_Model_Service
{
    private const SNAPSHOT_FIELDS = [
        'cms_page' => [
            'title',
            'content_heading',
            'content',
            'identifier',
            'meta_keywords',
            'meta_description',
            'is_active',
        ],
        'cms_block' => [
            'title',
            'content',
            'identifier',
            'is_active',
        ],
        'blog_post' => [
            'title',
            'content',
            'url_key',
            'meta_title',
            'meta_keywords',
            'meta_description',
            'is_active',
            'publish_date',
        ],
    ];

    /**
     * Create a version snapshot of the entity's original state (before modification).
     *
     * Uses getOrigData() to capture the DB state before in-memory changes.
     * Falls back to getData() when origData is not available (e.g. restoreVersion).
     */
    public function createVersion(Mage_Core_Model_Abstract $model, string $entityType, string $editor): void
    {
        if (!$model->getId()) {
            return;
        }

        $fields = self::SNAPSHOT_FIELDS[$entityType] ?? null;
        if ($fields === null) {
            return;
        }

        // Use original data (DB state) when available, fall back to current data
        $hasOrigData = $model->getOrigData() !== null;
        $snapshot = [];
        foreach ($fields as $field) {
            $snapshot[$field] = $hasOrigData
                ? $model->getOrigData($field)
                : $model->getData($field);
        }

        // Skip if nothing meaningful has changed
        if ($hasOrigData) {
            $changed = false;
            foreach ($fields as $field) {
                if ((string) $model->getData($field) !== (string) $snapshot[$field]) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                return;
            }
        }

        /** @var Maho_ContentVersion_Model_Resource_Version $resource */
        $resource = Mage::getResourceSingleton('contentversion/version');
        $entityId = (int) $model->getId();
        $versionNumber = $resource->getNextVersionNumber($entityType, $entityId);

        /** @var Maho_ContentVersion_Model_Version $version */
        $version = Mage::getModel('contentversion/version');
        $version->setData([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'version_number' => $versionNumber,
            'editor' => $editor,
        ]);
        $version->setContentDataEncoded($snapshot);
        $version->save();

        $this->cleanExpired($entityType, $entityId);
    }

    /**
     * Restore entity from a version snapshot.
     * Creates a new version of the current state first (so restore is reversible).
     */
    public function restoreVersion(int $versionId): Mage_Core_Model_Abstract
    {
        /** @var Maho_ContentVersion_Model_Version $version */
        $version = Mage::getModel('contentversion/version')->load($versionId);
        if (!$version->getId()) {
            Mage::throwException(Mage::helper('contentversion')->__('Version not found.'));
        }

        $entityType = $version->getData('entity_type');
        $entityId = (int) $version->getData('entity_id');
        $model = $this->loadEntity($entityType, $entityId);

        if (!$model->getId()) {
            Mage::throwException(Mage::helper('contentversion')->__('Source entity not found.'));
        }

        // Apply snapshot data and save — the observer will automatically
        // version the current state before the save completes
        $snapshot = $version->getContentDataDecoded();
        $model->addData($snapshot);
        $model->save();

        return $model;
    }

    private function loadEntity(string $entityType, int $entityId): Mage_Core_Model_Abstract
    {
        return match ($entityType) {
            'cms_page' => Mage::getModel('cms/page')->load($entityId),
            'cms_block' => Mage::getModel('cms/block')->load($entityId),
            'blog_post' => Mage::getModel('blog/post')->load($entityId),
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };
    }

    private function cleanExpired(string $entityType, int $entityId): void
    {
        /** @var Maho_ContentVersion_Helper_Data $helper */
        $helper = Mage::helper('contentversion');
        $maxVersions = $helper->getMaxVersions();
        $maxAgeDays = $helper->getMaxAgeDays();

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
