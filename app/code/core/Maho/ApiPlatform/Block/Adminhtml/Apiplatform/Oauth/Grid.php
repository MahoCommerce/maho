<?php

/**
 * Every OAuth client that has been registered, with the grants it holds.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('apiplatformOauthGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): static
    {
        /** @var Maho_ApiPlatform_Model_Resource_Oauth_Client_Collection $collection */
        $collection = Mage::getResourceModel('apiplatform/oauth_client_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): static
    {
        $this->addColumn('client_name', [
            'header' => $this->__('Application'),
            'index' => 'client_name',
        ]);

        $this->addColumn('client_id', [
            'header' => $this->__('Client ID'),
            'index' => 'client_id',
            'width' => '260px',
        ]);

        $this->addColumn('is_trusted', [
            'header' => $this->__('Verified'),
            'index' => 'is_trusted',
            'type' => 'options',
            'options' => [1 => $this->__('Yes'), 0 => $this->__('No')],
            'width' => '80px',
        ]);

        $this->addColumn('created_at', [
            'header' => $this->__('Registered'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);

        $this->addColumn('last_used_at', [
            'header' => $this->__('Last Used'),
            'index' => 'last_used_at',
            'type' => 'datetime',
            'width' => '150px',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return '';
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
