<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * WYSIWYG widget options form
 *
 * @method $this setConfig(\Maho\DataObject $value)
 * @method $this setElement(\Maho\Data\Form\Element\AbstractElement $value)
 * @method $this setFieldsetId(string $value)
 * @method string getLabel()
 * @method $this setSourceUrl(string $value)
 * @method $this setUniqId(string $value)
 */
class Mage_Widget_Block_Adminhtml_Widget_Chooser extends Mage_Adminhtml_Block_Template
{
    /**
     * Internal constructor, that is called from real constructor
     *
     * @return void
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTranslationHelper($this->helper('widget'));
    }

    /**
     * Chooser source URL getter
     *
     * @return string
     */
    public function getSourceUrl()
    {
        return $this->_getData('source_url');
    }

    /**
     * Chooser form element getter
     *
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function getElement()
    {
        return $this->_getData('element');
    }

    /**
     * Convert Array config to Object
     *
     * @return \Maho\DataObject
     */
    public function getConfig()
    {
        if ($this->_getData('config') instanceof \Maho\DataObject) {
            return $this->_getData('config');
        }

        $configArray = $this->_getData('config');
        $config = new \Maho\DataObject();
        $this->setConfig($config);
        if (!is_array($configArray)) {
            /** @var \Maho\DataObject $configData */
            $configData = $this->_getData('config');
            return $configData;
        }

        // define chooser label
        if (isset($configArray['label'])) {
            $config->setData('label', $this->__($configArray['label']));
        }

        // chooser control buttons
        $buttons = [
            'open'  => Mage::helper('widget')->__('Choose...'),
            'close' => Mage::helper('widget')->__('Close'),
        ];
        if (isset($configArray['button']) && is_array($configArray['button'])) {
            foreach ($configArray['button'] as $id => $label) {
                $buttons[$id] = $this->__($label);
            }
        }
        $config->setButtons($buttons);

        /** @var \Maho\DataObject $configData */
        $configData = $this->_getData('config');
        return $configData;
    }

    /**
     * Unique identifier for block that uses Chooser
     *
     * @return string
     */
    public function getUniqId()
    {
        return $this->_getData('uniq_id');
    }

    /**
     * Form element fieldset id getter for working with form in chooser
     *
     * @return string
     */
    public function getFieldsetId()
    {
        return $this->_getData('fieldset_id');
    }

    /**
     * Flag to indicate include hidden field before chooser or not
     *
     * @return bool
     */
    public function getHiddenEnabled()
    {
        return $this->hasData('hidden_enabled') ? (bool) $this->_getData('hidden_enabled') : true;
    }

    /**
     * Return chooser HTML and init scripts
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $element   = $this->getElement();
        /** @var \Maho\Data\Form\Element\Fieldset $fieldset */
        $fieldset  = $element->getForm()->getElement($this->getFieldsetId());
        $chooserId = $this->getUniqId();
        $config    = $this->getConfig();

        // add chooser element to fieldset
        $chooser = $fieldset->addField('chooser' . $element->getId(), 'note', [
            'label'       => $config->getLabel() ?: '',
            'value_class' => 'value2',
        ]);
        $hiddenHtml = '';
        if ($this->getHiddenEnabled()) {
            $hidden = new \Maho\Data\Form\Element\Hidden($element->getData());
            $hidden->setId("{$chooserId}value")->setForm($element->getForm());
            if ($element->getRequired()) {
                $hidden->addClass('required-entry');
            }
            $hiddenHtml = $hidden->getElementHtml();
            $element->setValue('');
        }

        $buttons = $config->getButtons();
        $chooseButton = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setId($chooserId . 'control')
            ->setClass('btn-chooser')
            ->setLabel($buttons['open'])
            ->setOnclick($chooserId . '.choose()')
            ->setDisabled($element->getReadonly());
        $chooser->setData('after_element_html', $hiddenHtml . $chooseButton->toHtml());

        // render label and chooser scripts
        $configJson = Mage::helper('core')->jsonEncode($config->getData());
        return '
            <label class="widget-option-label" id="' . $chooserId . 'label">'
            . $this->quoteEscape($this->getLabel() ?: Mage::helper('widget')->__('Not Selected'))
            . '</label>
            <div id="' . $chooserId . 'advice-container" class="hidden"></div>
            <script type="text/javascript">
                var instantiateChooser = function() {
                    window.' . $chooserId . ' = new WysiwygWidget.chooser(
                        "' . $chooserId . '",
                        "' . $this->getSourceUrl() . '",
                        ' . $configJson . '
                    );
                    if (document.getElementById("' . $chooserId . 'value")) {
                        document.getElementById("' . $chooserId . 'value").advaiceContainer = "' . $chooserId . 'advice-container";
                    }
                };

                if (document.loaded || document.readyState === "complete" || document.readyState === "interactive") {
                    instantiateChooser();
                } else {
                    document.addEventListener("DOMContentLoaded", instantiateChooser);
                }
            </script>
        ';
    }
}
