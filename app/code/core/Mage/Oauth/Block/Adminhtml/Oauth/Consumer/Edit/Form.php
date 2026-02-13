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

class Mage_Oauth_Block_Adminhtml_Oauth_Consumer_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Consumer model
     *
     * @var Mage_Oauth_Model_Consumer
     */
    protected $_model;

    /**
     * Get consumer model
     *
     * @return Mage_Oauth_Model_Consumer
     */
    public function getModel()
    {
        if ($this->_model === null) {
            $this->_model = Mage::registry('current_consumer');
        }
        return $this->_model;
    }

    #[\Override]
    protected function _prepareForm()
    {
        $model = $this->getModel();
        $form = new \Maho\Data\Form([
            'id' => 'edit_form', 'action' => $this->getData('action'), 'method' => 'post',
        ]);

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('oauth')->__('Consumer Information'), 'class' => 'fieldset-wide',
        ]);

        if ($model->getId()) {
            $fieldset->addField('id', 'hidden', ['name' => 'id', 'value' => $model->getId()]);
        }
        $fieldset->addField('name', 'text', [
            'name'      => 'name',
            'label'     => Mage::helper('oauth')->__('Name'),
            'title'     => Mage::helper('oauth')->__('Name'),
            'required'  => true,
            'value'     => $model->getName(),
        ]);

        $fieldset->addField('key', 'text', [
            'name'      => 'key',
            'label'     => Mage::helper('oauth')->__('Key'),
            'title'     => Mage::helper('oauth')->__('Key'),
            'disabled'  => true,
            'required'  => true,
            'value'     => $model->getKey(),
        ]);

        $fieldset->addField('secret', 'text', [
            'name'      => 'secret',
            'label'     => Mage::helper('oauth')->__('Secret'),
            'title'     => Mage::helper('oauth')->__('Secret'),
            'disabled'  => true,
            'required'  => true,
            'value'     => $model->getSecret(),
        ]);

        $fieldset->addField('callback_url', 'text', [
            'name'      => 'callback_url',
            'label'     => Mage::helper('oauth')->__('Callback URL'),
            'title'     => Mage::helper('oauth')->__('Callback URL'),
            'required'  => false,
            'value'     => $model->getCallbackUrl(),
        ]);

        $fieldset->addField('rejected_callback_url', 'text', [
            'name'      => 'rejected_callback_url',
            'label'     => Mage::helper('oauth')->__('Rejected Callback URL'),
            'title'     => Mage::helper('oauth')->__('Rejected Callback URL'),
            'required'  => false,
            'value'     => $model->getRejectedCallbackUrl(),
        ]);

        $fieldset->addField(
            'current_password',
            'obscure',
            [
                'name'  => 'current_password',
                'label' => Mage::helper('oauth')->__('Current Admin Password'),
                'title' => Mage::helper('oauth')->__('Current Admin Password'),
                'required' => true,
            ],
        );

        // Admin API Access fieldset
        $adminFieldset = $form->addFieldset('admin_fieldset', [
            'legend' => Mage::helper('oauth')->__('Admin API Access'),
            'class' => 'fieldset-wide',
        ]);

        // Role dropdown
        $roles = [['value' => '', 'label' => Mage::helper('oauth')->__('-- No API Access --')]];
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $roleTable = Mage::getSingleton('core/resource')->getTableName('api/role');
        $roleRows = $read->fetchAll(
            $read->select()
                ->from($roleTable, ['role_id', 'role_name'])
                ->where('role_type = ?', 'G')
                ->order('role_name ASC'),
        );
        foreach ($roleRows as $row) {
            $roles[] = ['value' => $row['role_id'], 'label' => $row['role_name']];
        }

        $adminFieldset->addField('api_role_id', 'select', [
            'name' => 'api_role_id',
            'label' => Mage::helper('oauth')->__('API Role'),
            'values' => $roles,
            'value' => $model->getData('api_role_id'),
            'note' => Mage::helper('oauth')->__('Assign an API role to grant permissions. Manage roles at System > Web Services > REST/GraphQL - Roles.'),
        ]);

        $adminFieldset->addField('store_ids', 'multiselect', [
            'name' => 'store_ids',
            'label' => Mage::helper('oauth')->__('Store Access'),
            'values' => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
            'value' => $this->_getStoreIdsValue($model),
            'note' => Mage::helper('oauth')->__('Select stores this consumer can access. Leave empty for all stores.'),
        ]);

        $adminFieldset->addField('expires_at', 'date', [
            'name' => 'expires_at',
            'label' => Mage::helper('oauth')->__('Expires At'),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
            'value' => $model->getExpiresAt(),
            'note' => Mage::helper('oauth')->__('Leave empty for no expiration'),
        ]);

        $form->setAction($this->getUrl('*/*/save'));
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get store IDs value for the multiselect field
     *
     * @return array
     */
    protected function _getStoreIdsValue(Mage_Oauth_Model_Consumer $model): array
    {
        $storeIds = $model->getStoreIds();
        if (empty($storeIds) || $storeIds === 'all') {
            return [];
        }
        $decoded = json_decode($storeIds, true);
        return is_array($decoded) ? $decoded : [];
    }
}
