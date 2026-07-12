// Accessibility Scan admin UI helpers
//
// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

async function accessibilityScanStart(form) {
    const button = form.querySelector('button[type="submit"]');
    const status = document.getElementById('a11yscan-status');

    button.disabled = true;
    if (status) {
        status.textContent = status.dataset.runningMessage ?? '';
        status.classList.remove('error');
    }

    try {
        const result = await mahoFetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        });
        if (result?.redirect) {
            setLocation(result.redirect);
            return;
        }
    } catch (error) {
        if (status) {
            status.textContent = error.message;
            status.classList.add('error');
        }
    }
    button.disabled = false;
}

function accessibilityScanDelete(url, message) {
    if (!confirm(message)) {
        return;
    }
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    const formKey = document.createElement('input');
    formKey.type = 'hidden';
    formKey.name = 'form_key';
    formKey.value = FORM_KEY;
    form.appendChild(formKey);

    document.body.appendChild(form);
    form.submit();
}
