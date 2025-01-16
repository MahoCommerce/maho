/**
 * Maho
 *
 * @category    Mage
 * @package     Mage_Adminhtml
 * @copyright   Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class MahoPasskeyController
{
    constructor(config = {}) {
        this.config = config;

        if (!this.config.registerStartUrl || !this.config.registerSaveUrl || !this.config.removePasskeyUrl) {
            throw new Error('Invalid passkey configuration');
        }

        this.bindEventListeners();
    }

    bindEventListeners() {
        document.getElementById('register-passkey-btn')?.addEventListener('click', this.startRegistration.bind(this));
        document.getElementById('remove-passkey-btn')?.addEventListener('click', this.removePasskey.bind(this));
    }

    async startRegistration() {
        try {
            const options = await mahoFetch(this.config.registerStartUrl, { method: 'POST' });
            options.challenge = Base64.toArrayBuffer(options.challenge);
            options.user.id = Base64.toArrayBuffer(options.user.id);

            const credential = await navigator.credentials.create({
                publicKey: options
            });

            const registrationData = {
                passkey_credential_id: Base64.fromArrayBuffer(credential.rawId),
                passkey_credential_public_key: Base64.fromArrayBuffer(credential.response.getPublicKey()),
                attestation_object: Base64.fromArrayBuffer(credential.response.attestationObject),
                client_data_json: Base64.fromArrayBuffer(credential.response.clientDataJSON)
            };

            const formData = new FormData();
            for (const [ key, value ] of Object.entries(registrationData)) {
                formData.append(key, value);
            }

            const verifyResponse = await mahoFetch(this.config.registerSaveUrl, {
                method: 'POST',
                body: formData
            });

            alert(Translator.translate('Passkey registered successfully!'));
        } catch (error) {
            alert(Translator.translate('Failed to register passkey: %s', error.message));
            console.error('Registration error:', error);
        }
    }

    removePasskey() {
        const form = document.getElementById('edit_form');
        if (form && editForm?.validate() && confirm(Translator.translate('Are you sure you want to remove your passkey?'))) {
            form.action = this.config.removePasskeyUrl;
            form.submit();
        }
    }
};
