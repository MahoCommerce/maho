<?php

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Edit_Tab_Role extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('User Role');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return $this->getTabLabel();
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    #[\Override]
    protected function _prepareForm(): static
    {
        $model = Mage::registry('api_user');
        $form = new Varien_Data_Form();
        $form->setHtmlIdPrefix('role_');

        $fieldset = $form->addFieldset('role_fieldset', [
            'legend' => $this->__('Assign Role'),
        ]);

        // Get available group roles
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $roleTable = $resource->getTableName('api/role');

        $roles = $read->fetchAll(
            $read->select()
                ->from($roleTable, ['role_id', 'role_name'])
                ->where('role_type = ?', 'G')
                ->order('role_name ASC'),
        );

        $options = [['value' => 0, 'label' => $this->__('-- No Role --')]];
        foreach ($roles as $role) {
            $options[] = ['value' => $role['role_id'], 'label' => $role['role_name']];
        }

        // Get current role assignment
        $currentRoleId = 0;
        if ($model->getId()) {
            $userRole = $read->fetchRow(
                $read->select()
                    ->from($roleTable, ['parent_id'])
                    ->where('user_id = ?', $model->getId())
                    ->where('role_type = ?', 'U'),
            );
            if ($userRole) {
                $currentRoleId = $userRole['parent_id'];
            }
        }

        $fieldset->addField('api_role', 'select', [
            'name'   => 'api_role',
            'label'  => $this->__('API Role'),
            'title'  => $this->__('API Role'),
            'values' => $options,
            'value'  => $currentRoleId,
        ]);

        $this->setForm($form);

        return parent::_prepareForm();
    }
}
