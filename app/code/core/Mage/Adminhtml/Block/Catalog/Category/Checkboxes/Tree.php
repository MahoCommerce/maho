<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Category_Checkboxes_Tree extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    /** @var list<int> */
    protected $_selectedIds = [];

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setTemplate('catalog/category/checkboxes/tree.phtml');
        return $this;
    }

    /**
     * @return list<int>
     */
    public function getCategoryIds()
    {
        return $this->_selectedIds;
    }

    /**
     * @param array $ids
     * @return $this
     */
    public function setCategoryIds($ids)
    {
        if (empty($ids)) {
            $ids = [];
        } elseif (!is_array($ids)) {
            $ids = [$ids];
        }
        foreach ($ids as $key => &$id) {
            $id = (int) $id;
            if ($id <= 0) {
                unset($ids[$key]);
            }
        }
        $this->_selectedIds = array_unique($ids);
        return $this;
    }

    #[\Override]
    public function getRoot($parentNodeCategory = null, $recursionLevel = null)
    {
        if ($parentNodeCategory === null && $this->getCategoryIds()) {
            return $this->getRootByIds($this->getCategoryIds(), $recursionLevel);
        }
        return parent::getRoot($parentNodeCategory, $recursionLevel);
    }

    #[\Override]
    protected function _getNodeJson($node, $level = 1)
    {
        $item = parent::_getNodeJson($node, $level);
        if (in_array($node->getId(), $this->getCategoryIds())) {
            $item['checked'] = true;
        }
        return $item;
    }

    #[\Override]
    protected function _isParentSelectedCategory($node)
    {
        $allChildrenIds = array_keys($node->getAllChildNodes());
        $selectedChildren = array_intersect($this->getCategoryIds(), $allChildrenIds);

        return count($selectedChildren) > 0;
    }
}
