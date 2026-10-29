<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Adminhtml_Template_Filter extends Mage_Cms_Model_Template_Filter
{
    /**
     * Retrieve media file local path instead of URL, so it can be read by Intervention Image
     *
     * @param array $construction
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function mediaDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for media directive.');
        }

        return Mage::getBaseDir('media') . DS . $params['url'];
    }

    /**
     * Retrieve skin file local path instead of URL, so it can be read by Intervention Image
     *
     * Only url, _area, _package and _theme are used. Each of the last three must be one directory name,
     * and the file must be inside a skin directory.
     *
     * @param array $construction
     * @return string
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function skinDirective($construction)
    {
        $params = $this->_getIncludeParameters($construction[2]);
        if (!isset($params['url'])) {
            Mage::throwException('Undefined url parameter for skin directive.');
        }

        $designParams = ['_type' => 'skin'];
        foreach (['_area', '_package', '_theme'] as $key) {
            if (!isset($params[$key])) {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string) $params[$key])) {
                Mage::throwException('Invalid ' . $key . ' parameter for skin directive.');
            }
            $designParams[$key] = (string) $params[$key];
        }

        try {
            $path = (string) Mage::getDesign()->getFilename((string) $params['url'], $designParams);
        } catch (Exception) {
            $path = '';
        }
        if (!$this->isSkinFile($path)) {
            Mage::throwException('Invalid url parameter for skin directive.');
        }

        return $path;
    }

    /**
     * Return true when $path is inside the skin directory of Maho or of an installed package.
     */
    protected function isSkinFile(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $skinDirs = [Mage::getBaseDir('skin')];
        foreach (Maho::getInstalledPackages() as $package) {
            $skinDirs[] = $package['path'] . '/public/skin';
        }
        return array_any(array_unique($skinDirs), fn($skinDir) => \Maho\Io::getPathWithinDir($skinDir, $path) !== null);
    }
}
