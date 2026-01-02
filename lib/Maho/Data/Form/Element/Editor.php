<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

/**
 * @method string getTitle()
 * @method string getForceLoad()
 * @method $this setConfig(\Maho\DataObject $value)
 * @method bool getWysiwyg()
 */
class Editor extends Textarea
{
    /**
     * Editor constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);

        if ($this->isEnabled()) {
            $this->setType('wysiwyg');
            $this->setExtType('wysiwyg');
        } else {
            $this->setType('textarea');
            $this->setExtType('textarea');
        }
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $this->addClass('textarea');
        if ($this->isEnabled()) {
            if (!$this->isHidden()) {
                $this->addClass('no-display');
            }
            $jsSetupObject = 'wysiwyg' . $this->getHtmlId();
            $configObject = json_encode($this->getConfig());

            $html = <<<HTML
                {$this->_getButtonsHtml()}
                <textarea id="{$this->getHtmlId()}" name="{$this->getName()}" {$this->serialize($this->getHtmlAttributes())}>{$this->getEscapedValue()}</textarea>
                <script>
                    mahoOnReady(() => {
                        window.$jsSetupObject = new tiptapWysiwygSetup('{$this->getHtmlId()}', $configObject);
                    });
                </script>
            HTML;

            $html = $this->_wrapIntoContainer($html);
            $html .= $this->getAfterElementHtml();
            return $html;

        }
        // Display only buttons to additional features
        if ($this->getConfig('widget_window_url') || $this->getConfig('plugins') || $this->getConfig('add_images')) {
            $html = $this->_getButtonsHtml() . parent::getElementHtml();
            $html = $this->_wrapIntoContainer($html);
            return $html;
        }
        return parent::getElementHtml();
    }

    /**
     * @return string
     */
    public function getTheme()
    {
        if (!$this->hasData('theme')) {
            return 'simple';
        }

        return $this->_getData('theme');
    }

    /**
     * Return Editor top Buttons HTML
     *
     * @return string
     */
    protected function _getButtonsHtml()
    {
        $buttonsHtml = '<div id="buttons' . $this->getHtmlId() . '" class="buttons-set">';
        if ($this->isEnabled()) {
            $buttonsHtml .= $this->_getToggleButtonHtml() . $this->_getPluginButtonsHtml($this->isHidden());
        } else {
            $buttonsHtml .= $this->_getPluginButtonsHtml(true);
        }
        $buttonsHtml .= '</div>';

        return $buttonsHtml;
    }

    /**
     * Return HTML button to toggling WYSIWYG
     *
     * @param bool $visible
     * @return string
     */
    protected function _getToggleButtonHtml($visible = true)
    {
        $html = $this->_getButtonHtml([
            'title'     => $this->translate('Show / Hide Editor'),
            'class'     => 'show-hide' . ($visible ? '' : ' no-display'),
            'id'        => 'toggle' . $this->getHtmlId(),
        ]);
        return $html;
    }

    /**
     * Prepare Html buttons for additional WYSIWYG features
     *
     * @param bool $visible Display button or not
     * @return string
     */
    protected function _getPluginButtonsHtml($visible = true)
    {
        $buttonsHtml = '';

        // Button to widget insertion window
        if ($this->getConfig('add_widgets')) {
            $url = $this->_getButtonUrl($this->getConfig('widget_window_url'), [
                'widget_target_id' => $this->getHtmlId(),
            ]);
            $buttonsHtml .= $this->_getButtonHtml([
                'title'     => $this->translate('Insert Widget...'),
                'onclick'   => "widgetTools.openDialog('$url');",
                'class'     => 'add-widget plugin' . ($visible ? '' : ' no-display'),
            ]);
        }

        // Button to media images insertion window
        if ($this->getConfig('add_images')) {
            $url = $this->_getButtonUrl($this->getConfig('files_browser_window_url'), [
                'target_element_id' => $this->getHtmlId(),
                'store' => $this->getConfig('store_id'),
            ]);
            $buttonsHtml .= $this->_getButtonHtml([
                'title'     => $this->translate('Insert Image...'),
                'onclick'   => "MediabrowserUtility.openDialog('$url');",
                'class'     => 'add-image plugin' . ($visible ? '' : ' no-display'),
            ]);
        }

        foreach ($this->getConfig('plugins') as $plugin) {
            if (isset($plugin['options']) && $this->_checkPluginButtonOptions($plugin['options'])) {
                $buttonOptions = $this->_prepareButtonOptions($plugin['options']);
                if (!$visible) {
                    $buttonOptions['class'] .= ' no-display';
                }
                $buttonsHtml .= $this->_getButtonHtml($buttonOptions);
            }
        }

        return $buttonsHtml;
    }

