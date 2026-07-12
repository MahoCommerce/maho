<?php

/**
 * Installed design package options (app/design/frontend directories).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Design_Package
{
    public function toOptionArray(): array
    {
        $options = [];
        $packages = Mage::getSingleton('core/design_package')->getPackageList();
        sort($packages);
        foreach ($packages as $package) {
            $options[] = ['value' => $package, 'label' => $package];
        }
        return $options;
    }
}
