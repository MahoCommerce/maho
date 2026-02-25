<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Category_Sidebar extends Mage_Core_Block_Template
{
    protected ?array $_categoryTree = null;

    #[\Override]
    protected function _beforeToHtml(): self
    {
        if (!Mage::helper('blog')->areCategoriesEnabled() || empty($this->getCategoryTree())) {
            // Switch to 1-column layout when sidebar has no content
            $root = $this->getLayout()->getBlock('root');
            if ($root) {
                $root->setTemplate('page/1column.phtml');
            }
        }

        return $this;
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::helper('blog')->areCategoriesEnabled() || empty($this->getCategoryTree())) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Get category tree as nested array
     *
     * @return array<int, array{category: Maho_Blog_Model_Category, children: array}>
     */
    public function getCategoryTree(): array
    {
        if ($this->_categoryTree === null) {
            $collection = Mage::getResourceModel('blog/category_collection')
                ->addRootFilter()
                ->addActiveFilter()
                ->addStoreFilter(Mage::app()->getStore())
                ->setOrder('position', 'ASC')
                ->setOrder('name', 'ASC');

            // Build tree from flat collection
            $items = [];
            $children = [];
            foreach ($collection as $category) {
                $items[$category->getId()] = $category;
                $parentId = (int) $category->getParentId();
                $children[$parentId][] = $category->getId();
            }

            $this->_categoryTree = $this->_buildTree($items, $children, Maho_Blog_Model_Category::ROOT_PARENT_ID);
        }

        return $this->_categoryTree;
    }

    /**
     * Recursively build tree structure
     */
    protected function _buildTree(array $items, array $children, int $parentId): array
    {
        $tree = [];
        if (isset($children[$parentId])) {
            foreach ($children[$parentId] as $childId) {
                if (isset($items[$childId])) {
                    $tree[] = [
                        'category' => $items[$childId],
                        'children' => $this->_buildTree($items, $children, $childId),
                    ];
                }
            }
        }
        return $tree;
    }

    public function getCurrentCategoryId(): ?int
    {
        $category = Mage::registry('current_blog_category');
        return $category ? (int) $category->getId() : null;
    }
}
