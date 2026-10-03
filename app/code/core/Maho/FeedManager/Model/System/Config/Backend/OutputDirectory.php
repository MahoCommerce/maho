<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_System_Config_Backend_OutputDirectory extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave(): self
    {
        $value = trim((string) $this->getValue());

        if ($value !== '') {
            $path = \Maho\Io::getPathWithinMount(Mage::helper('feedmanager')->getOutputMount(), '', $value);
            if ($path === null) {
                Mage::throwException(Mage::helper('feedmanager')->__('Output directory must be a relative path within the media folder.'));
            }

            $this->setValue($path);
        }

        return parent::_beforeSave();
    }
}
