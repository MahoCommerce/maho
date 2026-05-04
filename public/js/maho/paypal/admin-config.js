/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

function mahoPaypalGetCredentials() {
    const params = new URLSearchParams();
    params.set('sandbox', document.getElementById('payment_paypal_credentials_sandbox')?.value ?? '');
    params.set('client_id', document.getElementById('payment_paypal_credentials_client_id')?.value ?? '');
    params.set('client_secret', document.getElementById('payment_paypal_credentials_client_secret')?.value ?? '');

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('website')) params.set('website', urlParams.get('website'));
    if (urlParams.get('store')) params.set('store', urlParams.get('store'));

    return params;
}

async function mahoPaypalTestConnection(button) {
    const url = button.dataset.ajaxUrl;
    const resultSpan = button.nextElementSibling;
    button.disabled = true;
    resultSpan.textContent = 'Testing...';
    resultSpan.style.color = '';

    try {
        const response = await mahoFetch(url, {
            method: 'POST',
            body: mahoPaypalGetCredentials(),
        });
        resultSpan.textContent = response.message;
        resultSpan.style.color = response.success ? 'green' : 'red';
    } catch (error) {
        resultSpan.textContent = 'Request failed: ' + error.message;
        resultSpan.style.color = 'red';
    } finally {
        button.disabled = false;
    }
}

async function mahoPaypalRegisterWebhook(button) {
    const url = button.dataset.ajaxUrl;
    const resultSpan = button.nextElementSibling;
    button.disabled = true;
    resultSpan.textContent = 'Registering...';
    resultSpan.style.color = '';

    try {
        const response = await mahoFetch(url, {
            method: 'POST',
            body: mahoPaypalGetCredentials(),
        });
        resultSpan.textContent = response.message;
        resultSpan.style.color = response.success ? 'green' : 'red';

        if (response.webhook_id) {
            const webhookField = document.getElementById('payment_paypal_credentials_webhook_id');
            if (webhookField) {
                webhookField.value = response.webhook_id;
            }
        }
    } catch (error) {
        resultSpan.textContent = 'Request failed: ' + error.message;
        resultSpan.style.color = 'red';
    } finally {
        button.disabled = false;
    }
}
