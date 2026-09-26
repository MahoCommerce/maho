<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * Catalog layer filter abstract
 *
 * @package    Mage_Catalog
 */
abstract class Mage_Catalog_Block_Layer_Filter_Abstract extends Mage_Core_Block_Template
{
    /**
     * Catalog Layer Filter Attribute model
     *
     * @var Mage_Catalog_Model_Layer_Filter_Attribute
     */
    protected $_filter;

    /**
     * Filter Model Name
     *
     * @var string
     */
    protected $_filterModelName;

    /**
     * Whether to display product count for layer navigation items
     * @var bool
     */
    protected $_displayProductCount = null;

    /**
     * Initialize filter template
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/layer/filter.phtml');
    }

    /**
     * Initialize filter model object
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    public function init()
    {
        $this->_initFilter();
        return $this;
    }

    /**
     * Init filter model object
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _initFilter()
    {
        if (!$this->_filterModelName) {
            Mage::throwException(Mage::helper('catalog')->__('Filter model name must be declared.'));
        }
        $this->_filter = Mage::getModel($this->_filterModelName)
            ->setLayer($this->getLayer());
        $this->_prepareFilter();

        $this->_filter->apply($this->getRequest(), $this);
        return $this;
    }

    /**
     * Prepare filter process
     *
     * @return Mage_Catalog_Block_Layer_Filter_Abstract
     */
    protected function _prepareFilter()
    {
        return $this;
    }

    /**
     * Retrieve name of the filter block
     *
     * @return string
     */
    public function getName()
    {
        return $this->_filter->getName();
    }

    /**
     * Retrieve filter items
     *
     * @return array
     */
    public function getItems()
    {
        return $this->_filter->getItems();
    }

    /**
     * Retrieve filter items count
     *
     * @return int
     */
    public function getItemsCount()
    {
        return $this->_filter->getItemsCount();
    }

    /**
     * Getter for $_displayProductCount
     * @return bool
     */
    public function shouldDisplayProductCount()
    {
        if ($this->_displayProductCount === null) {
            $this->_displayProductCount = Mage::helper('catalog')->shouldDisplayProductCountOnLayer();
        }
        return $this->_displayProductCount;
    }

    /**
     * Retrieve block html
     *
     * @return string
     */
    public function getHtml()
    {
        return parent::_toHtml();
    }

    public function getAttributeModel(): ?Mage_Eav_Model_Entity_Attribute
    {
        return $this->getData('attribute_model');
    }
}
