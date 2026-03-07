<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Oauth_Block_Adminhtml_Oauth_Consumer_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Allow edit status
     *
     * @var bool
     */
    protected $_editAllow = false;

    /**
     * Construct grid block
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('consumerGrid');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
        $this->setDefaultSort('entity_id')
            ->setDefaultDir(Maho\Db\Select::SQL_DESC);

        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        $this->_editAllow = $session->isAllowed('system/oauth/consumer/edit');
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('oauth/consumer')->getCollection();
        $this->setCollection($collection);

        $roleTable = Mage::getSingleton('core/resource')->getTableName('api/role');
        $collection->getSelect()->joinLeft(
            ['api_role' => $roleTable],
            'main_table.api_role_id = api_role.role_id',
            ['role_name'],
        );

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', [
            'header' => Mage::helper('oauth')->__('ID'),
            'index' => 'entity_id',
            'width' => '50px',
        ]);

        $this->addColumn('name', [
            'header' => Mage::helper('oauth')->__('Consumer Name'),
            'index' => 'name',
            'escape' => true,
        ]);

        $this->addColumn('protocol', [
            'header' => Mage::helper('oauth')->__('Protocol'),
            'index' => 'api_role_id',
            'width' => '100px',
            'sortable' => false,
            'filter' => false,
            'frame_callback' => [$this, 'decorateProtocol'],
        ]);

        $this->addColumn('admin_api', [
            'header' => Mage::helper('oauth')->__('API Role'),
            'index' => 'role_name',
            'width' => '150px',
            'sortable' => false,
            'filter' => false,
            'frame_callback' => [$this, 'decorateApiRole'],
        ]);

        $this->addColumn('last_used_at', [
            'header' => Mage::helper('oauth')->__('Last Used'),
            'index' => 'last_used_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);

        $this->addColumn('expires_at', [
            'header' => Mage::helper('oauth')->__('Expires'),
            'index' => 'expires_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('oauth')->__('Created At'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '160px',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Render protocol column — OAuth 2 if api_role_id set, otherwise OAuth 1
     */
    public function decorateProtocol(string $value, Mage_Oauth_Model_Consumer $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $hasRole = !empty($row->getData('api_role_id'));
        if ($hasRole) {
            return '<span style="color:#2563eb;font-weight:600">OAuth 2</span>';
        }
        return '<span style="color:#6b7280">OAuth 1</span>';
    }

    /**
     * Render API role column
     */
    public function decorateApiRole(string $value, Mage_Oauth_Model_Consumer $row, Mage_Adminhtml_Block_Widget_Grid_Column $column, bool $isExport): string
    {
        $roleName = $row->getData('role_name');
        if ($roleName) {
            return '<span style="color:#16a34a">' . $this->escapeHtml($roleName) . '</span>';
        }
        return '<span style="color:#9ca3af">None</span>';
    }

    /**
     * Get grid URL
     *
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    /**
     * Get row URL
     *
     * @param Mage_Oauth_Model_Consumer $row
     * @return string|null
     */
    #[\Override]
    public function getRowUrl($row)
    {
        if ($this->_editAllow) {
            return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
        }
        return null;
    }
}
