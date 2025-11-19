<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        return ($formKey === $this->getFormKey());
    }

    public function getOrderIds(bool $clear = false): array
    {
        return $this->getData('order_ids', $clear) ?? [];
    }

    /**
     * Clean expired sessions from filesystem (Redis does it automatically)
     */
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

        $sessionSavePath = (string) Mage::getConfig()->getNode('global/session_save_path') ?: Mage::getBaseDir('var') . DS . 'session';
        if (!is_dir($sessionSavePath) || !is_readable($sessionSavePath)) {
            Mage::log("Session cleanup skipped: directory not accessible: {$sessionSavePath}", Mage::LOG_WARNING);
            return;
        }

        $maxIdleTime = max(
            (int) Mage::getStoreConfig('admin/security/session_cookie_lifetime'),
            (int) Mage::getStoreConfig('web/cookie/cookie_lifetime'),
            86400,
        );

        $deletedCount = 0;
        $processedCount = 0;
        foreach (new DirectoryIterator($sessionSavePath) as $file) {
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

        Mage::log("Session cleanup: processed {$processedCount} files, deleted {$deletedCount} expired filesystem sessions", Mage::LOG_INFO);
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

        } catch (Exception $e) {
            // If we can't get file modification time, consider it expired
            return true;
        }
    }
}
