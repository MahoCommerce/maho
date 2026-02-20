<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Category_Tree extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/category/tree.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $addUrl = $this->getUrl('*/*/add', ['_current' => true, '_query' => false, 'id' => null]);

        $this->setChild(
            'add_sub_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('catalog')->__('Add Subcategory'),
                    'onclick'   => "addNew('$addUrl', false)",
                    'class'     => 'add',
                    'id'        => 'add_subcategory_button',
                    'disabled'  =>  !$this->canAddSubCategory(),
                ]),
        );

        $this->setChild(
            'add_root_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('catalog')->__('Add Root Category'),
                    'onclick'   => "addNew('$addUrl', true)",
                    'class'     => 'add' . ($this->canAddRootCategory() ? '' : ' no-display'),
                    'id'        => 'add_root_category_button',
                ]),
        );

        $this->setChild(
            'store_switcher',
            $this->getLayout()->createBlock('adminhtml/store_switcher')
                ->setSwitchUrl($this->getUrl('*/*/*', ['_current' => true, '_query' => false, 'store' => null]))
                ->setTemplate('store/switcher/enhanced.phtml'),
        );
        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getAddRootButtonHtml()
    {
        return $this->getChildHtml('add_root_button');
    }

    /**
     * @return string
     */
    public function getAddSubButtonHtml()
    {
        return $this->getChildHtml('add_sub_button');
    }

    /**
     * @return string
     */
    public function getStoreSwitcherHtml()
    {
        return $this->getChildHtml('store_switcher');
    }

    /**
     * @return string
     */
    public function getSwitchTreeUrl()
    {
        return $this->getUrl(
            '*/catalog_category/tree',
            ['_current' => true, 'store' => null, '_query' => false, 'id' => null, 'parent' => null],
        );
    }

    /**
     * @return string
     */
    public function getMoveUrl()
    {
        return $this->getUrl('*/catalog_category/move', ['store' => $this->getRequest()->getParam('store')]);
    }

    /**
     * Returns root node and sets 'checked' flag (if necessary)
     *
     * @return \Maho\Data\Tree\Node
     */
    public function getRootNode()
    {
        $root = $this->getRoot();
        if ($root && $this->getCategory()) {
            if ($selected = $root->getTree()->getNodeById($this->getCategoryId())) {
                $selected->setChecked(true);
            } elseif ($parent = $root->getTree()->getNodeById($this->getCategory()->getParentId())) {
                $parent->setChecked(true);
            }
        }
        return $root;
    }

    /**
     * Get root category information
     */
    public function getRootTreeParameters(): array
    {
        $root = $this->getRootNode();
        return [
            'data' => $this->getTree(),
            'parameters' => [
                'text'         => $this->buildNodeName($root),
                'allowDrag'    => false,
                'allowDrop'    => (bool) $root->getIsVisible(),
                'id'           => (int) $root->getId(),
                'store_id'     => (int) $this->getStore()->getId(),
                'category_id'  => (int) $this->getCategory()->getId(),
                'checked'      => (bool) $root->getChecked(),
                'root_visible' => (bool) $root->getIsVisible(),
                'can_add_root' => (bool) $this->canAddRootCategory(),
                'expanded'     => $this->getRecursionLevel() === 0,
            ],
        ];
    }

    #[\Override]
    protected function _getNodeJson($node, $level = 0)
    {
        // create a node from data array
        if (is_array($node)) {
            $node = new \Maho\Data\Tree\Node($node, 'entity_id', new \Maho\Data\Tree());
        }

        $item = parent::_getNodeJson($node, $level);

        $allowMove = (bool) $this->_isCategoryMoveable($node);
        $isRoot = in_array($node->getEntityId(), $this->getRootIds());

        // disallow drag if it's first level and category is root of a store
        $item['allowDrag'] = $allowMove && !$isRoot && $node->getLevel() > 1;

        return $item;
    }

    /**
     * Check if the node can be moved
     *
     * @param \Maho\DataObject $node
     * @return bool
     */
    protected function _isCategoryMoveable($node)
    {
        $options = new \Maho\DataObject([
            'is_moveable' => true,
            'category' => $node,
        ]);

        Mage::dispatchEvent(
            'adminhtml_catalog_category_tree_is_moveable',
            ['options' => $options],
        );

        return $options->getIsMoveable();
    }

    /**
     * Check availability of adding root category
     *
     * @return bool
     */
    public function canAddRootCategory()
    {
        if ($this->getStore()->getId() !== 0) {
            return false;
        }

        $options = new \Maho\DataObject(['is_allow' => true]);
        Mage::dispatchEvent('adminhtml_catalog_category_tree_can_add_root_category', [
            'category' => $this->getCategory(),
            'options'  => $options,
            'store'    => $this->getStore()->getId(),
        ]);

        return (bool) $options->getIsAllow();
    }

    /**
     * Check availability of adding sub category
     *
     * @return bool
     */
    public function canAddSubCategory()
    {
        $options = new \Maho\DataObject(['is_allow' => true]);
        Mage::dispatchEvent('adminhtml_catalog_category_tree_can_add_sub_category', [
            'category' => $this->getCategory(),
            'options'  => $options,
            'store'    => $this->getStore()->getId(),
        ]);

        return (bool) $options->getIsAllow();
    }
}
