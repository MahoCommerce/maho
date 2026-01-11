<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2015-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Helper_Js extends Mage_Core_Helper_Abstract
{
    /**
     * Key for cache
     */
    public const JAVASCRIPT_TRANSLATE_CONFIG_KEY = 'javascript_translate_config';

    /**
     * Translate file name
     */
    public const JAVASCRIPT_TRANSLATE_CONFIG_FILENAME = 'jstranslator.xml';

    protected $_moduleName = 'Mage_Core';

    /**
     * Array of sentences of JS translations
     *
     * @var array
     */
    protected $_translateData = [];

    /**
     * Translate config
     *
     * @var Mage_Core_Model_Translate_Config|null
     */
    protected $_config = null;

    /**
     * Retrieve JSON of JS sentences translation
     *
     * @return string
     */
    public function getTranslateJson()
    {
        return Mage::helper('core')->jsonEncode($this->_getTranslateData());
    }

    /**
     * Retrieve JS translator initialization javascript
     *
     * @return string
     */
    public function getTranslatorScript()
    {
        $script = 'var Translator = new Translate(' . $this->getTranslateJson() . ');';
        return $this->getScript($script);
    }

    /**
     * Retrieve framed javascript
     *
     * @param   string $script
     * @return  string script
     */
    public function getScript($script)
    {
        return '<script type="text/javascript">' . $script . '</script>' . "\n";
    }

    /**
     * Retrieve javascript include code
     *
     * @param   string $file
     * @return  string
     */
    public function includeScript($file)
    {
        return '<script type="text/javascript" src="' . $this->getJsUrl($file) . '"></script>' . "\n";
    }

    /**
     * Retrieve
     *
     * @param   string $file
     * @return  string
     */
    public function includeSkinScript($file)
    {
        return '<script type="text/javascript" src="' . $this->getJsSkinUrl($file) . '"></script>';
    }

    /**
     * Retrieve JS file url
     *
     * @param   string $file
     * @return  string
     */
    public function getJsUrl($file)
    {
        return Mage::getBaseUrl('js') . $file;
    }

    /**
     * Retrieve skin JS file url
     *
     * @param   string $file
     * @return  string
     */
    public function getJsSkinUrl($file)
    {
        return Mage::getDesign()->getSkinUrl($file, []);
    }

    /**
     * Add messages to the JS translation array
     *
     * @param string|array $messageText a single or array of messages to translate
     * @param ?string $module the helper module to use for translating, defaults to 'core'
     */
    public function addTranslateData(string|array $messageText, ?string $module): void
    {
        $module = $module ?: 'core';
        $messageText = is_array($messageText) ? $messageText : [$messageText];
        foreach ($messageText as $text) {
            $translated = Mage::helper($module)->__($text);
            if ($text && $text !== $translated) {
                $this->_translateData[$text] = $translated;
            }
        }
    }

    /**
     * Retrieve JS translation array
     *
     * @return array
     */
    protected function _getTranslateData()
    {
        // Get current area, i.e. "adminhtml" or "frontend" plus "global"
        $areas = [
            Mage_Core_Model_App_Area::AREA_GLOBAL,
            Mage::app()->getTranslator()->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA),
        ];
        // Get current layout handles
        $handles = $this->getLayout()->getUpdate()->getHandles();

        // Get currently loaded JS files
        $headBlock = $this->getLayout()->getBlock('head');
        $scripts = $headBlock ? array_keys($headBlock->getData('items')) : [];

        foreach ($this->_getXmlConfig()->getNode()->children() as $node) {
            // Check for <frontend>, <adminhtml>, and <global> nodes
            if (in_array($node->getName(), $areas) && !isset($node['translate'])) {
                foreach ($node->children() as $child) {
                    $module = $child->xpath('ancestor-or-self::*/@module')[0]['module'] ?? null;
                    $this->addTranslateData((string) $child->message, (string) $module);
                }
                continue;
            }
            // Allow nodes to define a custom separator for area, handle, and script path attributes
            $separator = $node['separator'] ?? ',';

            // Check if we have an area attribute for all other node types
            if (isset($node['area']) && !array_intersect(explode($separator, $node['area']), $areas)) {
                continue;
            }
            // Check for <layout> nodes and if current layout handles match
            if ($node->getName() === 'layout' && isset($node['handle'])) {
                if (array_intersect(explode($separator, $node['handle']), $handles)) {
                    foreach ($node->children() as $child) {
                        $module = $child->xpath('ancestor-or-self::*/@module')[0]['module'] ?? null;
                        $this->addTranslateData((string) $child->message, (string) $module);
                    }
                }
                continue;
            }
            // Check for <script> nodes and if we have loaded the JS file
            if ($node->getName() === 'script' && isset($node['path'])) {
                $type = $node['type'] ?? 'js';
                $paths = array_map(fn($path) => "$type/$path", explode($separator, $node['path']));
                if (array_intersect($paths, $scripts)) {
                    foreach ($node->children() as $child) {
                        $module = $child->xpath('ancestor-or-self::*/@module')[0]['module'] ?? null;
                        $this->addTranslateData((string) $child->message, (string) $module);
                    }
                }
                continue;
            }
            // Default to original behavior
            if (isset($node->message)) {
                $this->addTranslateData((string) $node->message, (string) $node['module']);
            }
        }
        return $this->_translateData;
    }

    /**
     * Load config from files and try to cache it
     *
     * @return \Maho\Simplexml\Config
     */
    protected function _getXmlConfig()
    {
        if (is_null($this->_config)) {
            /** @var Mage_Core_Model_Translate_Config $xmlConfig */
            $xmlConfig = Mage::getModel('core/translate_config');

            $canUseCache = Mage::app()->useCache('config');
            $cachedXml = Mage::app()->loadCache(self::JAVASCRIPT_TRANSLATE_CONFIG_KEY);
            if ($canUseCache && $cachedXml) {
                $xmlConfig->loadString($cachedXml);
            } else {
                $xmlConfig->loadString('<?xml version="1.0"?><jstranslator></jstranslator>');
                Mage::getConfig()->loadModulesConfiguration(self::JAVASCRIPT_TRANSLATE_CONFIG_FILENAME, $xmlConfig);

                if ($canUseCache) {
                    Mage::app()->saveCache(
                        $xmlConfig->getXmlString(),
                        self::JAVASCRIPT_TRANSLATE_CONFIG_KEY,
                        [Mage_Core_Model_Config::CACHE_TAG],
                    );
                }
            }
            $this->_config = $xmlConfig;
        }
        return $this->_config;
    }

    /**
     * Helper for "onclick.deleteConfirm"
     *
     * @param string|null $message null for default message, do not use jsQuoteEscape() before
     * @uses Mage_Core_Helper_Abstract::jsQuoteEscape()
     */
    public function getDeleteConfirmJs(string $url, ?string $message = null): string
    {
        if (is_null($message)) {
            $message = Mage::helper('adminhtml')->__('Are you sure you want to do this?');
        }

        $message = Mage::helper('core')->jsQuoteEscape($message);
        return 'deleteConfirm(\'' . $message . '\', \'' . $url . '\')';
    }

    /**
     * Helper for "onclick.confirmSetLocation"
     *
     * @param string|null $message null for default message, do not use jsQuoteEscape() before
     * @uses Mage_Core_Helper_Abstract::jsQuoteEscape()
     */
    public function getConfirmSetLocationJs(string $url, ?string $message = null): string
    {
        if (is_null($message)) {
            $message = Mage::helper('adminhtml')->__('Are you sure you want to do this?');
        }

        $message = Mage::helper('core')->jsQuoteEscape($message);
        return "confirmSetLocation('{$message}', '{$url}')";
    }

    /**
     * Helper for "onclick.setLocation"
     */
    public function getSetLocationJs(string $url): string
    {
        return 'setLocation(\'' . $url . '\')';
    }

    /**
     * Helper for "onclick.saveAndContinueEdit"
     */
    public function getSaveAndContinueEditJs(string $url): string
    {
        return 'saveAndContinueEdit(\'' . $url . '\')';
    }
}
