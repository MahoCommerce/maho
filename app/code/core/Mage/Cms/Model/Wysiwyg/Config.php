<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getStoreId()
 * @method $this setStoreId(string $value)
 */
class Mage_Cms_Model_Wysiwyg_Config extends \Maho\DataObject
{
    /**
     * Wysiwyg behaviour: enabled
     */
    public const WYSIWYG_ENABLED = 'enabled';

    /**
     * Wysiwyg behaviour: hidden
     */
    public const WYSIWYG_HIDDEN = 'hidden';

    /**
     * Wysiwyg behaviour: disabled
     */
    public const WYSIWYG_DISABLED = 'disabled';

    /**
     * constant for image directory
     */
    public const IMAGE_DIRECTORY = 'wysiwyg';

    /**
     * Return Wysiwyg config as \Maho\DataObject
     *
     * Config options description:
     *
     * enabled:                 Enabled Visual Editor or not
     * hidden:                  Show Visual Editor on page load or not
     * use_container:           Wrap Editor contents into div or not
     * no_display:              Hide Editor container or not (related to use_container)
     * translator:              Helper to translate phrases in lib
     * files_browser_*:         Files Browser (media, images) settings
     *
     * @param array $data constructor params to override default config values
     * @return \Maho\DataObject
     */
    public function getConfig($data = [])
    {
        $config = new \Maho\DataObject();

        $config->setData([
            'enabled'                       => $this->isEnabled(),
            'hidden'                        => $this->isHidden(),
            'use_container'                 => false,
            'add_variables'                 => Mage::getSingleton('admin/session')->isAllowed('system/variable'),
            'add_widgets'                   => Mage::getSingleton('admin/session')->isAllowed('cms/widget_instance'),
            'no_display'                    => false,
            'translator'                    => Mage::helper('cms'),
            'directives_url'                => Mage::getSingleton('adminhtml/url')->getUrl('*/cms_wysiwyg/directive'),
            'width'                         => '100%',
            'plugins'                       => [],
        ]);
        $config->setData('directives_url_quoted', preg_quote($config->getData('directives_url')));

        if (Mage::getSingleton('admin/session')->isAllowed('cms/media_gallery')) {
            $config->addData([
                'add_images'               => true,
                'files_browser_window_url' => Mage::getSingleton('adminhtml/url')->getUrl('*/cms_wysiwyg_images/index'),
                'files_browser_window_width'
                    => (int) Mage::getConfig()->getNode('adminhtml/cms/browser/window_width'),
                'files_browser_window_height'
                    => (int) Mage::getConfig()->getNode('adminhtml/cms/browser/window_height'),
            ]);
        }

        if (is_array($data)) {
            $config->addData($data);
        }

        Mage::dispatchEvent('cms_wysiwyg_config_prepare', ['config' => $config]);

        return $config;
    }

    /**
     * Check whether Wysiwyg is enabled or not
     *
     * @return bool
     */
    public function isEnabled()
    {
        $storeId = $this->getStoreId();
        if (!is_null($storeId)) {
            $wysiwygState = Mage::getStoreConfig('cms/wysiwyg/enabled', $storeId);
        } else {
            $wysiwygState = Mage::getStoreConfig('cms/wysiwyg/enabled');
        }
        return in_array($wysiwygState, [self::WYSIWYG_ENABLED, self::WYSIWYG_HIDDEN]);
    }

    /**
     * Check whether Wysiwyg is loaded on demand or not
     *
     * @return bool
     */
    public function isHidden()
    {
        return Mage::getStoreConfig('cms/wysiwyg/enabled') == self::WYSIWYG_HIDDEN;
    }
}
