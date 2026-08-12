<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * @method null|bool getCookieShouldBeReceived()
 * @method $this setCookieShouldBeReceived(bool $value)
 * @method $this unsCookieShouldBeReceived()
 * @method $this unsSessionHosts()
 * @method string getCurrencyCode()
 * @method $this setCurrencyCode(string $value)
 * @method $this setFormData(array $value)
 * @method $this setOrderIds(array $value)
 * @method $this setLastUrl(string $value)
 */
class Mage_Core_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * @param array $data
     */
    public function __construct($data = [])
    {
        $name = $data['name'] ?? null;
        $this->init('core', $name);
    }

    /**
     * Retrieve Session Form Key
     *
     * @return string A 16 bit unique key for forms
     */
    public function getFormKey()
    {
        if (!$this->getData('_form_key')) {
            $this->renewFormKey();
        }
        return $this->getData('_form_key');
    }

    /**
     * Creates new Form key
     */
    public function renewFormKey()
    {
        $this->setData('_form_key', Mage::helper('core')->getRandomString(16));
    }

    /**
     * Validates Form key
     *
     * @param string|null $formKey
     * @return bool
     */
    public function validateFormKey($formKey)
    {
        return is_string($formKey) && hash_equals($this->getFormKey(), $formKey);
    }

    public function getOrderIds(bool $clear = false): array
    {
        return $this->getData('order_ids', $clear) ?? [];
    }

    /**
     * Clean expired sessions from filesystem (Redis does it automatically)
     */
    #[Maho\Config\CronJob('core_session_clean', schedule: '30 3 * * *')]
    public function cleanExpiredSessions(): void
    {
        try {
            $this->_cleanFileSystemSessions();
        } catch (Exception $e) {
            Mage::log('Session cleanup failed: ' . $e->getMessage(), Mage::LOG_ERROR);
            throw $e;
        }
    }

    protected function _cleanFileSystemSessions(): void
    {
        $sessionSaveMethod = $this->getSessionSaveMethod();
        if ($sessionSaveMethod !== 'files') {
            return;
        }

        $sessionSavePath = $this->getSessionSavePath();
        if (!is_dir($sessionSavePath) || !is_readable($sessionSavePath)) {
            Mage::log("Session cleanup skipped: directory not accessible: {$sessionSavePath}", Mage::LOG_WARNING);
            return;
        }

        $maxIdleTime = $this->_getFileSessionMaxIdleTime();

        $deletedCount = 0;
        $processedCount = 0;
        foreach ($this->_getSessionSaveDirs($sessionSavePath) as $directory) {
            foreach (new DirectoryIterator($directory) as $file) {
                if (!$file->isFile() || !str_starts_with($file->getFilename(), 'sess_')) {
                    continue;
                }

                $processedCount++;
                if ($this->_isFileSessionExpired($file, $maxIdleTime)) {
                    if (unlink($file->getPathname())) {
                        $deletedCount++;
                    }
                }
            }
        }

        Mage::log("Session cleanup: processed {$processedCount} files, deleted {$deletedCount} expired filesystem sessions", Mage::LOG_INFO);
    }

    /**
     * @return string[] the save path plus the subdirectory each non-storefront session name keeps
     *                  its records in
     */
    protected function _getSessionSaveDirs(string $savePath): array
    {
        $dirs = [$savePath];
        foreach (new DirectoryIterator($savePath) as $entry) {
            if ($entry->isDir() && !$entry->isDot() && $entry->isReadable()) {
                $dirs[] = $entry->getPathname();
            }
        }

        return $dirs;
    }

    /**
     * Read-time expiry enforces the policy, so this only reclaims disk and must never undercut it:
     * the lifetimes are per store view, while this runs in a single scope.
     */
    protected function _getFileSessionMaxIdleTime(): int
    {
        $maxIdleTime = self::getLongestConfiguredSessionLifetime();
        foreach (Mage::app()->getStores() as $store) {
            $maxIdleTime = max($maxIdleTime, self::getLongestConfiguredSessionLifetime($store));
        }

        return $maxIdleTime;
    }

    /**
     * Check if a session file is expired based on file modification time
     */
    protected function _isFileSessionExpired(\DirectoryIterator $file, int $maxIdleTime): bool
    {
        try {
            $expireTime = time() - $maxIdleTime;

            // For filesystem sessions, file modification time is the most reliable indicator
            // PHP updates the file mtime every time the session is accessed/written
            return $file->getMTime() < $expireTime;

        } catch (Exception) {
            // If we can't get file modification time, consider it expired
            return true;
        }
    }
}
