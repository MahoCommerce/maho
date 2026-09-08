<?php

/**
 * Installed design package options (app/design/frontend directories).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Design_Package
{
    public const CONFIG_PATH = 'design/package/name';

    public function toOptionArray(): array
    {
        $options = [];
        $packages = Mage::getSingleton('core/design_package')->getPackageList();
        sort($packages);
        foreach ($packages as $package) {
            $label = $package === Mage_Core_Model_Design_Package::LEGACY_PACKAGE
                ? $package . Mage::helper('adminhtml')->__(' (deprecated since 26.9)')
                : $package;
            $options[] = ['value' => $package, 'label' => $label];
        }
        foreach (self::storedValues([self::CONFIG_PATH], $packages) as $package) {
            $options[] = ['value' => $package, 'label' => $package . Mage::helper('adminhtml')->__(' (not installed)')];
        }
        return $options;
    }

    /**
     * The values some scope stores that no folder provides. A select renders no option for
     * an unknown value and posts the first one instead, so an unrelated save would silently
     * replace a theme that is only temporarily missing from disk.
     *
     * @param list<string> $paths
     * @param list<string> $installed
     * @return list<string>
     */
    public static function storedValues(array $paths, array $installed): array
    {
        $stored = Mage::getResourceModel('core/config_data_collection')
            ->addFieldToFilter('path', ['in' => $paths])
            ->getColumnValues('value');
        $missing = array_diff(array_unique(array_filter(array_map(trim(...), $stored))), $installed);
        sort($missing);
        return $missing;
    }
}
