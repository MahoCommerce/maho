<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ApiPlatform_Block_Adminhtml_Apiplatform_User_Edit_Tab_Main extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return $this->__('User Info');
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
        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('user_');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Account Information'),
        ]);

        if ($model->getId()) {
            $fieldset->addField('user_id', 'hidden', ['name' => 'user_id']);
        }

        $fieldset->addField('username', 'text', [
            'name'     => 'username',
            'label'    => $this->__('Username'),
            'title'    => $this->__('Username'),
            'required' => true,
        ]);

        $fieldset->addField('firstname', 'text', [
            'name'     => 'firstname',
            'label'    => $this->__('First Name'),
            'title'    => $this->__('First Name'),
            'required' => false,
        ]);

        $fieldset->addField('lastname', 'text', [
            'name'     => 'lastname',
            'label'    => $this->__('Last Name'),
            'title'    => $this->__('Last Name'),
            'required' => false,
        ]);

        $fieldset->addField('email', 'text', [
            'name'     => 'email',
            'label'    => $this->__('Email'),
            'title'    => $this->__('Email'),
            'required' => true,
            'class'    => 'validate-email',
        ]);

        if ($model->getId()) {
            $fieldset->addField('api_key', 'password', [
                'name'  => 'api_key',
                'label' => $this->__('New API Key'),
                'title' => $this->__('New API Key'),
                'note'  => $this->__('Leave blank to keep the current key.'),
            ]);
        } else {
            $fieldset->addField('api_key', 'password', [
                'name'     => 'api_key',
                'label'    => $this->__('API Key'),
                'title'    => $this->__('API Key'),
                'required' => true,
            ]);
        }

        $fieldset->addField('is_active', 'select', [
            'name'    => 'is_active',
            'label'   => $this->__('Status'),
            'title'   => $this->__('Status'),
            'values'  => [
                ['value' => 1, 'label' => $this->__('Active')],
                ['value' => 0, 'label' => $this->__('Inactive')],
            ],
        ]);

        // OAuth2 Client Credentials section
        $oauth = $form->addFieldset('oauth_fieldset', [
            'legend' => $this->__('OAuth2 Client Credentials'),
        ]);

        // Fetch client_id directly from DB (model doesn't load it)
        $clientId = null;
        if ($model->getId()) {
            $resource = Mage::getSingleton('core/resource');
            $clientId = $resource->getConnection('core_read')->fetchOne(
                $resource->getConnection('core_read')->select()
                    ->from($resource->getTableName('api/user'), ['client_id'])
                    ->where('user_id = ?', $model->getId()),
            );
        }

        if ($clientId) {
            $oauth->addField('client_id_display', 'note', [
                'label' => $this->__('Client ID'),
                'text'  => '<code>' . $this->escapeHtml($clientId) . '</code>',
            ]);

            $oauth->addField('client_secret_display', 'note', [
                'label' => $this->__('Client Secret'),
                'text'  => $this->__('Hidden (stored hashed). Regenerate if needed.'),
            ]);

            $oauth->addField('regenerate_client_credentials', 'checkbox', [
                'name'    => 'regenerate_client_credentials',
                'label'   => $this->__('Regenerate Credentials'),
                'title'   => $this->__('Regenerate Credentials'),
                'value'   => 1,
                'note'    => $this->__('Check to generate new client_id and client_secret. The old credentials will stop working.'),
            ]);
        } else {
            $oauth->addField('oauth_note', 'note', [
                'label' => $this->__('Client Credentials'),
                'text'  => $this->__('OAuth2 client_id and client_secret will be generated when you save this user.'),
            ]);
        }

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
