<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

class Mage_ImportExport_Model_Export extends Mage_ImportExport_Model_Abstract
{
    public const FILTER_ELEMENT_GROUP = 'export_filter';
    public const FILTER_ELEMENT_SKIP  = 'skip_attr';

    /**
     * Filter fields types.
     */
    public const FILTER_TYPE_SELECT = 'select';
    public const FILTER_TYPE_INPUT  = 'input';
    public const FILTER_TYPE_DATE   = 'date';
    public const FILTER_TYPE_NUMBER = 'number';

    /**
     * Config keys.
     */
    public const CONFIG_KEY_ENTITIES = 'global/importexport/export_entities';
    public const CONFIG_KEY_FORMATS  = 'global/importexport/export_file_formats';

    /**
     * The private mount that keeps the export files until an administrator downloads them.
     */
    public const STORAGE_MOUNT = 'importexport_exports';

    /**
     * The number of days that an export file stays in the mount.
     */
    public const FILE_LIFETIME_DAYS = 7;

    /**
     * Entity adapter.
     *
     * @var Mage_ImportExport_Model_Export_Entity_Abstract
     */
    protected $_entityAdapter;

    /**
     * Writer object instance.
     *
     * @var Mage_ImportExport_Model_Export_Adapter_Abstract
     */
    protected $_writer;

