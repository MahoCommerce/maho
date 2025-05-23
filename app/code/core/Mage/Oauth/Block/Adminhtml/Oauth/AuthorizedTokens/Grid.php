<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Oauth_Block_Adminhtml_Oauth_AuthorizedTokens_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Construct grid block
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('authorizedTokensGrid');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
        $this->setDefaultSort('entity_id')
            ->setDefaultDir(Varien_Db_Select::SQL_DESC);
    }

    /**
     * Prepare collection
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareCollection()
    {
        /** @var Mage_Oauth_Model_Resource_Token_Collection $collection */
        $collection = Mage::getModel('oauth/token')->getCollection();
        $collection->joinConsumerAsApplication()
            ->addFilterByType(Mage_Oauth_Model_Token::TYPE_ACCESS);
        $this->setCollection($collection);

        parent::_prepareCollection();
        return $this;
    }

    /**
     * Prepare columns
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', [
            'header'    => Mage::helper('oauth')->__('ID'),
            'index'     => 'entity_id',
        ]);

        $this->addColumn('name', [
            'header'    => $this->__('Application Name'),
            'index'     => 'name',
            'escape'    => true,
        ]);

        $this->addColumn('type', [
            'header'    => $this->__('User Type'),
            //'index'     => array('customer_id', 'admin_id'),
            'options'   => [0 => $this->__('Admin'), 1 => $this->__('Customer')],
            'frame_callback' => [$this, 'decorateUserType'],
        ]);

        $this->addColumn('user_id', [
            'header'    => $this->__('User ID'),
            //'index'     => array('customer_id', 'admin_id'),
            'frame_callback' => [$this, 'decorateUserId'],
        ]);

        /** @var Mage_Adminhtml_Model_System_Config_Source_Yesno $sourceYesNo */
        $sourceYesNo = Mage::getSingleton('adminhtml/system_config_source_yesno');
        $this->addColumn('revoked', [
            'header'    => $this->__('Revoked'),
            'index'     => 'revoked',
            'width'     => '100px',
            'type'      => 'options',
            'options'   => $sourceYesNo->toArray(),
        ]);

        parent::_prepareColumns();
        return $this;
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
     * Get revoke URL
     *
     * @param Mage_Oauth_Model_Token $row
     * @return string|null
     */
    public function getRevokeUrl($row)
    {
        return $this->getUrl('*/*/revoke', ['id' => $row->getId()]);
    }

    /**
     * Get delete URL
     *
     * @param Mage_Oauth_Model_Token $row
     * @return string|null
     */
    public function getDeleteUrl($row)
    {
        return $this->getUrl('*/*/delete', ['id' => $row->getId()]);
    }

    /**
     * Add mass-actions to grid
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        if (!$this->_isAllowed()) {
            return $this;
        }

        $this->setMassactionIdField('id');
        $block = $this->getMassactionBlock();

        $block->setFormFieldName('items');
        $block->addItem('enable', [
            'label' => Mage::helper('index')->__('Enable'),
            'url'   => $this->getUrl('*/*/revoke', ['status' => 0]),
        ]);
        $block->addItem('revoke', [
            'label' => Mage::helper('index')->__('Revoke'),
            'url'   => $this->getUrl('*/*/revoke', ['status' => 1]),
        ]);
        $block->addItem('delete', [
            'label' => Mage::helper('index')->__('Delete'),
            'url'   => $this->getUrl('*/*/delete'),
        ]);

        return $this;
    }

    /**
     * Decorate user type column
     *
     * @param string $value
     * @param Mage_Oauth_Model_Token $row
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @param bool $isExport
     * @return mixed
     */
    public function decorateUserType($value, $row, $column, $isExport)
    {
        $options = $column->getOptions();

        $value = ($row->getCustomerId()) ? $options[1] : $options[0];
        return $value;
    }

    /**
     * Decorate user type column
     *
     * @param string $value
     * @param Mage_Oauth_Model_Token $row
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @param bool $isExport
     * @return mixed
     */
    public function decorateUserId($value, $row, $column, $isExport)
    {
        $value = $row->getCustomerId() ?: $row->getAdminId();
        return $value;
    }

    /**
     * Check admin permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('system/oauth/authorizedTokens');
    }
}
