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

function accessibilityScanShowMarker(violationId) {
    const marker = document.getElementById('a11yscan-marker-' + violationId);
    const details = marker?.closest('details.a11yscan-screenshot');
    if (!details || !marker) {
        return;
    }
    details.open = true;
    for (const el of document.querySelectorAll('.a11yscan-marker.flash')) {
        el.classList.remove('flash');
    }
    // Force a reflow so re-clicking the same violation restarts the pulse
    void marker.offsetWidth;
    marker.classList.add('flash');
    marker.scrollIntoView({ block: 'center', behavior: 'smooth' });
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
