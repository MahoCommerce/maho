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

/**
 * convert RFC 1342-like base64 strings to array buffer
 * @param {mixed} obj
 * @returns {undefined}
 */
function recursiveBase64StrToArrayBuffer(obj) {
    let prefix = '=?BINARY?B?';
    let suffix = '?=';
    if (typeof obj === 'object') {
        for (let key in obj) {
            if (typeof obj[key] === 'string') {
                let str = obj[key];
                if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                    str = str.substring(prefix.length, str.length - suffix.length);

                    let binary_string = window.atob(str);
                    let len = binary_string.length;
                    let bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++)        {
                        bytes[i] = binary_string.charCodeAt(i);
                    }
                    obj[key] = bytes.buffer;
                }
            } else {
                recursiveBase64StrToArrayBuffer(obj[key]);
            }
        }
    }
}

/**
 * Convert a ArrayBuffer to Base64
 * @param {ArrayBuffer} buffer
 * @returns {String}
 */
function arrayBufferToBase64(buffer) {
    let binary = '';
    let bytes = new Uint8Array(buffer);
    let len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
        binary += String.fromCharCode( bytes[ i ] );
    }
    return window.btoa(binary);
}
