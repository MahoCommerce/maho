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

    private const ENTITY_TYPE_MAP = [
        'Mage_Cms_Model_Page' => 'cms_page',
        'Mage_Cms_Model_Block' => 'cms_block',
        'Maho_Blog_Model_Post' => 'blog_post',
    ];

    public function beforeSave(Maho\Event\Observer $observer): void
    {
        /** @var Mage_Core_Model_Abstract $object */
        $object = $observer->getEvent()->getData('object');

        $entityType = $this->getEntityType($object);
        if ($entityType === null) {
            return;
        }

        // Only version existing entities (updates, not creates)
        if (!$object->getId() || $object->isObjectNew()) {
            return;
        }

        try {
            Mage::getSingleton('contentversion/service')
                ->createVersion($object, $entityType, $this->detectEditor());
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    private function getEntityType(Mage_Core_Model_Abstract $object): ?string
    {
        foreach (self::ENTITY_TYPE_MAP as $class => $type) {
            if (class_exists($class) && $object instanceof $class) {
                return $type;
            }
        }
        return null;
    }

    private function detectEditor(): string
    {
        // Check registry hint (set by API processors or other callers)
        $registryEditor = Mage::registry(self::REGISTRY_EDITOR);
        if ($registryEditor !== null) {
            return (string) $registryEditor;
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
