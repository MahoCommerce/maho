<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Search Catalog Model
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 *
 * @method bool hasLimit()
 * @method int getLimit()
 * @method bool hasQuery()
 * @method string getQuery()
 * @method bool setResults(array $value)
 * @method bool hasStart()
 * @method int getStart()
 */
class Mage_Adminhtml_Model_Search_Catalog extends Varien_Object
{
    /**
     * Load search results
     *
     * @return $this
     */
    public function load()
    {
        $arr = [];

        if (!$this->hasStart() || !$this->hasLimit() || !$this->hasQuery()) {
            $this->setResults($arr);
            return $this;
        }

        $collection = Mage::helper('catalogsearch')->getQuery()->getSearchCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('description')
            ->addSearchFilter($this->getQuery())
            ->setCurPage($this->getStart())
            ->setPageSize($this->getLimit())
            ->load();

        foreach ($collection as $product) {
            $description = strip_tags($product->getDescription());
            $arr[] = [
                'id'            => 'product/1/' . $product->getId(),
                'type'          => Mage::helper('adminhtml')->__('Product'),
                'name'          => $product->getName(),
                'description'   => Mage::helper('core/string')->substr($description, 0, 30),
                'url' => Mage::helper('adminhtml')->getUrl('*/catalog_product/edit', ['id' => $product->getId()]),
            ];
        }

        $this->setResults($arr);

        return $this;
    }
}
