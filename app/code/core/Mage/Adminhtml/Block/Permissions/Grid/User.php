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

/**
 * Users grid
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Permissions_Grid_User extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('customerGrid');
        $this->setSaveParametersInSession(true);
        $this->setDefaultSort('username');
        $this->setDefaultDir('asc');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection =  Mage::getModel('permissions/users')->getCollection();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('user_id', [
            'header'    => Mage::helper('adminhtml')->__('ID'),
            'width'     => 5,
            'align'     => 'right',
            'index'     => 'user_id',
        ]);
        $this->addColumn('username', [
            'header'    => Mage::helper('adminhtml')->__('User Name'),
            'index'     => 'username',
        ]);
        $this->addColumn('firstname', [
            'header'    => Mage::helper('adminhtml')->__('First Name'),
            'index'     => 'firstname',
        ]);
        $this->addColumn('lastname', [
            'header'    => Mage::helper('adminhtml')->__('Last Name'),
            'index'     => 'lastname',
        ]);
        $this->addColumn('email', [
            'header'    => Mage::helper('adminhtml')->__('Email'),
            'width'     => 40,
            'align'     => 'left',
            'index'     => 'email',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edituser', ['id' => $row->getUserId()]);
    }
}
