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

class Mage_Install_Model_Installer_Abstract
{
    /**
     * Installer singleton
     */
    protected ?Mage_Install_Model_Installer $_installer = null;

    /**
     * Get installer singleton
     */
    protected function _getInstaller(): Mage_Install_Model_Installer
    {
        if (is_null($this->_installer)) {
            $this->_installer = Mage::getSingleton('install/installer');
        }
        return $this->_installer;
    }

    /**
     * Validate session storage value (files or db)
     * If empty, will return 'files'
     *
     * @throws Exception
     */
    protected function _checkSessionSave(string $value): string
    {
        if (empty($value)) {
            return 'files';
        }
        if (!in_array($value, ['files', 'db'], true)) {
            throw new Exception('session_save value must be "files" or "db".');
        }
        return $value;
    }

    /**
     * Validate admin frontname value.
     * If empty, "admin" will be returned
     *
     * @throws Exception
     */
    protected function _checkAdminFrontname(string $value): string
    {
        if (empty($value)) {
            return 'admin';
        }
        if (!preg_match('/^[a-z]+[a-z0-9_]+$/i', $value)) {
            throw new Exception('admin_frontname value must contain only letters (a-z or A-Z), numbers (0-9) or underscore(_), first character should be a letter.');
        }
        return $value;
    }
}
