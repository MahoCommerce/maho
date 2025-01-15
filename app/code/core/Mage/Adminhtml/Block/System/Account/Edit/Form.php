<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml edit admin user account form
 *
 * @category   Mage
 * @package    Mage_Adminhtml
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

        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('adminhtml')->__('Account Information')]);

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

        $fieldset->addField('current_password', 'obscure', [
            'name' => 'current_password',
            'label' => Mage::helper('adminhtml')->__('Current Admin Password'),
            'title' => Mage::helper('adminhtml')->__('Current Admin Password'),
            'required' => true,
        ]);

        $minAdminPasswordLength = Mage::getModel('admin/user')->getMinAdminPasswordLength();
        $fieldset->addField('password', 'password', [
            'name' => 'new_password',
            'label' => Mage::helper('adminhtml')->__('New Password'),
            'title' => Mage::helper('adminhtml')->__('New Password'),
            'class' => 'input-text validate-admin-password min-admin-pass-length-' . $minAdminPasswordLength,
            'note' => Mage::helper('adminhtml')
                ->__('Password must be at least of %d characters.', $minAdminPasswordLength),
        ]);

        $fieldset->addField('confirmation', 'password', [
            'name' => 'password_confirmation',
            'label' => Mage::helper('adminhtml')->__('Password Confirmation'),
            'class' => 'input-text validate-cpassword',
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

        $fieldset = $form->addFieldset('passkey_fieldset', array(
            'legend' => Mage::helper('adminhtml')->__('Passkey Authentication')
        ));

        if ($user->getPasskeyCredentialIdHash()) {
            $fieldset->addField('passkey_status', 'note', array(
                'label'     => Mage::helper('adminhtml')->__('Status'),
                'text'      => '<span class="grid-severity-notice"><span>' .
                    Mage::helper('adminhtml')->__('Passkey Registered') . '</span></span>' .
                    '<br/><button type="button" id="remove-passkey-btn" class="scalable delete">' .
                    '<span>' . Mage::helper('adminhtml')->__('Remove Passkey') . '</span></button>'
            ));
        } else {
            $fieldset->addField('passkey_register', 'note', array(
                'label'     => Mage::helper('adminhtml')->__('Register a passkey to enable passwordless login'),
                'text'      => '<button type="button" id="register-passkey-btn" class="scalable add" onclick="MahoPasskey.startRegistration()">' .
                    '<span>' . Mage::helper('adminhtml')->__('Register Passkey') . '</span></button>'
            ));
        }

        $fieldset->addField('passkey_script', 'note', array(
            'text' => '
            <script type="text/javascript">
var MahoPasskey = {
    startRegistration: async function() {
        try {
            const options = await mahoFetch("https://maho.ddev.site/index.php/admin/system_account/passkeyregisterstart/key/" + FORM_KEY, {
                method: "GET"
            });
            options.challenge = this.base64ToArrayBuffer(options.challenge);
            options.user.id = this.base64ToArrayBuffer(options.user.id);
            
            const credential = await navigator.credentials.create({
                publicKey: options
            });

            const registrationData = {
                passkey_credential_id: this.arrayBufferToBase64(credential.rawId),
                passkey_credential_public_key: this.arrayBufferToBase64(credential.response.getPublicKey()),
                attestation_object: this.arrayBufferToBase64(credential.response.attestationObject),
                client_data_json: this.arrayBufferToBase64(credential.response.clientDataJSON)
            };
            const formData = new FormData();
            for (const [key, value] of Object.entries(registrationData)) {
                formData.append(key, value);
            }
            
            const verifyResponse = await mahoFetch("https://maho.ddev.site/index.php/admin/system_account/passkeyregistersave", {
                method: "POST",
                body: formData
            });

            alert("Passkey registered successfully!");
        } catch (error) {
            console.error("Registration error:", error);
            alert("Failed to register passkey: " + error.message);
        }
    },

    base64ToArrayBuffer: function(base64) {
        const binaryString = atob(base64);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes.buffer;
    },

    arrayBufferToBase64: function(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer)))
            .replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
    }
};
                        
document.addEventListener("DOMContentLoaded", function() {
    const removePasskeyBtn = document.getElementById("remove-passkey-btn");
    if (removePasskeyBtn) {
        removePasskeyBtn.addEventListener("click", function() {
            const form = document.getElementById("edit_form");
            if (form && editForm && editForm.validate && editForm.validate() && confirm(Translator.translate("Are you sure you want to remove your passkey?"))) {
                form.action = form.action.replace("save", "removepasskey");
                form.submit();
            }
        });
    }
});
            </script>'
        ));

        $twoFactorAuthenticationHelper = Mage::helper('adminhtml/twoFactorAuthentication');
        $twoFactorAuthenticationFieldset = $form->addFieldset('twofa_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Two-Factor Authentication'),
        ]);

        $twoFactorAuthenticationFieldset->addField('twofa_enabled', 'select', [
            'label' => Mage::helper('adminhtml')->__('Enable 2FA'),
            'name' => 'twofa_enabled',
            'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
        ]);

        if (!$user->getTwofaEnabled()) {
            $secret = $user->getTwofaSecret();
            if (!$secret) {
                $secret = $twoFactorAuthenticationHelper->getSecret();
                $user->setTwofaSecret($secret)->save();
            }

            $twoFactorAuthenticationFieldset->addField('twofa_qr', 'note', [
                'label' => Mage::helper('adminhtml')->__('QR Code'),
                'text' => $twoFactorAuthenticationHelper->getQRCode($user->getUsername(), $secret),
                'note' => Mage::helper('adminhtml')->__('Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)'),
            ]);

            $twoFactorAuthenticationFieldset->addField('twofa_verification_code', 'text', [
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

        $this->setForm($form);

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
        $block = $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence');
        $block->addFieldDependence('twofa_qr', 'twofa_enabled', '1')
            ->addFieldDependence('twofa_verification_code', 'twofa_enabled', '1');

        $this->setChild('form_after', $block);

        return parent::_prepareForm();
    }
}
