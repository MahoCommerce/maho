<?php

/**
 * Validates and normalizes the scheduled-scan URL list (one URL per line).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_System_Config_Backend_ScheduledUrls extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave(): self
    {
        $helper = Mage::helper('accessibilityscan');

        $urls = [];
        foreach (preg_split('/\R/', (string) $this->getValue()) ?: [] as $line) {
            $url = trim($line);
            if ($url === '') {
                continue;
            }
            if (!Mage::helper('core')->isValidUrl($url) || !$helper->isAllowedScanUrl($url)) {
                Mage::throwException($helper->__('Invalid scan URL "%s": every URL must belong to one of the configured store base URLs', $url));
            }
            $urls[] = $url;
        }

        $this->setValue(implode("\n", array_values(array_unique($urls))));
        return parent::_beforeSave();
    }
}
