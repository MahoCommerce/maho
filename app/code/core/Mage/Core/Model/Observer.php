<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Observer
{
    /**
     * Cron job method to clean old cache resources
     */
    #[Maho\Config\CronJob('core_clean_cache', configPath: 'system/cache/flush_cron_expr')]
    public function cleanCache(Mage_Cron_Model_Schedule $schedule)
    {
        Mage::app()->getCache()->prune();
        Mage::dispatchEvent('core_clean_cache');
    }

    /**
     * Cleans cache by tags
     *
     * @return $this
     */
    #[Maho\Config\Observer('clean_cache_by_tags', area: 'adminhtml')]
    #[Maho\Config\Observer('admin_system_config_changed_section_catalog', area: 'adminhtml')]
    public function cleanCacheByTags(\Maho\Event\Observer $observer)
    {
        /** @var array $tags */
        $tags = $observer->getEvent()->getTags();
        if (empty($tags)) {
            Mage::app()->cleanCache();
            return $this;
        }

        Mage::app()->cleanCache($tags);
        return $this;
    }

    /**
     * Clean up old minified CSS/JS files (cron job)
     */
    #[Maho\Config\CronJob('core_minify_cleanup', schedule: '0 4 * * *')]
    public function cleanOldMinifiedFiles(Mage_Cron_Model_Schedule $schedule): void
    {
        Mage::helper('core/minify')->cleanupOldVersions();
    }

    /**
     * Checks method availability for processing in variable
     *
     * @throws Exception
     * @return Mage_Core_Model_Observer
     */
    #[Maho\Config\Observer('model_save_before')]
    #[Maho\Config\Observer('model_delete_before')]
    public function secureVarProcessing(\Maho\Event\Observer $observer)
    {
        if (Mage::registry('varProcessing')) {
            Mage::throwException(Mage::helper('core')->__('Disallowed template variable method.'));
        }
        return $this;
    }
}