    /**
     * Create instance of entity adapter and returns it.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Export_Entity_Abstract
     */
    protected function _getEntityAdapter()
    {
        if (!$this->_entityAdapter) {
            $validTypes = Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_ENTITIES);

            if (isset($validTypes[$this->getEntity()])) {
                try {
                    $_entityAdapter = Mage::getModel($validTypes[$this->getEntity()]['model']);
                    if (!$_entityAdapter instanceof Mage_ImportExport_Model_Export_Entity_Abstract) {
                        Mage::throwException(
                            Mage::helper('importexport')->__('Entity adapter object must be an instance of Mage_ImportExport_Model_Export_Entity_Abstract'),
                        );
                    }
                    $this->_entityAdapter = $_entityAdapter;
                } catch (Exception $e) {
                    Mage::logException($e);
                    Mage::throwException(
                        Mage::helper('importexport')->__('Invalid entity model'),
                    );
                }
            } else {
                Mage::throwException(Mage::helper('importexport')->__('Invalid entity'));
            }
            // check for entity codes integrity
            if ($this->getEntity() != $this->_entityAdapter->getEntityTypeCode()) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Input entity code is not equal to entity adapter code'),
                );
            }
            $this->_entityAdapter->setParameters($this->getData());
        }
        return $this->_entityAdapter;
    }

    /**
     * Get writer object.
     *
     * @throws Mage_Core_Exception
     * @return Mage_ImportExport_Model_Export_Adapter_Abstract
     */
    protected function _getWriter()
    {
        if (!$this->_writer) {
            $validWriters = Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_FORMATS);

            if (isset($validWriters[$this->getFileFormat()])) {
                try {
                    $_writer = Mage::getModel($validWriters[$this->getFileFormat()]['model'], $this->getDestination() ?? []);
                    if (!$_writer instanceof Mage_ImportExport_Model_Export_Adapter_Abstract) {
                        Mage::throwException(
                            Mage::helper('importexport')->__('Adapter object must be an instance of %s', 'Mage_ImportExport_Model_Export_Adapter_Abstract'),
                        );
                    }
                    $this->_writer = $_writer;
                } catch (Exception $e) {
                    Mage::logException($e);
                    Mage::throwException(
                        Mage::helper('importexport')->__('Invalid entity model'),
                    );
                }
            } else {
                Mage::throwException(Mage::helper('importexport')->__('Invalid file format'));
            }
        }
        return $this->_writer;
    }

    /**
     * Export data and return temporary file through array.
     *
     * This method will return following array:
     *
     * [
     *     'rows'  => count of written rows,
     *     'value' => path to created file,
     *     'type'  => 'file'
     * ]
     *
     * @throws Mage_Core_Exception
     * @return array
     */
    public function exportFile()
    {
        if (isset($this->_data[self::FILTER_ELEMENT_GROUP])) {
            $this->addLogComment(Mage::helper('importexport')->__('Begin export of %s', $this->getEntity()));
            $result = $this->_getEntityAdapter()
                ->setWriter($this->_getWriter())
                ->exportFile();

            if (isset($result['rows'])) {
                if (!$result['rows']) {
                    Mage::throwException(
                        Mage::helper('importexport')->__('There is no data for export'),
                    );
                } else {
                    $this->addLogComment([
                        Mage::helper('importexport')->__('Exported %s rows.', $result['rows']),
                        Mage::helper('importexport')->__('Export has been done.'),
                    ]);
                }
            }

            return $result;
        }
        Mage::throwException(
            Mage::helper('importexport')->__('No filter data provided'),
        );
    }

    /**
     * Clean up already loaded attribute collection.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function filterAttributeCollection(Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection)
    {
        return $this->_getEntityAdapter()->filterAttributeCollection($collection);
    }

    /**
     * Determine filter type for specified attribute.
     *
     * @static
     * @throws Exception
     * @return string
     */
    public static function getAttributeFilterType(Mage_Eav_Model_Entity_Attribute $attribute)
    {
        if ($attribute->usesSource() || $attribute->getFilterOptions()) {
            return self::FILTER_TYPE_SELECT;
        }
        if ($attribute->getBackendType() == 'datetime') {
            return self::FILTER_TYPE_DATE;
        }
        if ($attribute->getBackendType() == 'decimal' || $attribute->getBackendType() == 'int') {
            return self::FILTER_TYPE_NUMBER;
        }
        if ($attribute->isStatic()
                  || $attribute->getBackendType() == 'varchar'
                  || $attribute->getBackendType() == 'text') {
            return self::FILTER_TYPE_INPUT;
        }
        Mage::throwException(
            Mage::helper('importexport')->__('Can not determine attribute filter type'),
        );
    }

    /**
     * MIME-type for 'Content-Type' header.
     *
     * @return string
     */
    public function getContentType()
    {
        return $this->_getWriter()->getContentType();
    }

    /**
     * Override standard entity getter.
     *
     * @throw Exception
     * @return string
     */
    public function getEntity()
    {
        if (empty($this->_data['entity'])) {
            Mage::throwException(Mage::helper('importexport')->__('Entity is unknown'));
        }
        return $this->_data['entity'];
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function getEntityAttributeCollection()
    {
        return $this->_getEntityAdapter()->getAttributeCollection();
    }

    /**
     * Override standard entity getter.
     *
     * @throw Exception
     * @return string
     */
    public function getFileFormat()
    {
        if (empty($this->_data['file_format'])) {
            Mage::throwException(Mage::helper('importexport')->__('File format is unknown'));
        }
        return $this->_data['file_format'];
    }

    /**
     * The file that the writer fills. Without one, the writer uses a temporary file.
     */
    public function getDestination(): ?string
    {
        $value = $this->getData('destination');
        return $value === null ? null : (string) $value;
    }

    public function setDestination(?string $value): static
    {
        return $this->setData('destination', $value);
    }

    /**
     * Return file name for downloading.
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->getEntity() . '_' . date('Ymd_His') . '.' . $this->_getWriter()->getFileExtension();
    }

    /**
     * Queue the export of the current entity, file format and filters. The result goes to the
     * inbox of $adminUserId.
     *
     * @throws Mage_Core_Exception when the entity or the file format is unknown
     */
    public function queueExport(?int $adminUserId = null): void
    {
        if (!isset(Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_ENTITIES)[$this->getEntity()])) {
            Mage::throwException(Mage::helper('importexport')->__('Invalid entity'));
        }
        if (!isset(Mage_ImportExport_Model_Config::getModels(self::CONFIG_KEY_FORMATS)[$this->getFileFormat()])) {
            Mage::throwException(Mage::helper('importexport')->__('Invalid file format'));
        }
        if (!is_array($this->getData(self::FILTER_ELEMENT_GROUP))) {
            Mage::throwException(Mage::helper('importexport')->__('No filter data provided'));
        }

        $parameters = [];
        foreach (['entity', 'file_format', self::FILTER_ELEMENT_GROUP, self::FILTER_ELEMENT_SKIP] as $key) {
            if ($this->hasData($key)) {
                $parameters[$key] = $this->getData($key);
            }
        }

        \Maho\Queue\QueueManager::dispatch(
            new Mage_ImportExport_Model_ExportMessage($parameters, $adminUserId),
            queue: Mage_ImportExport_Model_ExportMessage::QUEUE_NAME,
        );
    }

    /**
     * Export the data to a new file in the export mount, and return the name of that file.
     *
     * @throws Mage_Core_Exception when there is no data to export
     */
    public function exportToStorage(): string
    {
        $this->setDestination(null);
        $result = $this->exportFile();
        if (!isset($result['value'])) {
            Mage::throwException(Mage::helper('importexport')->__('There is no data for export'));
        }

        // A random part makes the name hard to guess, because the download link has no secret key
        $fileName = $this->getEntity() . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8))
            . '.' . $this->_getWriter()->getFileExtension();
        $mount = $this->getStorage();
        if (($result['type'] ?? null) === 'string') {
            $mount->write($fileName, (string) $result['value']);
        } else {
            $this->_getWriter()->destruct();
            try {
                $mount->copyFromLocalFile((string) $result['value'], $fileName);
            } finally {
                @unlink((string) $result['value']);
            }
        }

        return $fileName;
    }

    /**
     * The path in the export mount of the file $fileName, or null when $fileName is not the name
     * of an export file. $fileName comes from a request, so only a plain file name passes.
     */
    public function getStoragePath(string $fileName): ?string
    {
        if (!preg_match('/^[a-z0-9_]+\.[a-z0-9]+$/i', $fileName)) {
            return null;
        }

        return \Maho\Io::getPathWithinMount($this->getStorage(), '', $fileName);
    }

    /**
     * Delete the export files that are older than FILE_LIFETIME_DAYS.
     */
    #[Maho\Config\CronJob('importexport_delete_expired_exports', schedule: '45 3 * * *')]
    public function deleteExpiredFiles(): void
    {
        $mount = $this->getStorage();
        $limit = time() - self::FILE_LIFETIME_DAYS * 86400;
        foreach ($mount->listFiles() as $file) {
            $lastModified = $file->lastModified();
            if ($lastModified !== null && $lastModified < $limit) {
                $mount->delete($file->path());
            }
        }
    }

    public function getStorage(): \Maho\Storage\Mount
    {
        return Mage::getStorage(self::STORAGE_MOUNT);
    }
}
