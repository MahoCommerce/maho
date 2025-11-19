<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Post_List extends Mage_Core_Block_Template
{
    protected ?Maho_Blog_Model_Resource_Post_Collection $_posts = null;

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager', 'blog.pager')
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
            // Get today's date for filtering published posts
            $today = Mage_Core_Model_Locale::today();

            // Get current page from request
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

            // Add publish date filter (show posts with no date or date <= today)
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
