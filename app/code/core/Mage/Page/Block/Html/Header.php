<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

/**
 * @method $this setLogoAlt(string $value)
 * @method $this setLogoSrc(string $value)
 * @method $this setLogoWidth(string $value)
 * @method $this setLogoHeight(string $value)
 */
class Mage_Page_Block_Html_Header extends Mage_Core_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('page/html/header.phtml');
    }

    /**
     * Check if current url is url for home page
     *
     * @return bool
     */
    public function getIsHomePage()
    {
        return $this->getUrl('') == $this->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true]);
    }

    /**
     * @param string $logoSrc
     * @param string $logoAlt
     * @return $this
     */
    public function setLogo($logoSrc, $logoAlt)
    {
        $this->setLogoSrc($logoSrc);
        $this->setLogoAlt($logoAlt);
        return $this;
    }

    /**
     * The configured logo is a skin path by default. A full URL is used as is, and a
     * path under media/ resolves against the media base URL, so a logo uploaded through
     * the admin file manager works without a skin file.
     *
     * @return string
     */
    public function getLogoSrc()
    {
        if (empty($this->_data['logo_src'])) {
            $this->_data['logo_src'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_src'));
        }
        return $this->resolveLogoUrl($this->_data['logo_src']);
    }

    protected function resolveLogoUrl(string $src): string
    {
        if (preg_match('#^(https?:)?//#', $src)) {
            return $src;
        }
        if (str_starts_with($src, 'media/')) {
            return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . substr($src, 6);
        }
        return $this->getSkinUrl($src);
    }

    /**
     * @return string
     */
    public function getLogoAlt()
    {
        if (empty($this->_data['logo_alt'])) {
            $this->_data['logo_alt'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_alt'));
        }
        return $this->_data['logo_alt'];
    }

    protected function calculateLogoSize(): void
    {
        $width = Mage::getStoreConfig('design/header/logo_width');
        $height = Mage::getStoreConfig('design/header/logo_height');
        if (empty($width) && empty($height)) {
            $height = 50;
        }
        $this->_data['logo_width'] = $this->escapeHtmlAsObject((string) $width);
        $this->_data['logo_height'] = $this->escapeHtmlAsObject((string) $height);
    }

    public function getLogoWidth(): string
    {
        if (empty($this->_data['logo_width'])) {
            $this->calculateLogoSize();
        }
        return $this->_data['logo_width'];
    }

    public function getLogoHeight(): string
    {
        if (empty($this->_data['logo_height'])) {
            $this->calculateLogoSize();
        }
        return $this->_data['logo_height'];
    }
}
