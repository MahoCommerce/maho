<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Dashboard_Searches_Last extends Mage_Adminhtml_Block_Dashboard_Grid
{
    protected $_collection;

    public function __construct()
    {
        parent::__construct();
        $this->setId('lastSearchGrid');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        if (!$this->isModuleEnabled('Mage_CatalogSearch')) {
            return parent::_prepareCollection();
        }
        $this->_collection = Mage::getModel('catalogsearch/query')
            ->getResourceCollection();
        $this->_collection->setRecentQueryFilter();

        if ($this->getRequest()->getParam('store')) {
            $this->_collection->addFieldToFilter('store_id', $this->getRequest()->getParam('store'));
        } elseif ($this->getRequest()->getParam('website')) {
            $storeIds = Mage::app()->getWebsite($this->getRequest()->getParam('website'))->getStoreIds();
            $this->_collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        } elseif ($this->getRequest()->getParam('group')) {
            $storeIds = Mage::app()->getGroup($this->getRequest()->getParam('group'))->getStoreIds();
            $this->_collection->addFieldToFilter('store_id', ['in' => $storeIds]);
        }

        $this->setCollection($this->_collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('search_query', [
            'header'    => $this->__('Search Term'),
            'sortable'  => false,
            'index'     => 'query_text',
            'renderer'  => 'adminhtml/dashboard_searches_renderer_searchquery',
        ]);

        $this->addColumn('num_results', [
            'header'    => $this->__('Results'),
            'sortable'  => false,
            'index'     => 'num_results',
            'type'      => 'number',
        ]);

        $this->addColumn('popularity', [
            'header'    => $this->__('Number of Uses'),
            'sortable'  => false,
            'index'     => 'popularity',
            'type'      => 'number',
        ]);

        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/catalog_search/edit', ['id' => $row->getId()]);
    }
}
