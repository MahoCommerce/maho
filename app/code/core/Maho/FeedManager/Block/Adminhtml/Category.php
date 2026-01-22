<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Category extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('maho/feedmanager/category/mapping.phtml');
        $this->_headerText = $this->__('Category Mapping');
    }

    public function getPlatformOptions(): array
    {
        return Maho_FeedManager_Model_Platform::getPlatformOptions();
    }

    public function getCategoriesJsonUrl(): string
    {
        return $this->getUrl('*/*/categoriesJson');
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save');
    }

    public function getAutoMapUrl(): string
    {
        return $this->getUrl('*/*/autoMap');
    }

    public function getSearchTaxonomyUrl(): string
    {
        return $this->getUrl('*/*/searchTaxonomy');
    }

    /**
     * Get store categories as tree
     */
    public function getCategoriesTree(): array
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('level', ['gt' => 0])
            ->addOrderField('path');

        $tree = [];
        foreach ($collection as $category) {
            $tree[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'level' => $category->getLevel(),
                'path' => $this->_getCategoryPath($category),
            ];
        }

        return $tree;
    }

    protected function _getCategoryPath(Mage_Catalog_Model_Category $category): string
    {
        $path = [];
        $pathIds = explode('/', $category->getPath());
        array_shift($pathIds); // Remove root

        foreach ($pathIds as $id) {
            $parent = Mage::getModel('catalog/category')->load($id);
            if ($parent->getId() && $parent->getLevel() > 0) {
                $path[] = $parent->getName();
            }
        }

        return implode(' > ', $path);
    }
}
