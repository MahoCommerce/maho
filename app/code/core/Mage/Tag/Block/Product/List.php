<?php

/**
 * Maho
 *
 * @package    Mage_Tag
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Tag_Block_Product_List extends Mage_Core_Block_Template
{
    protected $_collection;

    /**
     * Unique Html Id
     *
     * @var string|null
     */
    protected $_uniqueHtmlId = null;

    /**
     * @return int
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getCount()
    {
        return count($this->getTags());
    }

    /**
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getTags()
    {
        return $this->_getCollection()->getItems();
    }

    /**
     * @return int|false
     */
    public function getProductId()
    {
        if ($product = Mage::registry('current_product')) {
            return $product->getId();
        }
        return false;
    }

    /**
     * @return mixed
     * @throws Mage_Core_Model_Store_Exception
     */
    protected function _getCollection()
    {
        if (!$this->_collection && $this->getProductId()) {
            $model = Mage::getModel('tag/tag');
            $this->_collection = $model->getResourceCollection()
                ->addPopularity()
                ->addStatusFilter($model->getApprovedStatus())
                ->addProductFilter($this->getProductId())
                ->setFlag('relation', true)
                ->addStoreFilter(Mage::app()->getStore()->getId())
                ->setActiveFilter()
                ->load();
        }
        return $this->_collection;
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        if (!$this->getProductId()) {
            return $this;
        }

        return parent::_beforeToHtml();
    }

    /**
     * @return string
     */
    public function getFormAction()
    {
        return Mage::getUrl('tag/index/save', [
            'product' => $this->getProductId(),
            Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => Mage::helper('core/url')->getEncodedUrl(),
            '_secure' => $this->_isSecure(),
        ]);
    }

    /**
     * Render tags by specified pattern and implode them by specified 'glue' string
     *
     * @param string $pattern
     * @param string $glue
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    public function renderTags($pattern, $glue = ' ')
    {
        $out = [];
        foreach ($this->getTags() as $tag) {
            $out[] = sprintf(
                $pattern,
                $tag->getTaggedProductsUrl(),
                $this->escapeHtml($tag->getName()),
                $tag->getProducts(),
            );
        }
        return implode($glue, $out);
    }

    /**
     * Generate unique html id
     *
     * @param string $prefix
     * @return string
     */
    public function getUniqueHtmlId($prefix = '')
    {
        if (is_null($this->_uniqueHtmlId)) {
            $this->_uniqueHtmlId = Mage::helper('core/data')->uniqHash($prefix);
        }
        return $this->_uniqueHtmlId;
    }

    #[\Override]
    public function renderView()
    {
        $helper = $this->helper('tag');
        if ($this->getCount() === 0 && $helper->isAddingTagsEnabledOnFrontend() === false) {
            return '';
        }

        return parent::renderView();
    }
}
