<?php

/**
 * Maho
 *
 * @package    Mage_CatalogSearch
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Autocomplete queries list
 *
 * @package    Mage_CatalogSearch
 */
class Mage_CatalogSearch_Block_Autocomplete extends Mage_Core_Block_Template
{
    protected ?Mage_CatalogSearch_Model_Resource_Fulltext_Collection $productCollection = null;

    #[\Override]
    protected function _toHtml()
    {
        $html = '';

        if (!$this->_beforeToHtml()) {
            return $html;
        }

        $productCollection = $this->getProductCollection();
        if (!($count = count($productCollection))) {
            return $html;
        }

        $html = '<ul><li style="display:none"></li>';
        foreach ($productCollection as $produt) {
            $html .= '<li>';
            $html .= '<a href="' . $produt->getUrl() . '">';
            //$html .= '<img src="' . $produt->get . '" alt="" class="product-image" />';
            $html .= '<span class="product-name">' . $this->escapeHtml($produt->getName()) . '</span>';
            $html .= '<span class="product-price">' . Mage::helper('core')->formatPrice($produt->getPrice()) . '</span>';
            $html .= '</a>';
            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    public function getProductCollection(): Mage_CatalogSearch_Model_Resource_Fulltext_Collection
    {
        if (!$this->productCollection) {
            /** @var Mage_CatalogSearch_Helper_Data $helper */
            $helper = $this->helper('catalogsearch');
            $query = $helper->getQueryText();

            $productCollection = Mage::getResourceModel('catalogsearch/fulltext_collection');
            $productCollection->addSearchFilter($query)
                ->addAttributeToSelect(['name', 'thumbnail', 'price', 'url_key'])
                ->setOrder('relevance', 'desc')
                ->setPageSize(10); // Limit results
            $this->productCollection = $productCollection;
        }

        return $this->productCollection;
    }
}
