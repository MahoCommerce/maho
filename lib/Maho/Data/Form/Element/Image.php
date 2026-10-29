<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Image extends AbstractElement
{
    /** The preview shows at 4rem, so 128 pixels cover a screen with a pixel ratio of 2 */
    public const PREVIEW_WIDTH = 128;

    /**
     * Constructor
     *
     * @param array $data
     */
    public function __construct($data)
    {
        parent::__construct($data);
        $this->setType('file');
    }

    /**
     * Return element html code
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '';

        $value = $this->getValue();
        if ($value && is_string($value)) {
            $url = $this->_getUrl();

            if (!preg_match("/^http\:\/\/|https\:\/\//", $url)) {
                $url = \Mage::getBaseUrl('media') . $url;
            }

            $html = '<a href="' . $url . '"'
                . ' onclick="imagePreview(\'' . $this->getHtmlId() . '_image\'); return false;">'
                . '<img src="' . ($this->_getPreviewUrl() ?? $url) . '" id="' . $this->getHtmlId() . '_image" title="' . $this->_escape($value) . '"'
                . ' alt="' . $this->_escape($value) . '" height="22" width="22" class="small-image-preview v-middle" />'
                . '</a> ';
        }
        $this->setClass('input-file');
        $html .= parent::getElementHtml();
        $html .= $this->_getDeleteCheckbox();

        return '<div class="image-field">' . $html . '</div>';
    }

    /**
     * Return html code of delete checkbox element
     *
     * @return string
     */
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ($this->getValue()) {
            $label = \Mage::helper('core')->__('Delete Image');
            $html .= '<span class="delete-image">';
            $html .= '<input type="checkbox"'
                . ' name="' . parent::getName() . '[delete]" value="1" class="checkbox"'
                . ' id="' . $this->getHtmlId() . '_delete"' . ($this->getDisabled() ? ' disabled="disabled"' : '')
                . '>';
            $html .= '<label for="' . $this->getHtmlId() . '_delete"'
                . ($this->getDisabled() ? ' class="disabled"' : '') . '> ' . $label . '</label>';
            $html .= $this->_getHiddenInput();
            $html .= '</span>';
        }

        return $html;
    }

    /**
     * Return html code of hidden element
     *
     * @return string
     */
    protected function _getHiddenInput()
    {
        $value = $this->getValue();
        // Don't output hidden input for array values (delete checkbox)
        if (is_array($value)) {
            return '';
        }
        return '<input type="hidden" name="' . parent::getName() . '[value]" value="' . $this->_escape($value) . '">';
    }

    /**
     * Get image preview url
     *
     * @return string
     */
    protected function _getUrl()
    {
        $value = $this->getValue();
        if ($baseUrl = $this->getData('base_url')) {
            return $baseUrl . $value;
        }
        return $value;
    }

    /**
     * The URL of the smallest stored size of the image, or null when the field sets no
     * image_entity. The admin store has no sizes, so the sizes of the default store view apply.
     */
    protected function _getPreviewUrl(): ?string
    {
        $entity = $this->getData('image_entity');
        $value = $this->getValue();
        if (!is_string($entity) || !is_string($value)) {
            return null;
        }
        $storeId = (int) \Mage::app()->getDefaultStoreView()?->getId();
        return \Mage::helper('core/image')->init($entity, $value, $storeId)?->getUrl(self::PREVIEW_WIDTH);
    }

    /**
     * Return name
     *
     * @return string
     */
    #[\Override]
    public function getName()
    {
        return  $this->getData('name');
    }

    public function getDisabled(): ?bool
    {
        $value = $this->getData('disabled');
        return $value === null ? null : (bool) $value;
    }
}
