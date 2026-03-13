/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

async function mahoPaypalTestConnection(button) {
    const url = button.dataset.ajaxUrl;
    const resultSpan = button.nextElementSibling;
    button.disabled = true;
    resultSpan.textContent = 'Testing...';
    resultSpan.style.color = '';

    try {
        const response = await mahoFetch(url, { method: 'POST' });
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
        const response = await mahoFetch(url, { method: 'POST' });
        resultSpan.textContent = response.message;
        resultSpan.style.color = response.success ? 'green' : 'red';

        if (response.webhook_id) {
            const webhookField = document.getElementById('maho_paypal_credentials_webhook_id');
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
