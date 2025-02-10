<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Html page block
 *
 * @package    Mage_Page
 *
 * @method $this setLogoAlt(string $value)
 * @method $this setLogoSrc(string $value)
 * @method $this setLogoWidth(string $value)
 * @method $this setLogoHeight(string $value)
 */
class Mage_Page_Block_Html_Header extends Mage_Core_Block_Template
{
    #[\Override]
    public function _construct()
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
     * @return string
     */
    public function getLogoSrc()
    {
        if (empty($this->_data['logo_src'])) {
            $this->_data['logo_src'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_src'));
        }
        return $this->getSkinUrl($this->_data['logo_src']);
    }

    /**
     * @return string
     * @deprecated Use SVG or srcset
     */
    public function getLogoSrcSmall()
    {
        if (empty($this->_data['logo_src_small'])) {
            $this->_data['logo_src_small'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_src_small'));
        }
        return $this->getSkinUrl($this->_data['logo_src_small']);
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

    public function getLogoWidth(): string
    {
        if (empty($this->_data['logo_width'])) {
            $this->_data['logo_width'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_width'));
        }
        return $this->_data['logo_width'];
    }

    public function getLogoHeight(): string
    {
        if (empty($this->_data['logo_height'])) {
            $this->_data['logo_height'] = $this->escapeHtmlAsObject((string) Mage::getStoreConfig('design/header/logo_height'));
        }
        return $this->_data['logo_height'];
    }
}
