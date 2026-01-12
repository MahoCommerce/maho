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
