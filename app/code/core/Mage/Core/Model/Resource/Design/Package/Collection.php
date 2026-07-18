<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Design_Package_Collection extends \Maho\DataObject
{
    /**
     * Load design package collection
     *
     * @return $this
     */
    public function load()
    {
        $packages = $this->getData('packages');
        if (is_null($packages)) {
            $packages = Mage::getModel('core/design_package')->getPackageList();
            $this->setData('packages', $packages);
        }

        return $this;
    }

    public function toOptionArray(): array
    {
        $options = [];
        $packages = $this->getData('packages');
        foreach ($packages as $package) {
            $options[] = ['value' => $package, 'label' => $package];
        }
        array_unshift($options, ['value' => '', 'label' => '']);

        return $options;
    }
}
