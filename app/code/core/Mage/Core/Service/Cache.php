<?php

/**
 * Enable, disable, refresh and flush the cache types of System > Cache Management, for the admin, the API and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Cache
{
    /**
     * @return array<string, \Maho\DataObject> each declared cache type by its code, with its status
     */
    public function getTypes(): array
    {
        return Mage::app()->getCache()->getTypes();
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getByCode(string $code): \Maho\DataObject
    {
        return $this->getTypes()[$code]
            ?? throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__("Cache type '%s' not found", $code));
    }

    public function isInvalidated(string $code): bool
    {
        return isset(Mage::app()->getCache()->getInvalidatedTypes()[$code]);
    }

    /**
     * Enable each type in $codes. Return the number of types that were disabled before.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_NoSuchEntity when a code is not a declared cache type
     */
    public function enable(array $codes): int
    {
        return $this->saveEnabled($codes, true);
    }

    /**
     * Disable each type in $codes and remove its stored data. Return the number of types that were enabled before.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_NoSuchEntity when a code is not a declared cache type
     */
    public function disable(array $codes): int
    {
        $changed = $this->saveEnabled($codes, false);
        foreach ($codes as $code) {
            Mage::app()->getCache()->cleanType($code);
        }
        return $changed;
    }

    /**
     * Remove the stored data of each type in $codes and clear its invalidated state. Return the number of types.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_NoSuchEntity when a code is not a declared cache type
     */
    public function refresh(array $codes): int
    {
        $this->assertKnown($codes);
        foreach ($codes as $code) {
            Mage::app()->getCache()->cleanType($code);
            Mage::dispatchEvent('adminhtml_cache_refresh_type', ['type' => $code]);
        }
        return count($codes);
    }

    /**
     * Remove the stored data of every cache type at once.
     */
    public function flush(): void
    {
        Mage::app()->getCache()->flush();
        Mage::dispatchEvent('adminhtml_cache_flush_all');
    }

    /**
     * Clean the cache, call $applySchema, run the pending setup scripts, and save the configuration cache again.
     * The configuration cache stays off until the scripts ran, so they read the configuration of the files.
     *
     * @param (Closure(): void)|null $applySchema the declarative schema step of `./maho migrate`
     * @return array{schema: int, data: int} the number of setup scripts that ran, by type
     */
    public function flushSystem(?Closure $applySchema = null): array
    {
        $cache = Mage::app()->getCache();
        $cache->banUse('config');
        Mage::getConfig()->reinit();
        Mage::getConfig()->getCacheSaveLock(30, true);
        try {
            Mage::app()->cleanCache();
            if ($applySchema !== null) {
                $applySchema();
            }

            $scripts = ['schema' => 0, 'data' => 0];
            foreach (Mage_Core_Model_Resource_Setup::getAllPendingUpdates() as $updates) {
                foreach ($updates as $update) {
                    $update['type'] === 'data' ? $scripts['data']++ : $scripts['schema']++;
                }
            }
            Mage_Core_Model_Resource_Setup::applyAllUpdates();
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

            // saveCache() does nothing while the configuration cache is off
            $cache->unbanUse('config');
            Mage::getConfig()->saveCache();
        } finally {
            $cache->unbanUse('config');
            Mage::getConfig()->releaseCacheSaveLock();
        }

        Mage::dispatchEvent('adminhtml_cache_flush_system');
        return $scripts;
    }

    /**
     * Delete the minified CSS and JS files.
     */
    public function flushMinified(): void
    {
        Mage::helper('core/minify')->clearCache();
        Mage::dispatchEvent('clean_minified_cache_after');
    }

    /**
     * @param list<string> $codes
     * @return int the number of types whose state changed
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function saveEnabled(array $codes, bool $enabled): int
    {
        $this->assertKnown($codes);
        $cache = Mage::app()->getCache();
        // The stored options, because the options in memory also hold the types that this request turned off
        $options = Mage::getResourceSingleton('core/cache')->getAllOptions() ?: [];

        $changed = 0;
        foreach ($codes as $code) {
            if (!empty($options[$code]) !== $enabled) {
                $options[$code] = (int) $enabled;
                $changed++;
            }
            // saveUseCache() does not change the options in memory, so the rest of the request reads the new state
            $enabled ? $cache->unbanUse($code) : $cache->banUse($code);
        }
        if ($changed > 0) {
            Mage::app()->saveUseCache($options);
        }
        return $changed;
    }

    /**
     * @param list<string> $codes
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    private function assertKnown(array $codes): void
    {
        foreach ($codes as $code) {
            $this->getByCode($code);
        }
    }
}
