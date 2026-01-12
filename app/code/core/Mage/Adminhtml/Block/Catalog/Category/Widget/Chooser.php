<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Category_Widget_Chooser extends Mage_Adminhtml_Block_Catalog_Category_Abstract
{
    protected $_selectedCategories = [];

    /**
     * Block construction
     * Defines tree template and init tree params
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/category/widget/tree.phtml');
        $this->_withProductCount = false;
    }

    /**
     * Setter
     *
     * @param array $selectedCategories
     * @return $this
     */
    public function setSelectedCategories($selectedCategories)
    {
        if (!is_array($selectedCategories)) {
            $selectedCategories = [$selectedCategories];
        }
        $this->_selectedCategories = array_filter($selectedCategories);
        return $this;
    }

    /**
     * Getter
     *
     * @return array
     */
    public function getSelectedCategories()
    {
        return $this->_selectedCategories;
    }

    /**
     * Prepare chooser element HTML
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element Form Element
     * @return \Maho\Data\Form\Element\AbstractElement
     */
    public function prepareElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $uniqId = Mage::helper('core')->uniqHash($element->getId());
        $sourceUrl = $this->getUrl(
            '*/catalog_category_widget/chooser',
            ['uniq_id' => $uniqId, 'use_massaction' => false],
        );

        $chooser = $this->getLayout()->createBlock('widget/adminhtml_widget_chooser')
            ->setElement($element)
            ->setTranslationHelper($this->getTranslationHelper())
            ->setConfig($this->getConfig())
            ->setFieldsetId($this->getFieldsetId())
            ->setSourceUrl($sourceUrl)
            ->setUniqId($uniqId);

        if ($element->getValue()) {
            $value = explode('/', $element->getValue());
            $categoryId = false;
            if (isset($value[0]) && isset($value[1]) && $value[0] == 'category') {
                $categoryId = $value[1];
            }
            if ($categoryId) {
                $label = $this->_getModelAttributeByEntityId('catalog/category', 'name', $categoryId);
                $chooser->setLabel($label);
            }
        }

        $element->setData('after_element_html', $chooser->toHtml());
        return $element;
    }

    /**
     * Retrieve model attribute value
     *
     * @param string $modelType Model Type
     * @param string $attributeName Attribute Name
     * @param string $entityId Form Entity ID
     * @return string
     */
    protected function _getModelAttributeByEntityId($modelType, $attributeName, $entityId)
    {
        $result = '';
        $model = Mage::getModel($modelType)
            ->getCollection()
            ->addAttributeToSelect($attributeName)
            ->addAttributeToFilter('entity_id', $entityId)
            ->getFirstItem();
        if ($model) {
            $result = $model->getData($attributeName);
        }
        return $result;
    }

    /**
     * Category Tree node onClick listener js function
     *
     * @return string
     */
    public function getNodeClickListener()
    {
        if ($this->getData('node_click_listener')) {
            return $this->getData('node_click_listener');
        }
        if ($this->getUseMassaction()) {
            return <<<JS
                function (selected) {
                    this.dispatchEvent(new CustomEvent('category:changed', {
                        bubbles: true,
                        detail: { selected },
                    }));
                }
            JS;
        }
        $chooserJsObject = $this->getId();
        return <<<JS
                function ([node]) {
                    {$chooserJsObject}.setElementValue("category/" + node.id);
                    {$chooserJsObject}.setElementLabel(node.text);
                    {$chooserJsObject}.close();
                }
            JS;
    }

    #[\Override]
    public function getRoot($parentNodeCategory = null, $recursionLevel = null)
    {
        if ($parentNodeCategory === null && $this->getSelectedCategories()) {
            return $this->getRootByIds($this->getSelectedCategories(), $recursionLevel);
        }
        return parent::getRoot($parentNodeCategory, $recursionLevel);
    }

    #[\Override]
    protected function _getNodeJson($node, $level = 0)
    {
        $item = parent::_getNodeJson($node, $level);
        if (in_array($node->getId(), $this->getSelectedCategories())) {
            $item['checked'] = true;
        }
        if ($this->getIsAnchorOnly() && !$node->getIsAnchor()) {
            $item['selectable'] = false;
        }
        $item['is_anchor'] = (bool) $node->getIsAnchor();
        $item['url_key'] = $node->getData('url_key');
        return $item;
    }

    #[\Override]
    protected function _isParentSelectedCategory($node)
    {
        $allChildrenIds = array_keys($node->getAllChildNodes());
        $selectedChildren = array_intersect($this->getSelectedCategories(), $allChildrenIds);
        return count($selectedChildren) > 0;
    }

    /**
     * Adds some extra params to categories collection
     *
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    #[\Override]
    public function getCategoryCollection()
    {
        return parent::getCategoryCollection()->addAttributeToSelect('url_key')->addAttributeToSelect('is_anchor');
    }

    /**
     * Tree JSON source URL
     *
     * @param null $expanded deprecated
     * @return string
     */
    #[\Override]
    public function getLoadTreeUrl($expanded = null)
    {
        return $this->getUrl('*/catalog_category_widget/categoriesJson', [
            'uniq_id' => $this->getId(),
            'is_anchor_only' => $this->getIsAnchorOnly(),
            'use_massaction' => $this->getUseMassaction(),
        ]);
    }
}
