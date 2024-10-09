<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Configuration for Design model
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Design_Config extends Varien_Simplexml_Config
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

            // Include module files
            $modules = mahoGetComposerInstallationData();
            foreach ($modules as $module => $info) {
                if ($module === 'mahocommerce/maho' && $info['isChildProject'] === false) {
                    continue;
                }
                foreach (glob($info['path'] . '/app/design/*/*/*/etc/theme.xml') as $file) {
                    $normalizedFile = str_replace($info['path'] . '/app/design', '', $file);
                    $files[$normalizedFile] = $file;
                }
            }

            // Include local files, overriding module files
            foreach (glob($this->_designRoot . '/*/*/*/etc/theme.xml') as $file) {
                $normalizedFile = str_replace($this->_designRoot, '', $file);
                $files[$normalizedFile] = $file;
            }

            foreach ($files as $file) {
                $config = new Varien_Simplexml_Config();
                $config->loadFile($file);
                list($area, $package, $theme) = $this->_getThemePathSegments($file);
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
        return (bool)Mage::app()->useCache('config');
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
