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
        const started = await mahoFetch(form.action, {
            method: 'POST',
            body: new FormData(form),
        });

        // Kick off the scan. A fronting proxy may time this request out even
        // though the scan keeps running server-side, so its outcome is only
        // advisory: the status poller below decides success or failure.
        const runBody = new FormData();
        runBody.append('form_key', FORM_KEY);
        mahoFetch(started.run_url, { method: 'POST', body: runBody }).catch(() => {});

        let result;
        do {
            await new Promise((resolve) => setTimeout(resolve, 3000));
            result = await mahoFetch(started.status_url);
        } while (!result?.redirect);
        setLocation(result.redirect);
        return;
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
    marker.scrollIntoView({
        block: 'center',
        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
    });
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
