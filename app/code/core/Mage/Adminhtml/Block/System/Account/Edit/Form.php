<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Account_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $userId = Mage::getSingleton('admin/session')->getUser()->getId();
        $user = Mage::getModel('admin/user')
            ->load($userId);
        $user->unsetData('password');

        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Account Information'),
        ]);

        $fieldset->addField('username', 'text', [
            'name' => 'username',
            'label' => Mage::helper('adminhtml')->__('User Name'),
            'title' => Mage::helper('adminhtml')->__('User Name'),
            'required' => true,
        ]);

        $fieldset->addField('firstname', 'text', [
            'name' => 'firstname',
            'label' => Mage::helper('adminhtml')->__('First Name'),
            'title' => Mage::helper('adminhtml')->__('First Name'),
            'required' => true,
        ]);

        $fieldset->addField('lastname', 'text', [
            'name' => 'lastname',
            'label' => Mage::helper('adminhtml')->__('Last Name'),
            'title' => Mage::helper('adminhtml')->__('Last Name'),
            'required' => true,
        ]);

        $fieldset->addField('user_id', 'hidden', [
            'name' => 'user_id',
        ]);

        $fieldset->addField('email', 'text', [
            'name' => 'email',
            'label' => Mage::helper('adminhtml')->__('Email'),
            'title' => Mage::helper('adminhtml')->__('User Email'),
            'required' => true,
        ]);

        $locales = Mage::app()->getLocale()->getTranslatedOptionLocales();
        $locales = array_column($locales, 'label', 'value');
        array_unshift($locales, '');
        $fieldset->addField('backend_locale', 'select', [
            'name' => 'backend_locale',
            'label' => Mage::helper('adminhtml')->__('Backend Locale'),
            'class' => 'input-select',
            'options' => $locales,
            'required' => false,
        ]);

        $fieldset->addField('current_password', 'obscure', [
            'name' => 'current_password',
            'label' => Mage::helper('adminhtml')->__('Current Admin Password'),
            'title' => Mage::helper('adminhtml')->__('Current Admin Password'),
            'autocomplete' => 'current-password',
            'required' => true,
        ]);

        $fieldset->addField('password_change', 'checkbox', [
            'label' => Mage::helper('adminhtml')->__('Change Password'),
            'name' => 'password_change',
        ]);

        $minAdminPasswordLength = Mage::getModel('admin/user')->getMinAdminPasswordLength();
        $fieldset->addField('password', 'password', [
            'name' => 'new_password',
            'label' => Mage::helper('adminhtml')->__('New Password'),
            'title' => Mage::helper('adminhtml')->__('New Password'),
            'class' => 'input-text validate-admin-password min-admin-pass-length-' . $minAdminPasswordLength,
            'autocomplete' => 'new-password',
            'note' => Mage::helper('adminhtml')
                ->__('Password must be at least of %d characters.', $minAdminPasswordLength),
        ]);

        $fieldset->addField('confirmation', 'password', [
            'name' => 'password_confirmation',
            'label' => Mage::helper('adminhtml')->__('New Password Confirmation'),
            'class' => 'input-text validate-cpassword',
            'autocomplete' => 'new-password',
        ]);

        $fieldset = $form->addFieldset('passkey_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Passkey Authentication'),
        ]);

        $passkeyEnabled = $user->isPasskeyEnabled();
        if ($passkeyEnabled) {
            $passkeyStatusHtml = '<strong>' . Mage::helper('adminhtml')->__('Passkey Active') . '</strong>';
            $passkeyExtraHtml = [
                $this->getButtonHtml($this->__('Register New Passkey'), null, '', 'register-passkey-btn'),
                $this->getButtonHtml($this->__('Remove Passkey'), null, '', 'remove-passkey-btn'),
                '<input id="passkey_enabled" type="hidden" value="1">',
            ];
        } else {
            $passkeyStatusHtml = Mage::helper('adminhtml')->__('No Passkey Created');
            $passkeyExtraHtml = [
                $this->getButtonHtml($this->__('Register Passkey'), null, '', 'register-passkey-btn'),
                $this->getButtonHtml($this->__('Remove Passkey'), null, 'no-display', 'remove-passkey-btn'),
                '<input id="passkey_enabled" type="hidden" value="0">',
            ];
        }

        $fieldset->addField('passkey_status', 'note', [
            'label' => Mage::helper('adminhtml')->__('Status'),
            'text'  => $passkeyStatusHtml,
        ]);
        $fieldset->addField('passkey_extra', 'note', [
            'text'  => join("\n", $passkeyExtraHtml),
        ]);
        $fieldset->addField('passkey_value', 'hidden', [
            'name'  => 'passkey_value',
        ]);
        $fieldset->addField('password_enabled', 'boolean', [
            'label' => Mage::helper('adminhtml')->__('Enable Password Authentication'),
            'note' => Mage::helper('adminhtml')->__('Registering a passkey automatically disables password authentication. For security reasons, we recommend keeping it disabled, though you can re-enable it if necessary.'),
            'name' => 'password_enabled',
        ]);

        $fieldset = $form->addFieldset('twofa_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Two-Factor Authentication'),
        ]);
        $fieldset->addField('twofa_enabled', 'boolean', [
            'label' => Mage::helper('adminhtml')->__('Enable 2FA'),
            'name' => 'twofa_enabled',
        ]);

        if (!$user->getTwofaEnabled()) {
            if (!$user->getTwofaSecret()) {
                $secret = Mage::helper('admin/auth')->getTwofaSecret();
                $user->setTwofaSecret($secret)->save();
            }
            $fieldset->addField('twofa_qr', 'note', [
                'label' => Mage::helper('adminhtml')->__('QR Code'),
                'text' => Mage::helper('admin/auth')->getTwofaQRCode($user->getUsername(), $user->getTwofaSecret()),
                'note' => Mage::helper('adminhtml')->__('Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)'),
            ]);
            $fieldset->addField('twofa_verification_code', 'text', [
                'name' => 'twofa_verification_code',
                'label' => Mage::helper('adminhtml')->__('Verification Code'),
                'title' => Mage::helper('adminhtml')->__('Verification Code'),
                'required' => true,
                'class' => 'validate-number',
                'note' => Mage::helper('adminhtml')->__('Enter the 6-digit code from your authenticator app'),
            ]);
        }

        $form->setValues($user->getData());
        $form->setAction($this->getUrl('*/system_account/save'));
        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('edit_form');

        $form->addField('form_script', 'note', [
            'text'  => <<<JS
                <script>
                    new MahoAdminhtmlSystemAccountController({
                        passkeyRegisterStartUrl: '{$this->getUrl('*/index/passkeyregisterstart')}',
                    });
                </script>
            JS,
        ]);

        $this->setForm($form);

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
        $block = $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence');
        $block
            ->addFieldDependence('password', 'password_change', true)
            ->addFieldDependence('confirmation', 'password_change', true)
            ->addFieldDependence('password_enabled', 'passkey_enabled', '1')
            ->addFieldDependence('twofa_qr', 'twofa_enabled', '1')
            ->addFieldDependence('twofa_verification_code', 'twofa_enabled', '1');

        $this->setChild('form_after', $block);

        return parent::_prepareForm();
    }
}
