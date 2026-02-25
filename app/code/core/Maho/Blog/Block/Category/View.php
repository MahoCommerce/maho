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

class Maho_Blog_Block_Category_View extends Mage_Core_Block_Template
{
    protected ?Maho_Blog_Model_Resource_Post_Collection $_posts = null;

    public function getCategory(): ?Maho_Blog_Model_Category
    {
        return Mage::registry('current_blog_category');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'blog.category.pager')
            ->setCollection($this->getPosts())
            ->setShowPerPage(false)
            ->setShowAmounts(false)
            ->setFrameLength(5);

        $this->setChild('pager', $pager);
        $this->getPosts()->load();

        return $this;
    }

    public function getPosts(): Maho_Blog_Model_Resource_Post_Collection
    {
        if (!$this->_posts) {
            $category = $this->getCategory();
            $today = Mage_Core_Model_Locale::today();
            $page = (int) $this->getRequest()->getParam('p', 1);
            $pageSize = Mage::helper('blog')->getPostsPerPage();

            $this->_posts = Mage::getResourceModel('blog/post_collection')
                ->addStoreFilter(Mage::app()->getStore())
                ->addFieldToFilter('is_active', 1)
                ->addAttributeToSelect('*')
                ->setOrder('publish_date', 'DESC')
                ->addAttributeToSort('created_at', 'DESC')
                ->setPageSize($pageSize)
                ->setCurPage($page);

            // Filter by category and all its descendants
            if ($category && $category->getId()) {
                $adapter = $this->_posts->getConnection();
                $categoryTable = $this->_posts->getTable('blog/category');

                // Get this category + all descendants via path
                $descendantSelect = $adapter->select()
                    ->from($categoryTable, ['entity_id'])
                    ->where('entity_id = ?', $category->getId())
                    ->orWhere('path LIKE ?', $category->getPath() . '/%');

                $this->_posts->getSelect()->join(
                    ['bpc' => $this->_posts->getTable('blog/post_category')],
                    'e.entity_id = bpc.post_id',
                    [],
                )->where(
                    'bpc.category_id IN (?)',
                    new Maho\Db\Expr($descendantSelect->assemble()),
                )->distinct();
            }

            $this->_posts->getSelect()->where(
                'publish_date IS NULL OR publish_date <= ?',
                $today,
            );
        }

        return $this->_posts;
    }

    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }
}
