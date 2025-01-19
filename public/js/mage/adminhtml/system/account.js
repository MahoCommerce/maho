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
            recursiveBase64StrToArrayBuffer(options);

            const cred = await navigator.credentials.create(options);
            const authenticatorAttestationResponse = {
                transports: cred.response.getTransports  ? cred.response.getTransports() : null,
                clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
                attestationObject: cred.response.attestationObject ? arrayBufferToBase64(cred.response.attestationObject) : null
            };

            const verifyResponse = await mahoFetch(this.config.registerSaveUrl + '?form_key=' + FORM_KEY, {
                method: 'POST',
                body: JSON.stringify(authenticatorAttestationResponse),
            });

            alert(verifyResponse.message);
            location.reload();
        } catch (error) {
            alert(error.message);
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
