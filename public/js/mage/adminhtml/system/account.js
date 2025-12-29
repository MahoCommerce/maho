/**
 * Maho
 *
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class MahoAdminhtmlSystemAccountController
{
    constructor(config = {}) {
        this.config = config;
        if (!this.config.passkeyRegisterStartUrl) {
            throw new Error('Missing Passkey Register Start URL');
        }

        this.bindEventListeners();
    }

    bindEventListeners() {
        document.getElementById('register-passkey-btn')?.addEventListener('click', this.startRegistration.bind(this));
        document.getElementById('remove-passkey-btn')?.addEventListener('click', this.removePasskey.bind(this));
    }

    submitForm() {
        window.editForm.submit();
        if (document.querySelector('input[name=current_password].validation-failed')) {
            setMessagesDiv(
                Translator.translate('Enter current password and press "Save Account" to confirm passkey change.'), 'notice'
            );
        }
    }

    setPasskeyEnabled(flag) {
        document.getElementById('password_enabled').value = flag ? 0 : 1;
        document.getElementById('passkey_enabled').value = flag ? 1 : 0;
        document.getElementById('passkey_enabled').dispatchEvent(new formElementDependenceEvent());
    }

    async startRegistration() {
        try {
            const options = await mahoFetch(this.config.passkeyRegisterStartUrl, { method: 'POST' });
            recursiveBase64StrToArrayBuffer(options);

            const cred = await navigator.credentials.create(options);
            const authenticatorAttestationResponse = {
                transports: cred.response.getTransports  ? cred.response.getTransports() : null,
                clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
                attestationObject: cred.response.attestationObject ? arrayBufferToBase64(cred.response.attestationObject) : null
            };

            document.getElementById('passkey_status').innerHTML = '<em>' + Translator.translate('Passkey will be registered upon saving.') + '</em>';
            document.getElementById('passkey_value').value = JSON.stringify(authenticatorAttestationResponse);
            document.getElementById('remove-passkey-btn').classList.remove('no-display');
            this.setPasskeyEnabled(true);
            this.submitForm();
        } catch (error) {
            document.getElementById('passkey_status').innerHTML = `<span class="error">${escapeHtml(error.message)}</span>`;
            document.getElementById('passkey_value').value = '';
            document.getElementById('remove-passkey-btn').classList.add('no-display');
        }
    }

    removePasskey() {
        document.getElementById('passkey_status').innerHTML = '<em>' + Translator.translate('Passkey will be deleted upon saving.') + '</em>';
        document.getElementById('passkey_value').value = 'deleted';
        document.getElementById('remove-passkey-btn').classList.add('no-display');
        this.setPasskeyEnabled(false);
        this.submitForm();
    }
};
