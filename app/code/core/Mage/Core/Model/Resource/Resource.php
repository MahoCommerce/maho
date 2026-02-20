<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Resource_Resource extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Database versions
     *
     * @var array|null
     */
    protected static $_versions        = null;

    /**
     * Resource data versions cache array
     *
     * @var array|null
     */
    protected static $_dataVersions    = null;

    /**
     * Resource maho versions cache array
     *
     * @var array|null
     */
    protected static $_mahoVersions    = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('core/resource', 'store_id');
    }

    /**
     * Fill static versions arrays.
     * This routine fetches Db and Data versions of at once to optimize sql requests. However, when upgrading, it's
     * possible that 'data' column will be created only after all Db installs are passed. So $neededType contains
     * information on main purpose of calling this routine, and even when 'data' column is absent - it won't require
     * reissuing new sql just to get 'db' version of module.
     *
     * @param string $needType Can be 'db' or 'data'
     * @return $this
     */
    protected function _loadVersionData($needType)
    {
        if ((($needType == 'db') && is_null(self::$_versions))
            || (($needType == 'data') && is_null(self::$_dataVersions))
            || (($needType == 'maho') && is_null(self::$_mahoVersions))
        ) {
            self::$_versions     = []; // Db version column always exists
            self::$_dataVersions = null; // Data version array will be filled only if Data column exist
            self::$_mahoVersions = null; // Maho version array will be filled only if Maho column exist

            if ($this->_getReadAdapter()->isTableExists($this->getMainTable())) {
                $select = $this->_getReadAdapter()->select()
                    ->from($this->getMainTable(), '*');
                $rowSet = $this->_getReadAdapter()->fetchAll($select);
                foreach ($rowSet as $row) {
                    self::$_versions[$row['code']] = $row['version'];
                    if (array_key_exists('data_version', $row)) {
                        if (is_null(self::$_dataVersions)) {
                            self::$_dataVersions = [];
                        }
                        self::$_dataVersions[$row['code']] = $row['data_version'];
                    }
                    if (array_key_exists('maho_version', $row)) {
                        if (is_null(self::$_mahoVersions)) {
                            self::$_mahoVersions = [];
                        }
                        self::$_mahoVersions[$row['code']] = $row['maho_version'];
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Get Module version from DB
     *
     * @param string $resName
     * @return string|false
     */
    public function getDbVersion($resName)
    {
        if (!$this->_getReadAdapter()) {
            return false;
        }
        $this->_loadVersionData('db');
        return self::$_versions[$resName] ?? false;
    }

    /**
     * Set module version into DB
     *
     * @param string $resName
     * @param string $version
     * @return int
     */
    public function setDbVersion($resName, $version)
    {
        $dbModuleInfo = [
            'code'    => $resName,
            'version' => $version,
        ];

        if ($this->getDbVersion($resName)) {
            self::$_versions[$resName] = $version;
            return $this->_getWriteAdapter()->update(
                $this->getMainTable(),
                $dbModuleInfo,
                ['code = ?' => $resName],
            );
        }
        self::$_versions[$resName] = $version;
        return $this->_getWriteAdapter()->insert($this->getMainTable(), $dbModuleInfo);
    }

    /**
     * Get resource data version
     *
     * @param string $resName
     * @return string|false
     */
    public function getDataVersion($resName)
    {
        if (!$this->_getReadAdapter()) {
            return false;
        }

        $this->_loadVersionData('data');

        return self::$_dataVersions[$resName] ?? false;
    }

    /**
     * Specify resource data version
     *
     * @param string $resName
     * @param string $version
     * @return $this
     */
    public function setDataVersion($resName, $version)
    {
        $data = [
            'code'          => $resName,
            'data_version'  => $version,
        ];

        if ($this->getDbVersion($resName) || $this->getDataVersion($resName)) {
            self::$_dataVersions[$resName] = $version;
            $this->_getWriteAdapter()->update($this->getMainTable(), $data, ['code = ?' => $resName]);
        } else {
            self::$_dataVersions[$resName] = $version;
            $this->_getWriteAdapter()->insert($this->getMainTable(), $data);
        }
        return $this;
    }

    /**
     * Get resource version for Maho-specific install scripts
     */
    public function getMahoVersion(string $resName): string|false
    {
        if (!$this->_getReadAdapter()) {
            return false;
        }

        $this->_loadVersionData('maho');

        return self::$_mahoVersions[$resName] ?? false;
    }

    /**
     * Specify resource version for Maho-specific install scripts
     */
    public function setMahoVersion(string $resName, string $version): self
    {
        $data = [
            'code'          => $resName,
            'maho_version'  => $version,
        ];

        if ($this->getDbVersion($resName) || $this->getDataVersion($resName) || $this->getMahoVersion($resName)) {
            self::$_dataVersions[$resName] = $version;
            $this->_getWriteAdapter()->update($this->getMainTable(), $data, ['code = ?' => $resName]);
        } else {
            self::$_dataVersions[$resName] = $version;
            $this->_getWriteAdapter()->insert($this->getMainTable(), $data);
        }
        return $this;
    }
}
