<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
            Mage::log('Session cleanup failed: ' . $e->getMessage(), Zend_Log::ERR);
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

        $sessionHandler = new \Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler($sessionSavePath);
        $maxIdleTime = $this->_getDefaultSessionLifetime();
        $deletedCount = 0;

        foreach (new DirectoryIterator($sessionSavePath) as $file) {
            if ($file->isFile() && str_starts_with($file->getFilename(), 'sess_')) {
                $sessionId = substr($file->getFilename(), 5);

                if ($this->_isSessionExpired($sessionId, $sessionHandler, $maxIdleTime)) {
                    if (unlink($file->getPathname())) {
                        $deletedCount++;
                    }
                }
            }
        }

        Mage::log("Session cleanup: deleted {$deletedCount} expired filesystem sessions", Zend_Log::INFO);
    }

    /**
     * Check if a session is expired using Symfony's native capabilities
     */
    protected function _isSessionExpired(string $sessionId, \SessionHandlerInterface $sessionHandler, int $maxIdleTime): bool
    {
        try {
            // Create a temporary session with default MetadataBag
            $storage = new \Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage(
                [
                    'cache_limiter' => '',
                    'use_cookies' => false,
                    'cookie_lifetime' => $maxIdleTime,
                ],
                $sessionHandler,
                // Use Symfony's default MetadataBag
            );

            $session = new \Symfony\Component\HttpFoundation\Session\Session($storage);
            $session->setId($sessionId);
            $session->start();

            $metadataBag = $session->getMetadataBag();

            // Symfony handles all expiration logic automatically now
            return time() - $metadataBag->getLastUsed() > $maxIdleTime;

        } catch (Exception $e) {
            // If we can't read session properly, consider it expired
            return true;
        }
    }

    /**
     * Get default session lifetime from configuration
     */
    protected function _getDefaultSessionLifetime(): int
    {
        $adminLifetime = (int) Mage::getStoreConfig('admin/security/session_cookie_lifetime');
        $frontendLifetime = (int) Mage::getStoreConfig('web/cookie/cookie_lifetime');
        return max($adminLifetime, $frontendLifetime, 86400);
    }
}
