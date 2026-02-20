<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Db extends Mage_Install_Model_Installer_Abstract
{
    /**
     * @var Mage_Install_Model_Installer_Db_Abstract|null database
     */
    protected ?Mage_Install_Model_Installer_Db_Abstract $_dbResource = null;

    /**
     * Check database connection
     * and return checked connection data
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function checkDbConnectionData(array $data): array
    {
        $data = $this->_getCheckedData($data);

        try {
            $dbEngine = $data['db_engine'];
            $resource = $this->_getDbResource($dbEngine);
            $resource->setConfig($data);

            // check required extensions
            $absenteeExtensions = [];
            $extensions = $resource->getRequiredExtensions();
            foreach ($extensions as $extName) {
                if (!extension_loaded($extName)) {
                    $absenteeExtensions[] = $extName;
                }
            }
            if (!empty($absenteeExtensions)) {
                Mage::throwException(
                    Mage::helper('install')->__('PHP Extensions "%s" must be loaded.', implode(',', $absenteeExtensions)),
                );
            }

            // check InnoDB support (MySQL-specific, PostgreSQL always returns true)
            if (!$resource->supportEngine()) {
                Mage::throwException(
                    Mage::helper('install')->__('Database server does not support the InnoDB storage engine.'),
                );
            }

            // TODO: check user roles
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::throwException(Mage::helper('install')->__($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException(Mage::helper('install')->__('Database connection error.'));
        }

        return $data;
    }

    /**
     * Check database connection data
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function _getCheckedData(array $data): array
    {
        if (!isset($data['db_name']) || empty($data['db_name'])) {
            Mage::throwException(Mage::helper('install')->__('Database Name cannot be empty.'));
        }
        //make all table prefix to lower letter
        if ($data['db_prefix'] != '') {
            $data['db_prefix'] = strtolower($data['db_prefix']);
        }
        //check table prefix
        if ($data['db_prefix'] != '') {
            if (!preg_match('/^[a-z]+[a-z0-9_]*$/', $data['db_prefix'])) {
                Mage::throwException(
                    Mage::helper('install')->__('The table prefix should contain only letters (a-z), numbers (0-9) or underscores (_), the first character should be a letter.'),
                );
            }
        }

        // Set default db engine
        if (empty($data['db_engine'])) {
            $data['db_engine'] = 'mysql';
        }

        if (!isset($data['db_init_statemants'])) {
            $data['db_init_statemants'] = (string) Mage::getConfig()
                ->getNode(sprintf('install/databases/%s/initStatements', $data['db_engine']));
        }

        return $data;
    }

    /**
     * Retrieve the database resource
     *
     * @param  string $engine database engine (mysql, pgsql)
     * @throws Mage_Core_Exception
     */
    protected function _getDbResource(string $engine): Mage_Install_Model_Installer_Db_Abstract
    {
        if (!isset($this->_dbResource)) {
            $resource = Mage::getSingleton(sprintf('install/installer_db_%s', $engine));
            if (!$resource) {
                Mage::throwException(
                    Mage::helper('install')->__('Installer does not exist for %s database engine', $engine),
                );
            }
            assert($resource instanceof \Mage_Install_Model_Installer_Db_Abstract);
            $this->_dbResource = $resource;
        }
        return $this->_dbResource;
    }
}
