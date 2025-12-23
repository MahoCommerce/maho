<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Installer_Filesystem extends Mage_Install_Model_Installer_Abstract
{
    public function __construct() {}

    /**
     * Check and prepare file system
     */
    public function install(): self
    {
        if (!$this->_checkFilesystem()) {
            throw new Exception();
        }
        return $this;
    }

    /**
     * Check file system by config
     *
     * @return bool
     */
    protected function _checkFilesystem()
    {
        $res = true;
        $config = Mage::getSingleton('install/config')->getWritableFullPathsForCheck();

        if (is_array($config)) {
            foreach ($config as $item) {
                $recursive = isset($item['recursive']) ? (bool) $item['recursive'] : false;
                $existence = isset($item['existence']) ? (bool) $item['existence'] : false;
                $checkRes = $this->_checkFullPath($item['path'], $recursive, $existence);
                $res = $res && $checkRes;
            }
        }
        return $res;
    }

    /**
     * Check file system full path
     *
     * @param  string $fullPath
     * @param  bool $recursive
     * @param  bool $existence
     * @return bool
     */
    protected function _checkFullPath($fullPath, $recursive, $existence)
    {
        $res = true;
        $setError = $existence && (is_dir($fullPath) && !isDirWriteable($fullPath) || !is_writable($fullPath))
            || !$existence && file_exists($fullPath) && !is_writable($fullPath);

        if ($setError) {
            $this->_getInstaller()->getDataModel()->addError(
                Mage::helper('install')->__('Path "%s" must be writable.', $fullPath),
            );
            $res = false;
        }

        if ($recursive && is_dir($fullPath)) {
            $skipFileNames = ['.svn', '.htaccess'];
            foreach (new DirectoryIterator($fullPath) as $file) {
                $fileName = $file->getFilename();
                if (!$file->isDot() && !in_array($fileName, $skipFileNames)) {
                    $res = $this->_checkFullPath($fullPath . DS . $fileName, $recursive, $existence) && $res;
                }
            }
        }
        return $res;
    }
}
