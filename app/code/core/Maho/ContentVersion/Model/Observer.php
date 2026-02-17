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

class Maho_ContentVersion_Model_Observer
{
    /**
     * Registry key for API callers to set editor attribution.
     * Set via Mage::register() before save to attribute the version.
     */
    public const REGISTRY_EDITOR = 'contentversion_editor';

    public function beforeCmsPageSave(Maho\Event\Observer $observer): void
    {
        $this->createVersionFromEvent($observer, 'cms_page');
    }

    public function beforeCmsBlockSave(Maho\Event\Observer $observer): void
    {
        $this->createVersionFromEvent($observer, 'cms_block');
    }

    public function beforeBlogPostSave(Maho\Event\Observer $observer): void
    {
        $this->createVersionFromEvent($observer, 'blog_post');
    }

    private function createVersionFromEvent(Maho\Event\Observer $observer, string $entityType): void
    {
        /** @var Mage_Core_Model_Abstract $object */
        $object = $observer->getEvent()->getData('object');

        // Only version existing entities (updates, not creates)
        if (!$object->getId() || $object->isObjectNew()) {
            return;
        }

        try {
            Mage::helper('contentversion')
                ->createVersion($object, $entityType, $this->detectEditor());
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    private function detectEditor(): string
    {
        // Check explicit registry hint (set by callers who want custom attribution)
        $registryEditor = Mage::registry(self::REGISTRY_EDITOR);
        if ($registryEditor !== null) {
            return (string) $registryEditor;
        }

        // Check for API user (set by API Platform authenticator)
        $apiUser = Mage::registry('current_api_user');
        if (is_array($apiUser) && !empty($apiUser['name'])) {
            return 'API: ' . $apiUser['name'];
        }

        // Check for admin session
        try {
            $adminSession = Mage::getSingleton('admin/session');
            if ($adminSession->isLoggedIn()) {
                $admin = $adminSession->getUser();
                return 'Admin: ' . $admin->getUsername();
            }
        } catch (\Exception) {
            // No admin session available
        }

        return 'System';
    }
}
