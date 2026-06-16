<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Setup
{
    public const DEFAULT_SETUP_CONNECTION  = 'core_setup';
    public const VERSION_COMPARE_EQUAL     = 0;
    public const VERSION_COMPARE_LOWER     = -1;
    public const VERSION_COMPARE_GREATER   = 1;

    public const TYPE_DB_INSTALL           = 'install';
    public const TYPE_DB_UPGRADE           = 'upgrade';
    public const TYPE_DB_ROLLBACK          = 'rollback';
    public const TYPE_DB_UNINSTALL         = 'uninstall';
    public const TYPE_DATA_INSTALL         = 'data-install';
    public const TYPE_DATA_UPGRADE         = 'data-upgrade';

    /**
     * Setup resource name
     * @var string
     */
    protected $_resourceName;

    /**
     * Setup resource configuration object
     *
     * @var Mage_Core_Model_Config_Element
     */
    protected $_resourceConfig;

    /**
     * Connection configuration object
     *
     * @var Mage_Core_Model_Config_Element
     */
    protected $_connectionConfig;

    /**
     * Setup module configuration object
     *
     * @var Mage_Core_Model_Config_Element
     */
    protected $_moduleConfig;

    /**
     * Call afterApplyAllUpdates method flag
     *
     * @var bool
     */
    protected $_callAfterApplyAllUpdates = false;

    /**
     * Setup Connection
     *
     * @var Maho\Db\Adapter\AdapterInterface|Maho\Db\Adapter\Pdo\Mysql
     */
    protected $_conn;
    /**
     * Tables cache array
     *
     * @var array
     */
    protected $_tables = [];
    /**
     * Tables data cache array
     *
     * @var array
     */
    protected $_setupCache = [];

    /**
     * Flag which shows, that setup has hooked queries from DB adapter
     *
     * @var bool
     */
    protected $_queriesHooked = false;

    /**
     * Flag which allow to detect that some schema update was applied dueting request
     *
     * @var bool
     */
    protected static $_hadUpdates;

    /**
     * Flag which allow run data install or upgrade
     *
     * @var bool
     */
    protected static $_schemaUpdatesChecked;

    /**
     * Initialize resource configurations, setup connection, etc
     *
     * @param string $resourceName the setup resource name
     * @param string|null $moduleName owning module, for resources discovered by
     *        convention (a sql/data <name>_setup directory with no <resources>
     *        declaration). Ignored when the resource is declared in config.
     */
    public function __construct($resourceName, ?string $moduleName = null)
    {
        $config = Mage::getConfig();
        $this->_resourceName = $resourceName;
        $this->_resourceConfig = $config->getResourceConfig($resourceName);
        $connection = $config->getResourceConnectionConfig($resourceName);
        if ($connection) {
            $this->_connectionConfig = $connection;
        } else {
            $this->_connectionConfig = $config->getResourceConnectionConfig(self::DEFAULT_SETUP_CONNECTION);
        }

        $modName = '';
        if ($this->_resourceConfig && $this->_resourceConfig->setup) {
            $modName = (string) $this->_resourceConfig->setup->module;
        }
        if ($modName === '') {
            $modName = (string) $moduleName;
        }
        $this->_moduleConfig = $config->getModuleConfig($modName);
        $connection = Mage::getSingleton('core/resource')->getConnection($this->_resourceName);
        /**
         * If module setup configuration wasn't loaded
         */
        if (!$connection) {
            $connection = Mage::getSingleton('core/resource')->getConnection($this->_resourceName);
        }
        $this->_conn = $connection;
    }

    /**
     * Get connection object
     *
     * @return Maho\Db\Adapter\AdapterInterface|Maho\Db\Adapter\Pdo\Mysql
     */
    public function getConnection()
    {
        return $this->_conn;
    }

    /**
     * Add table placeholder/table name relation
     *
     * @param string $tableName
     * @param string $realTableName
     * @return $this
     */
    public function setTable($tableName, $realTableName)
    {
        $this->_tables[$tableName] = $realTableName;
        return $this;
    }

    /**
     * Get table name (validated by db adapter) by table placeholder
     *
     * @param string|array $tableName
     * @return string
     */
    public function getTable($tableName)
    {
        $cacheKey = $this->_getTableCacheName($tableName);
        if (!isset($this->_tables[$cacheKey])) {
            $this->_tables[$cacheKey] = Mage::getSingleton('core/resource')->getTableName($tableName);
        }
        return $this->_tables[$cacheKey];
    }

    /**
     * Retrieve table name for cache
     *
     * @param string|array $tableName
     * @return string
     */
    protected function _getTableCacheName($tableName)
    {
        if (is_array($tableName)) {
            return implode('_', $tableName);
        }
        return $tableName;
    }

    /**
     * Get core resource resource model
     *
     * @return Mage_Core_Model_Resource_Resource
     */
    protected function _getResource()
    {
        return Mage::getResourceSingleton('core/resource');
    }

    /**
     * Every setup resource to run, keyed by resource name, as
     * ['module' => owning module, 'class' => setup class].
     *
     * Two sources, merged:
     *  - resources declared in config under global/resources (the only place a
     *    custom setup <class> or a non-default connection can live);
     *  - convention: any module shipping a sql/<name>_setup or data/<name>_setup
     *    directory whose name is not already a declared resource. These run with
     *    the base setup class, so a module needing only the default setup needs
     *    no <resources> XML at all. The directory name is the resource name (and
     *    the core_resource version key), so existing installs keep their history.
     *
     * Declared resources come first, preserving their depends_on-respecting
     * config order; convention resources are appended. Anything order-sensitive
     * relative to another module must therefore stay declared.
     *
     * @return array<string, array{module: string, class: string}>
     */
    public static function getAllSetupResources(): array
    {
        $resources = [];

        foreach (Mage::getConfig()->getNode('global/resources')->children() as $resName => $resource) {
            if (!$resource->setup) {
                continue;
            }
            $resources[(string) $resName] = [
                'module' => (string) $resource->setup->module,
                'class'  => isset($resource->setup->class) ? $resource->setup->getClassName() : self::class,
            ];
        }

        foreach (Mage::getConfig()->getNode('modules')->children() as $modName => $module) {
            if (!$module->is('active')) {
                continue;
            }
            foreach (['sql', 'data'] as $type) {
                $dir = Mage::getModuleDir($type, (string) $modName);
                foreach (Maho::listDirectories($dir) as $subDir) {
                    if (str_ends_with($subDir, '_setup') && !isset($resources[$subDir])) {
                        $resources[$subDir] = ['module' => (string) $modName, 'class' => self::class];
                    }
                }
            }
        }

        return $resources;
    }

    /**
     * @return bool
     */
    public static function applyAllUpdates()
    {
        Mage::app()->setUpdateMode(true);
        self::$_hadUpdates = false;

        $afterApplyUpdates = [];
        foreach (self::getAllSetupResources() as $resName => $resource) {
            /** @var Mage_Core_Model_Resource_Setup $setupClass */
            $setupClass = new $resource['class']($resName, $resource['module']);
            $setupClass->applyUpdates();
            if ($setupClass->getCallAfterApplyAllUpdates()) {
                $afterApplyUpdates[] = $setupClass;
            }
        }

        foreach ($afterApplyUpdates as $setupClass) {
            $setupClass->afterApplyAllUpdates();
        }

        Mage::app()->setUpdateMode(false);
        self::$_schemaUpdatesChecked = true;
        return true;
    }

    public static function applyAllDataUpdates()
    {
        if (!self::$_schemaUpdatesChecked) {
            return;
        }
        foreach (self::getAllSetupResources() as $resName => $resource) {
            /** @var Mage_Core_Model_Resource_Setup $setupClass */
            $setupClass = new $resource['class']($resName, $resource['module']);
            $setupClass->applyDataUpdates();
        }
    }

    /**
     * Apply data updates to the system after upgrading.
     *
     * @return $this
     */
    public function applyDataUpdates()
    {
        $dataVer = $this->_getResource()->getDataVersion($this->_resourceName);
        $configVer = (string) $this->_moduleConfig->version;
        if ($dataVer !== false) {
            $status = version_compare($configVer, $dataVer);
            if ($status == self::VERSION_COMPARE_GREATER) {
                $this->_upgradeData($dataVer, $configVer);
            }
        } elseif ($configVer) {
            $this->_installData($configVer);
        }
        return $this;
    }

    /**
     * Enumerate pending schema/data scripts across all setup resources
     * without running them. Powers `migrate --dry-run`.
     *
     * @return array<string, list<array{type: string, toVersion: string, fileName: string}>>
     *         keyed by resource name; only resources with pending scripts appear
     */
    public static function getAllPendingUpdates(): array
    {
        $pending = [];
        foreach (self::getAllSetupResources() as $resName => $resource) {
            /** @var Mage_Core_Model_Resource_Setup $setupClass */
            $setupClass = new $resource['class']($resName, $resource['module']);
            $updates = $setupClass->getPendingUpdates();
            if ($updates !== []) {
                $pending[$resName] = $updates;
            }
        }
        return $pending;
    }

    /**
     * Pending install/upgrade scripts for this resource, in apply order, without
     * running them. Mirrors the version comparison applyUpdates()/
     * applyDataUpdates() use to pick scripts, but only
     * enumerates the files: the SQL an imperative script emits is unknowable
     * without executing it.
     *
     * @return list<array{type: string, toVersion: string, fileName: string}>
     */
    public function getPendingUpdates(): array
    {
        $configVer = (string) $this->_moduleConfig->version;
        $updates = [];

        $dbVer = $this->_getResource()->getDbVersion($this->_resourceName);
        if ($dbVer === false && $configVer) {
            $install = $this->_getAvailableDbFiles(self::TYPE_DB_INSTALL, '', $configVer);
            $from = $install === [] ? '' : $install[array_key_last($install)]['toVersion'];
            $updates = array_merge(
                $updates,
                $this->_labelPendingFiles('schema', $install),
                $this->_labelPendingFiles('schema', $this->_getAvailableDbFiles(self::TYPE_DB_UPGRADE, $from, $configVer)),
            );
        } elseif ($dbVer !== false && version_compare($configVer, $dbVer) === self::VERSION_COMPARE_GREATER) {
            $updates = array_merge(
                $updates,
                $this->_labelPendingFiles('schema', $this->_getAvailableDbFiles(self::TYPE_DB_UPGRADE, $dbVer, $configVer)),
            );
        }

        $dataVer = $this->_getResource()->getDataVersion($this->_resourceName);
        if ($dataVer === false && $configVer) {
            $install = $this->_getAvailableDataFiles(self::TYPE_DATA_INSTALL, '', $configVer);
            $from = $install === [] ? '' : $install[array_key_last($install)]['toVersion'];
            $updates = array_merge(
                $updates,
                $this->_labelPendingFiles('data', $install),
                $this->_labelPendingFiles('data', $this->_getAvailableDataFiles(self::TYPE_DATA_UPGRADE, $from, $configVer)),
            );
        } elseif ($dataVer !== false && version_compare($configVer, $dataVer) === self::VERSION_COMPARE_GREATER) {
            $updates = array_merge(
                $updates,
                $this->_labelPendingFiles('data', $this->_getAvailableDataFiles(self::TYPE_DATA_UPGRADE, $dataVer, $configVer)),
            );
        }

        return $updates;
    }

    /**
     * Tag each enumerated file with its script type for the dry-run report.
     *
     * @param list<array{toVersion: string, fileName: string}> $files
     * @return list<array{type: string, toVersion: string, fileName: string}>
     */
    protected function _labelPendingFiles(string $type, array $files): array
    {
        $labeled = [];
        foreach ($files as $file) {
            $labeled[] = [
                'type'      => $type,
                'toVersion' => $file['toVersion'],
                'fileName'  => $file['fileName'],
            ];
        }
        return $labeled;
    }

    /**
     * Apply module resource install, upgrade and data scripts
     *
     * @return $this|true
     */
    public function applyUpdates()
    {
        $dbVer = $this->_getResource()->getDbVersion($this->_resourceName);
        $configVer = (string) $this->_moduleConfig->version;

        // Module is installed
        if ($dbVer !== false) {
            $status = version_compare($configVer, $dbVer);
            switch ($status) {
                case self::VERSION_COMPARE_LOWER:
                    $this->_rollbackResourceDb($configVer, $dbVer);
                    break;
                case self::VERSION_COMPARE_GREATER:
                    $this->_upgradeResourceDb($dbVer, $configVer);
                    break;
                default:
                    return true;
            }
        } elseif ($configVer) {
            $this->_installResourceDb($configVer);
        }

        $this->_unhookQueries();

        return $this;
    }

    /**
     * Hooks queries to strengthen backwards compatibility in MySQL.
     * Currently - dynamically updates column types for foreign keys, when their targets were changed
     * during MMDB development.
     *
     * @return $this
     */
    protected function _hookQueries()
    {
        $this->_queriesHooked = true;
        /** @var Maho\Db\Adapter\Pdo\Mysql $adapter */
        $adapter = $this->getConnection();
        $adapter->setQueryHook(['object' => $this, 'method' => 'callbackQueryHook']);
        return $this;
    }

    /**
     * Removes query hook
     *
     * @return $this
     */
    protected function _unhookQueries()
    {
        if (!$this->_queriesHooked) {
            return $this;
        }
        /** @var Maho\Db\Adapter\Pdo\Mysql $adapter */
        $adapter = $this->getConnection();
        $adapter->setQueryHook(null);
        $this->_queriesHooked = false;
        return $this;
    }

    /**
     * Callback function, called on every query adapter processes.
     * Modifies SQL or tables, so that foreign keys will be set successfully
     *
     * @param string $sql
     * @param array $bind
     * @return $this
     */
    public function callbackQueryHook(&$sql, &$bind)
    {
        Mage::getSingleton('core/resource_setup_query_modifier', [$this->getConnection()])
            ->processQuery($sql, $bind);
        return $this;
    }

    /**
     * Run data install scripts
     *
     * @param string $newVersion
     * @return $this
     */
    protected function _installData($newVersion)
    {
        $oldVersion = $this->_modifyResourceDb(self::TYPE_DATA_INSTALL, '', $newVersion);
        $this->_modifyResourceDb(self::TYPE_DATA_UPGRADE, $oldVersion, $newVersion);
        $this->_getResource()->setDataVersion($this->_resourceName, $newVersion);

        return $this;
    }

    /**
     * Run data upgrade scripts
     *
     * @param string $oldVersion
     * @param string $newVersion
     * @return $this
     */
    protected function _upgradeData($oldVersion, $newVersion)
    {
        $this->_modifyResourceDb('data-upgrade', $oldVersion, $newVersion);
        $this->_getResource()->setDataVersion($this->_resourceName, $newVersion);

        return $this;
    }

    /**
     * Run resource installation file
     *
     * @param string $newVersion
     * @return $this
     */
    protected function _installResourceDb($newVersion)
    {
        $oldVersion = $this->_modifyResourceDb(self::TYPE_DB_INSTALL, '', $newVersion);
        $this->_modifyResourceDb(self::TYPE_DB_UPGRADE, $oldVersion, $newVersion);
        $this->_getResource()->setDbVersion($this->_resourceName, $newVersion);

        return $this;
    }

    /**
     * Run resource upgrade files from $oldVersion to $newVersion
     *
     * @param string $oldVersion
     * @param string $newVersion
     * @return $this
     */
    protected function _upgradeResourceDb($oldVersion, $newVersion)
    {
        $this->_modifyResourceDb(self::TYPE_DB_UPGRADE, $oldVersion, $newVersion);
        $this->_getResource()->setDbVersion($this->_resourceName, $newVersion);

        return $this;
    }

    /**
     * Roll back resource
     *
     * @param string $newVersion
     * @param string $oldVersion
     * @return $this
     */
    protected function _rollbackResourceDb($newVersion, $oldVersion)
    {
        $this->_modifyResourceDb(self::TYPE_DB_ROLLBACK, $newVersion, $oldVersion);
        return $this;
    }

    /**
     * Uninstall resource
     *
     * @param string $version existing resource version
     * @return $this
     */
    protected function _uninstallResourceDb($version)
    {
        $this->_modifyResourceDb(self::TYPE_DB_UNINSTALL, $version, '');
        return $this;
    }

    /**
     * Get the database engine identifier from connection config
     *
     * Supports new 'engine' config with backward compatibility for legacy 'model' config.
     */
    protected function _getConnectionEngine(): string
    {
        // New config: use 'engine'
        if (!empty($this->_connectionConfig->engine)) {
            return (string) $this->_connectionConfig->engine;
        }

        // Backward compatibility: use 'model' (legacy name for engine)
        if (!empty($this->_connectionConfig->model)) {
            return (string) $this->_connectionConfig->model;
        }

        // Default to MySQL for safety
        return 'mysql';
    }

    /**
     * Retrieve available Database install/upgrade files for current module
     *
     * @param string $actionType
     * @param string $fromVersion
     * @param string $toVersion
     * @return array
     */
    protected function _getAvailableDbFiles($actionType, $fromVersion, $toVersion)
    {
        $resModel   = $this->_getConnectionEngine();
        $modName    = (string) $this->_moduleConfig[0]->getName();

        // Backwards compatibility: also match mysql4- prefix when model is mysql
        $modelPattern = $resModel === 'mysql' ? 'mysql4?' : preg_quote($resModel, '#');

        $dbFiles    = [];
        $typeFiles  = [];
        $regExpDb   = sprintf('#^%s-(.*)\.(php|sql)$#i', $actionType);
        $regExpType = sprintf('#^%s-%s-(.*)\.(php|sql)$#i', $modelPattern, $actionType);

        $filesDir   = Mage::getModuleDir('sql', $modName) . DS . $this->_resourceName;
        foreach (Maho::globPackages("$filesDir/*") as $file) {
            $matches = [];
            if (preg_match($regExpDb, basename($file), $matches) === 1) {
                $dbFiles[$matches[1]] = $file;
            } elseif (preg_match($regExpType, basename($file), $matches) === 1) {
                $typeFiles[$matches[1]] = $file;
            }
        }

        if (empty($typeFiles) && empty($dbFiles)) {
            return [];
        }

        foreach ($typeFiles as $version => $file) {
            $dbFiles[$version] = $file;
        }

        return $this->_getModifySqlFiles($actionType, $fromVersion, $toVersion, $dbFiles);
    }

    /**
     * Retrieve available Data install/upgrade files for current module
     *
     * @param string $actionType
     * @param string $fromVersion
     * @param string $toVersion
     * @return array
     */
    protected function _getAvailableDataFiles($actionType, $fromVersion, $toVersion)
    {
        $modName    = (string) $this->_moduleConfig[0]->getName();
        $resModel   = $this->_getConnectionEngine();
        $files      = [];

        // Backwards compatibility: also match mysql4- prefix when model is mysql
        $modelPattern = $resModel === 'mysql' ? 'mysql4?' : preg_quote($resModel, '#');

        $regExp     = sprintf('#^%s-(.*)\.php$#i', $actionType);
        $regExpOld  = sprintf('#^%s-%s-(.*)\.php$#i', $modelPattern, $actionType);

        $filesDir   = Mage::getModuleDir('data', $modName) . DS . $this->_resourceName;
        foreach (Maho::globPackages("$filesDir/*") as $file) {
            $matches = [];
            if (preg_match($regExp, basename($file), $matches) === 1) {
                $files[$matches[1]] = $file;
            }
        }

        // search data files in old location
        $filesDir   = Mage::getModuleDir('sql', $modName) . DS . $this->_resourceName;
        foreach (Maho::globPackages("$filesDir/*") as $file) {
            $matches = [];
            if (preg_match($regExpOld, basename($file), $matches) === 1) {
                $files[$matches[1]] = $file;
            }
        }

        if (empty($files)) {
            return [];
        }

        return $this->_getModifySqlFiles($actionType, $fromVersion, $toVersion, $files);
    }

    /**
     * Save resource version
     */
    protected function _setResourceVersion(string $actionType, string $version): self
    {
        match ($actionType) {
            self::TYPE_DB_INSTALL, self::TYPE_DB_UPGRADE => $this->_getResource()->setDbVersion($this->_resourceName, $version),
            self::TYPE_DATA_INSTALL, self::TYPE_DATA_UPGRADE => $this->_getResource()->setDataVersion($this->_resourceName, $version),
            default => $this,
        };

        return $this;
    }

    /**
     * Run module modification files. Return version of last applied upgrade (false if no upgrades applied)
     *
     * @param string $actionType self::TYPE_*
     * @param string $fromVersion
     * @param string $toVersion
     * @return string|false
     * @throws Mage_Core_Exception
     */

    protected function _modifyResourceDb($actionType, $fromVersion, $toVersion)
    {
        $files = match ($actionType) {
            self::TYPE_DB_INSTALL, self::TYPE_DB_UPGRADE => $this->_getAvailableDbFiles($actionType, $fromVersion, $toVersion),
            self::TYPE_DATA_INSTALL, self::TYPE_DATA_UPGRADE => $this->_getAvailableDataFiles($actionType, $fromVersion, $toVersion),
            default => [],
        };
        if (empty($files) || !$this->getConnection()) {
            return false;
        }

        $version = false;

        foreach ($files as $file) {
            $fileName = $file['fileName'];
            $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
            $this->getConnection()->disallowDdlCache();
            try {
                switch ($fileType) {
                    case 'php':
                        $conn   = $this->getConnection();
                        $result = include $fileName;
                        break;
                    case 'sql':
                        $sql = file_get_contents($fileName);
                        if (!empty($sql)) {
                            $result = $this->run($sql);
                        } else {
                            $result = true;
                        }
                        break;
                    default:
                        $result = false;
                        break;
                }

                if ($result) {
                    $this->_setResourceVersion($actionType, $file['toVersion']);
                }
            } catch (Exception $e) {
                throw Mage::exception('Mage_Core', Mage::helper('core')->__('Error in file: "%s" - %s', $fileName, $e->getMessage()));
            }
            $version = $file['toVersion'];
            $this->getConnection()->allowDdlCache();
        }
        self::$_hadUpdates = true;
        return $version;
    }

    /**
     * Get data files for modifications
     *
     * @param string $actionType
     * @param string $fromVersion
     * @param string $toVersion
     * @param array $arrFiles
     * @return array
     */
    protected function _getModifySqlFiles($actionType, $fromVersion, $toVersion, $arrFiles)
    {
        $arrRes = [];
        switch ($actionType) {
            case self::TYPE_DB_INSTALL:
            case self::TYPE_DATA_INSTALL:
                uksort($arrFiles, 'version_compare');
                foreach ($arrFiles as $version => $file) {
                    if (version_compare($version, $toVersion) !== self::VERSION_COMPARE_GREATER) {
                        $arrRes[0] = [
                            'toVersion' => $version,
                            'fileName'  => $file,
                        ];
                    }
                }
                break;

            case self::TYPE_DB_UPGRADE:
            case self::TYPE_DATA_UPGRADE:
                uksort($arrFiles, 'version_compare');
                foreach ($arrFiles as $version => $file) {
                    $versionInfo = explode('-', $version);

                    // In array must be 2 elements: 0 => version from, 1 => version to
                    if (count($versionInfo) != 2) {
                        continue;
                    }
                    $infoFrom = $versionInfo[0];
                    $infoTo   = $versionInfo[1];
                    if (version_compare($infoFrom, $fromVersion) !== self::VERSION_COMPARE_LOWER
                        && version_compare($infoTo, $toVersion) !== self::VERSION_COMPARE_GREATER
                    ) {
                        $arrRes[] = [
                            'toVersion' => $infoTo,
                            'fileName'  => $file,
                        ];
                    }
                }
                break;

            case self::TYPE_DB_ROLLBACK:
            case self::TYPE_DB_UNINSTALL:
                break;
        }
        return $arrRes;
    }

    /**
     * Retrieve row or field from table by id or string and parent id
     *
     * @param string $table
     * @param string $idField
     * @param string|int $id
     * @param string $field
     * @param string $parentField
     * @param string|int $parentId
     * @return mixed|boolean
     */
    public function getTableRow($table, $idField, $id, $field = null, $parentField = null, $parentId = 0)
    {
        if (str_contains($table, '/')) {
            $table = $this->getTable($table);
        }

        if (empty($this->_setupCache[$table][$parentId][$id])) {
            $adapter = $this->getConnection();
            $bind    = ['id_field' => $id];
            $select  = $adapter->select()
                ->from($table)
                ->where($adapter->quoteIdentifier($idField) . '= :id_field');
            if (!is_null($parentField)) {
                $select->where($adapter->quoteIdentifier($parentField) . '= :parent_id');
                $bind['parent_id'] = $parentId;
            }
            $this->_setupCache[$table][$parentId][$id] = $adapter->fetchRow($select, $bind);
        }

        if (is_null($field)) {
            return $this->_setupCache[$table][$parentId][$id];
        }
        return $this->_setupCache[$table][$parentId][$id][$field] ?? false;
    }

    /**
     * Delete table row
     *
     * @param string $table
     * @param string $idField
     * @param int|string $id
     * @param null|string $parentField
     * @param int|string $parentId
     * @return $this
     */
    public function deleteTableRow($table, $idField, $id, $parentField = null, $parentId = 0)
    {
        if (str_contains($table, '/')) {
            $table = $this->getTable($table);
        }

        $adapter = $this->getConnection();
        $where = [$adapter->quoteIdentifier($idField) . '=?' => $id];
        if (!is_null($parentField)) {
            $where[$adapter->quoteIdentifier($parentField) . '=?'] = $parentId;
        }

        $adapter->delete($table, $where);

        if (isset($this->_setupCache[$table][$parentId][$id])) {
            unset($this->_setupCache[$table][$parentId][$id]);
        }

        return $this;
    }

    /**
     * Update one or more fields of table row
     *
     * @param string $table
     * @param string $idField
     * @param string|int $id
     * @param string|array $field
     * @param mixed|null $value
     * @param string $parentField
     * @param string|int $parentId
     * @return $this
     */
    public function updateTableRow($table, $idField, $id, $field, $value = null, $parentField = null, $parentId = 0)
    {
        if (str_contains($table, '/')) {
            $table = $this->getTable($table);
        }

        if (is_array($field)) {
            $data = $field;
        } else {
            $data = [$field => $value];
        }

        $adapter = $this->getConnection();
        $where = [$adapter->quoteIdentifier($idField) . '=?' => $id];
        $adapter->update($table, $data, $where);

        if (isset($this->_setupCache[$table][$parentId][$id])) {
            if (is_array($field)) {
                $this->_setupCache[$table][$parentId][$id] =
                    array_merge($this->_setupCache[$table][$parentId][$id], $field);
            } else {
                $this->_setupCache[$table][$parentId][$id][$field] = $value;
            }
        }

        return $this;
    }

    /**
     * Check is table exists
     *
     * @param string $table
     * @return bool
     */
    public function tableExists($table)
    {
        if (str_contains($table, '/')) {
            $table = $this->getTable($table);
        }

        return $this->getConnection()->isTableExists($table);
    }

    /**
     * Save configuration data
     *
     * @param string $path
     * @param string $value
     * @param int|string $scope
     * @param int $scopeId
     * @param int $inherit
     * @return $this
     */
    public function setConfigData($path, $value, $scope = 'default', $scopeId = 0, $inherit = 0)
    {
        $table = $this->getTable('core/config_data');
        // this is a fix for mysql 4.1
        $this->getConnection()->showTableStatus($table);

        $data  = [
            'scope'     => $scope,
            'scope_id'  => $scopeId,
            'path'      => $path,
            'value'     => $value,
        ];
        $this->getConnection()->insertOnDuplicate($table, $data, ['value']);
        return $this;
    }

    /**
     * Delete config field values
     *
     * @param string $path
     * @param string $scope (default|stores|websites|config)
     * @return $this
     */
    public function deleteConfigData($path, $scope = null)
    {
        $where = ['path = ?' => $path];
        if (!is_null($scope)) {
            $where['scope = ?'] = $scope;
        }
        $this->getConnection()->delete($this->getTable('core/config_data'), $where);
        return $this;
    }

    /**
     * Run plain SQL query(ies)
     *
     * @param string $sql
     * @return $this
     */
    public function run($sql)
    {
        $this->getConnection()->multiQuery($sql);
        return $this;
    }

    /**
     * Prepare database before install/upgrade
     *
     * @return $this
     */
    public function startSetup()
    {
        $this->getConnection()->startSetup();
        return $this;
    }

    /**
     * Prepare database after install/upgrade
     *
     * @return $this
     */
    public function endSetup()
    {
        $this->getConnection()->endSetup();
        return $this;
    }

    /**
     * Retrieve 32bit UNIQUE HASH for a Table index
     *
     * @param array|string $tableName
     * @param array|string $fields
     * @param string $indexType
     * @return string
     */
    public function getIdxName($tableName, $fields, $indexType = '')
    {
        return Mage::getSingleton('core/resource')->getIdxName($tableName, $fields, $indexType);
    }

    /**
     * Retrieve 32bit UNIQUE HASH for a Table foreign key
     *
     * @param array|string $priTableName  the target table name
     * @param string $priColumnName the target table column name
     * @param string $refTableName  the reference table name
     * @param string $refColumnName the reference table column name
     * @return string
     */
    public function getFkName($priTableName, $priColumnName, $refTableName, $refColumnName)
    {
        return Mage::getSingleton('core/resource')
            ->getFkName($priTableName, $priColumnName, $refTableName, $refColumnName);
    }

    /**
     * Check call afterApplyAllUpdates method for setup class
     *
     * @return bool
     */
    public function getCallAfterApplyAllUpdates()
    {
        return $this->_callAfterApplyAllUpdates;
    }

    /**
     * Run each time after applying of all updates,
     * if setup model set $_callAfterApplyAllUpdates flag to true
     *
     * @return $this
     */
    public function afterApplyAllUpdates()
    {
        return $this;
    }
}
