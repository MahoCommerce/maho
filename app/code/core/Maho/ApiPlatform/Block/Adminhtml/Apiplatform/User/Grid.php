<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('apiplatformUserGrid');
        $this->setDefaultSort('user_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        $collection = Mage::getResourceModel('api/user_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $this->addColumn('user_id', [
            'header' => $this->__('ID'),
            'width'  => '50px',
            'index'  => 'user_id',
        ]);

        $this->addColumn('username', [
            'header' => $this->__('Username'),
            'index'  => 'username',
        ]);

        $this->addColumn('email', [
            'header' => $this->__('Email'),
            'index'  => 'email',
        ]);

        $this->addColumn('client_id', [
            'header' => $this->__('Client ID'),
            'index'  => 'client_id',
        ]);

        $this->addColumn('is_active', [
            'header'  => $this->__('Status'),
            'index'   => 'is_active',
            'type'    => 'options',
            'options' => [1 => $this->__('Active'), 0 => $this->__('Inactive')],
            'width'   => '80px',
        ]);

        $this->addColumn('created', [
            'header' => $this->__('Created'),
            'index'  => 'created',
            'type'   => 'datetime',
            'width'  => '150px',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['user_id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