    /**
     * Prepare button options array to create button html
     *
     * @param array $options
     * @return array
     */
    protected function _prepareButtonOptions($options)
    {
        return $this->_prepareOptions(['class' => 'plugin', ...$options]);
    }

    /**
     * Check if plugin button options have required values
     *
     * @param array $pluginOptions
     * @return bool
     */
    protected function _checkPluginButtonOptions($pluginOptions)
    {
        if (!isset($pluginOptions['title'])) {
            return false;
        }
        return true;
    }

    /**
     * Convert options by replacing template constructions ( like {{var_name}} )
     * with data from this element object
     *
     * @param array $options
     * @return array
     */
    protected function _prepareOptions($options)
    {
        $preparedOptions = [];
        foreach ($options as $name => $value) {
            if (is_array($value) && isset($value['search']) && isset($value['subject'])) {
                $subject = $value['subject'];
                foreach ($value['search'] as $part) {
                    $subject = str_replace('{{' . $part . '}}', $this->getDataUsingMethod($part), $subject);
                }
                $preparedOptions[$name] = $subject;
            } else {
                $preparedOptions[$name] = $value;
            }
        }
        return $preparedOptions;
    }

    /**
     * Return custom button HTML
     *
     * @param array $data Button params
     * @return string
     */
    protected function _getButtonHtml($data)
    {
        $title = $data['title'];

        $attributes = new \Maho\DataObject($data);
        $attributes->setType('button');
        $attributes->setClass('scalable ' . $attributes->getClass());
        $attributes->unsTitle();

        return "<button {$attributes->serialize()}>$title</button>";
    }

    /**
     * Return custom button HTML
     */
    protected function _getButtonUrl(string $url, ?array $params): string
    {
        foreach ($params as $key => $value) {
            if ($value !== null) {
                $url .= "$key/$value/";
            }
        }
        return $url;
    }

    /**
     * Wraps Editor HTML into div if 'use_container' config option is set to true
     * If 'no_display' config option is set to true, the div will be invisible
     *
     * @param string $html HTML code to wrap
     * @return string
     */
    protected function _wrapIntoContainer($html)
    {
        if (!$this->getConfig('use_container')) {
            return $html;
        }

        $attributes = new \Maho\DataObject();
        $attributes->setId('editor' . $this->getHtmlId());
        $attributes->setClass(
            $this->getConfig('container_class') . ($this->getConfig('no_display') ? ' no-display' : ''),
        );

        return "<div {$attributes->serialize()}>$html</div>";
    }

    /**
     * Editor config retriever
     *
     * @param string $key Config var key
     * @return mixed
     */
    public function getConfig($key = null)
    {
        if (!($this->_getData('config') instanceof \Maho\DataObject)) {
            $config = new \Maho\DataObject();
            $this->setConfig($config);
        }
        if ($key !== null) {
            return $this->_getData('config')->getData($key);
        }
        return $this->_getData('config');
    }

    /**
     * Translate string using defined helper
     *
     * @param string $string String to be translated
     * @return string
     */
    public function translate($string)
    {
        $translator = $this->getConfig('translator');
        if ($translator && method_exists($translator, '__')) {
            $result = $translator->__($string);
            if (is_string($result)) {
                return $result;
            }
        }

        return $string;
    }

    /**
     * Check whether Wysiwyg is enabled or not
     *
     * @return bool
     */
    public function isEnabled()
    {
        if ($this->hasData('wysiwyg')) {
            return $this->getWysiwyg();
        }
        return $this->getConfig('enabled');
    }

    /**
     * Check whether Wysiwyg is loaded on demand or not
     *
     * @return bool
     */
    public function isHidden()
    {
        return $this->getConfig('hidden');
    }
}
