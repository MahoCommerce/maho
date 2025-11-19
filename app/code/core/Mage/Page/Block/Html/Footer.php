<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Page_Block_Html_Footer extends Mage_Core_Block_Template
{
    /**
     * @var string
     */
    protected $_copyright;

    #[\Override]
    protected function _construct()
    {
        $this->addData(['cache_lifetime' => false]);
        $this->addCacheTag([
            Mage_Core_Model_Store::CACHE_TAG,
            Mage_Cms_Model_Block::CACHE_TAG,
        ]);
    }

    /**
     * Get cache key informative items
     *
     * @return array
     */
    #[\Override]
    public function getCacheKeyInfo()
    {
        return [
            $this->getIsMinimal() ? 'PAGE_FOOTER_MINIMAL' : 'PAGE_FOOTER',
            Mage::app()->getStore()->getId(),
            (int) Mage::app()->isCurrentlySecure(),
            Mage::getDesign()->getPackageName(),
            Mage::getDesign()->getTheme('template'),
            Mage::getSingleton('customer/session')->isLoggedIn(),
        ];
    }

    /**
     * @param string $copyright
     * @return $this
     */
    public function setCopyright($copyright)
    {
        $this->_copyright = $copyright;
        return $this;
    }

    /**
     * @return string
     */
    public function getCopyright()
    {
        if (!$this->_copyright) {
            $this->_copyright = Mage::getStoreConfig('design/footer/copyright');
        }

        return $this->_copyright;
    }

    /**
     * Retrieve child block HTML, sorted by default
     *
     * @param string $name
     * @param bool $useCache
     * @param bool $sorted
     * @return  string
     */
    #[\Override]
    public function getChildHtml($name = '', $useCache = true, $sorted = true)
    {
        return parent::getChildHtml($name, $useCache, $sorted);
    }
}
