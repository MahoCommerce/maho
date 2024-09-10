<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * List of customers tagged a product
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_Tag_Customer extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('tag_customers_grid');
        $this->setDefaultSort('firstname');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        if (Mage::helper('catalog')->isModuleEnabled('Mage_Tag')) {
            $collection = Mage::getModel('tag/tag')
                ->getCustomerCollection()
                ->addProductFilter($this->getProductId())
                ->addGroupByTag()
                ->addDescOrder();

            $this->setCollection($collection);
        }
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _afterLoadCollection()
    {
        return parent::_afterLoadCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('firstname', [
            'header'    => Mage::helper('catalog')->__('First Name'),
            'index'     => 'firstname',
        ]);

        $this->addColumn('middlename', [
            'header'        => Mage::helper('catalog')->__('Middle Name'),
            'index'         => 'middlename',
        ]);

        $this->addColumn('lastname', [
            'header'        => Mage::helper('catalog')->__('Last Name'),
            'index'         => 'lastname',
        ]);

        $this->addColumn('email', [
            'header'        => Mage::helper('catalog')->__('Email'),
            'index'         => 'email',
        ]);

        $this->addColumn('name', [
            'header'        => Mage::helper('catalog')->__('Tag Name'),
            'index'         => 'name',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/customer/edit', ['id' => $row->getEntityId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/catalog_product/tagCustomerGrid', [
            '_current' => true,
            'id'       => $this->getProductId(),
            'product_id' => $this->getProductId(),
        ]);
    }
}
