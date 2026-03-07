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

class Maho_Blog_Block_Adminhtml_Category_Edit_Tab_Posts extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('blogCategoryPostsGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(true);
    }

    public function getCategory(): ?Maho_Blog_Model_Category
    {
        return Mage::registry('blog_category');
    }

    #[\Override]
    protected function _addColumnFilterToCollection($column)
    {
        if ($column->getId() === 'in_category') {
            $postIds = $this->_getSelectedPostIds();
            if (empty($postIds)) {
                $postIds = [0];
            }
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('entity_id', ['in' => $postIds]);
            } elseif ($column->getFilter()->getValue() === '0') {
                $this->getCollection()->addFieldToFilter('entity_id', ['nin' => $postIds]);
            }
        } else {
            parent::_addColumnFilterToCollection($column);
        }
        return $this;
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('blog/post_collection');
        $collection->addAttributeToSelect('image');

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('in_category', [
            'header_css_class' => 'a-center',
            'type' => 'checkbox',
            'name' => 'in_category',
            'values' => $this->_getSelectedPostIds(),
            'align' => 'center',
            'index' => 'entity_id',
        ]);

        $this->addColumn('entity_id', [
            'header' => Mage::helper('blog')->__('ID'),
            'sortable' => true,
            'width' => '60px',
            'index' => 'entity_id',
        ]);

        $this->addColumn('title', [
            'header' => Mage::helper('blog')->__('Title'),
            'index' => 'title',
        ]);

        $this->addColumn('url_key', [
            'header' => Mage::helper('blog')->__('URL Key'),
            'index' => 'url_key',
            'width' => '150px',
        ]);

        $this->addColumn('is_active', [
            'header' => Mage::helper('blog')->__('Status'),
            'index' => 'is_active',
            'type' => 'options',
            'width' => '80px',
            'options' => [
                0 => Mage::helper('blog')->__('Disabled'),
                1 => Mage::helper('blog')->__('Enabled'),
            ],
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/postsGrid', ['_current' => true]);
    }

    protected function _getSelectedPostIds(): array
    {
        $postIds = $this->getPostsCategory();
        if (!is_array($postIds)) {
            $postIds = $this->getSelectedCategoryPosts();
        }
        return $postIds;
    }

    public function getSelectedCategoryPosts(): array
    {
        $category = $this->getCategory();
        if ($category && $category->getId()) {
            return $category->getPostIds();
        }
        return [];
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__('Posts');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('blog')->__('Category Posts');
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
