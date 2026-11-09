<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Design_Config extends \Maho\Simplexml\Config
{
    protected $_designRoot;

    /**
     * Assemble themes inheritance config
     * @throws Mage_Core_Exception
     */
    public function __construct(array $params = [])
    {
        if (isset($params['designRoot'])) {
            if (!is_dir($params['designRoot'])) {
                throw new Mage_Core_Exception("Design root '{$params['designRoot']}' isn't a directory.");
            }
            $this->_designRoot = rtrim($params['designRoot'], '/\\');
        } else {
            $this->_designRoot = Mage::getBaseDir('design');
        }
        $this->_cacheChecksum = null;
        $this->setCacheId('config_theme');
        $this->setCache(Mage::app()->getCache());
        if (!$this->loadCache()) {
            $this->loadString('<theme />');

            $files = [];

            foreach (Maho::getInstalledPackages() as $package => $info) {
                if ($package === 'root') {
                    $designRoot = $this->_designRoot;
                } else {
                    $designRoot = $info['path'] . '/app/design';
                }
                foreach (glob("$designRoot/*/*/*/etc/theme.xml") as $file) {
                    $relativePath = str_replace($designRoot, '', $file);
                    $files[$relativePath] = $file;
                }
            }

            foreach ($files as $file) {
                $config = new \Maho\Simplexml\Config();
                $config->loadFile($file);
                [$area, $package, $theme] = $this->_getThemePathSegments($file);
                $this->setNode($area . '/' . $package . '/' . $theme, null);
                $this->getNode($area . '/' . $package . '/' . $theme)->extend($config->getNode());
            }
            $this->saveCache();
        }
    }

    /**
     * The $name nodes of theme.xml for the skin theme of the store view and for its parents, child
     * first, up to and with base/default. As in the design fallback, when the theme declares no
     * parent, only the default theme of the store view and then the default theme of the package
     * come before base/default. A design change, a user agent exception or the custom design of a
     * category or a product does not change the theme here.
     *
     * @return list<\Maho\Simplexml\Element>
     */
    public function getThemeNodes(string $name, int $storeId): array
    {
        $area = Mage_Core_Model_Design_Package::DEFAULT_AREA;
        $package = (string) Mage::getStoreConfig('design/package/name', $storeId) ?: Mage_Core_Model_Design_Package::DEFAULT_PACKAGE;
        $theme = (string) Mage::getStoreConfig('design/theme/skin', $storeId)
            ?: (string) Mage::getStoreConfig('design/theme/default', $storeId)
            ?: Mage_Core_Model_Design_Package::DEFAULT_THEME;

        $steps = [
            ...Mage::getModel('core/design_fallback', ['config' => $this])->setStore($storeId)->getFallbackScheme($area, $package, $theme),
            ['_package' => Mage_Core_Model_Design_Package::BASE_PACKAGE, '_theme' => Mage_Core_Model_Design_Package::DEFAULT_THEME],
        ];
        $nodes = [];
        foreach ($steps as $step) {
            $package = $step['_package'] ?? $package;
            $theme = $step['_theme'] ?? $theme;
            $node = $this->getNode("{$area}/{$package}/{$theme}/{$name}");
            if ($node) {
                $nodes[] = $node;
            }
        }
        return $nodes;
    }

    /**
     * Load cache
     *
     * @return bool
     */
    #[\Override]
    public function loadCache()
    {
        if ($this->_canUseCache()) {
            return parent::loadCache();
        }
        return false;
    }

    /**
     * Save cache
     *
     * @param array $tags
     * @return $this
     */
    #[\Override]
    public function saveCache($tags = null)
    {
        if ($this->_canUseCache()) {
            $tags = is_array($tags) ? $tags : [];
            if (!in_array(Mage_Core_Model_Config::CACHE_TAG, $tags)) {
                $tags[] = Mage_Core_Model_Config::CACHE_TAG;
            }
            parent::saveCache($tags);
        }
        return $this;
    }

    /**
     * @return bool
     */
    protected function _canUseCache()
    {
        return (bool) Mage::app()->useCache('config');
    }

    /**
     * Get area, package and theme from path .../app/design/{area}/{package}/{theme}/etc/theme.xml
     *
     * @param string $filePath
     * @return array
     */
    protected function _getThemePathSegments($filePath)
    {
        $segments = array_reverse(explode(DS, $filePath));
        return [$segments[4], $segments[3], $segments[2]];
    }
}
