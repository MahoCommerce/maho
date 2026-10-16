<?php

/**
 * Starts the creation of the resized files when the image settings of a store view change.
 *
 * The settings come from the theme and from the store config. A config save checks them at
 * once. A theme changes with a deploy, which fires no event, so a cron job checks them too.
 * The observer also deletes the stored size of each image that a mount changes.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_Observer
{
    /** How long a queued creation blocks the next one for the same settings, in seconds. */
    public const QUEUED_LIFETIME = 86400;

    #[Maho\Config\Observer('admin_system_config_changed_section_catalog', id: 'image_settings_check_catalog')]
    #[Maho\Config\Observer('admin_system_config_changed_section_design', id: 'image_settings_check_design')]
    #[Maho\Config\Observer('admin_system_config_changed_section_system', id: 'image_settings_check_system')]
    public function checkAfterConfigSave(\Maho\Event\Observer $observer): void
    {
        Mage::getSingleton('core/image_config')->reset();
        $this->queueNewSettings();
    }

    /**
     * Queue the creation of the files of each entity whose settings miss a folder on the media
     * mount. The same missing folders are queued once a day at most, so a long run is not queued
     * again while it works.
     */
    #[Maho\Config\CronJob('image_settings_check', schedule: '*/15 * * * *')]
    public function queueNewSettings(): void
    {
        $config = Mage::getSingleton('core/image_config');
        $resizer = Mage::getSingleton('core/image_resizer');
        foreach (array_keys($config->getEntities()) as $entity) {
            $folders = $resizer->getMissingFolders($entity);
            if ($folders === []) {
                continue;
            }

            $key = md5($entity . ',' . implode(',', $folders));
            $cacheId = 'image_settings_queued_' . $key;
            if (Mage::app()->loadCache($cacheId)) {
                continue;
            }
            Mage::app()->saveCache('1', $cacheId, [Mage_Core_Model_Image_Config::CACHE_TAG], self::QUEUED_LIFETIME);
            // A config save cleans the cache marker, so the queue must also refuse a second message while one runs
            $resizer->queueAll($entity, 'image_resize_all_' . $key);
        }
    }

    /**
     * Delete the stored size of each image that a mount operation changed. The next page or the
     * resizer stores the new size.
     */
    #[Maho\Config\Observer('storage_mount_change', id: 'image_size_forget')]
    public function forgetSizes(\Maho\Event\Observer $observer): void
    {
        $event = $observer->getEvent();
        $mount = $event->getData('mount');
        // Before the install, no table holds sizes, and there can be no database at all
        if ($mount instanceof \Maho\Storage\Mount && Mage::isInstalled()) {
            Mage::getSingleton('core/image_size')->forget($mount->name(), (array) $event->getData('paths'), (array) $event->getData('folders'));
        }
    }
}
