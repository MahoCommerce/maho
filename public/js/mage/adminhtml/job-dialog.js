// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0

/**
 * Dialog for a job that outlives the request that started it.
 *
 * The start url answers with { token, steps } and keeps working with the connection closed; the
 * status url reports { finished, steps } until the run reaches a terminal state.
 */
class MahoJobDialog {
    static POLL_FAST_MS = 1000;
    static POLL_FAST_COUNT = 10;
    static POLL_SLOW_MS = 3000;
    static MAX_ERRORS = 5;

    constructor(config) {
        this.title = config.title ?? '';
        this.startUrl = config.startUrl;
        this.statusUrl = config.statusUrl;
        this.startParams = config.startParams ?? {};
        this.width = config.width ?? 460;
        this.reloadOnClose = config.reloadOnClose ?? true;
        this.labels = {
            close: 'Close',
            done: 'Done',
            failed: 'Failed',
            requestFailed: 'Request failed',
            ...config.labels,
        };

        this.steps = [];
        this.timer = null;
        this.dialog = null;
        this.startedAt = 0;
    }

    async run() {
        this.open();

        try {
            const body = new URLSearchParams();
            for (const [key, value] of Object.entries(this.startParams)) {
                Array.isArray(value)
                    ? value.forEach((item) => body.append(`${key}[]`, item))
                    : body.set(key, value);
            }

            await this.follow(await mahoFetch(this.startUrl, { method: 'POST', body, loaderArea: false }));
        } catch (e) {
            this.showResult('error', e.message || this.labels.requestFailed);
        }
    }

    /** Same dialog, for a start request someone else already sent (grid mass-actions post their own form) */
    async attach(result) {
        this.open();

        try {
            await this.follow(result);
        } catch (e) {
            this.showResult('error', e.message || this.labels.requestFailed);
        }
    }

    open() {
        this.startedAt = Date.now();
        this.dialog = Dialog.info('', {
            title: this.title,
            className: 'maho-job-dialog',
            width: this.width,
            ok: true,
            okLabel: this.labels.close,
            onClose: () => {
                clearInterval(this.timer);
                if (this.reloadOnClose) {
                    location.reload();
                }
            },
        });

        this.render();
        this.timer = setInterval(() => {
            const el = this.dialog?.querySelector('.maho-job-timer');
            if (el) {
                el.textContent = MahoJobDialog.formatElapsed(Date.now() - this.startedAt);
            }
        }, 100);
    }

    async follow(result) {
        if (result.error) {
            this.showResult('error', result.message);
            return;
        }

        this.update(result);
        if (!result.finished) {
            await this.poll(result.token);
        }
    }

    async poll(token) {
        const url = `${this.statusUrl}?token=${encodeURIComponent(token)}`;
        let errors = 0;

        for (let i = 0; this.isOpen(); i++) {
            const wait = i < MahoJobDialog.POLL_FAST_COUNT ? MahoJobDialog.POLL_FAST_MS : MahoJobDialog.POLL_SLOW_MS;
            await new Promise((resolve) => setTimeout(resolve, wait));

            if (!this.isOpen()) {
                return;
            }

            try {
                const data = await mahoFetch(url, { loaderArea: false });
                errors = 0;
                this.update(data);
                if (data.finished) {
                    return;
                }
            } catch (e) {
                if (++errors >= MahoJobDialog.MAX_ERRORS) {
                    this.showResult('error', e.message || this.labels.requestFailed);
                    return;
                }
            }
        }
    }

    update(data) {
        this.steps = data.steps ?? [];
        if (data.finished) {
            clearInterval(this.timer);
        }
        this.render();

        if (data.finished) {
            const failed = this.steps.some((step) => step.state === 'error');
            this.showResult(failed ? 'error' : 'success', failed ? this.labels.failed : this.labels.done);
        }
    }

    render() {
        const content = this.dialog?.querySelector('.dialog-content');
        if (!content) {
            return;
        }

        const elapsed = MahoJobDialog.formatElapsed(Date.now() - this.startedAt);
        content.innerHTML = `<div class="maho-job-timer">${elapsed}</div>`
            + `<ul class="maho-job-steps">${this.steps.map((step) => this.renderStep(step)).join('')}</ul>`;
    }

    renderStep(step) {
        const icon = step.state === 'running'
            ? '<span class="maho-spinner"></span>'
            : '<span class="maho-job-step-icon"></span>';

        const meta = step.duration !== null && step.duration !== undefined
            ? MahoJobDialog.formatElapsed(step.duration * 1000)
            : '';

        return `<li class="maho-job-step" data-state="${MahoJobDialog.escapeHtml(step.state)}">`
            + '<div class="maho-job-step-row">'
            + icon
            + `<span class="maho-job-step-name">${MahoJobDialog.escapeHtml(step.name)}</span>`
            + `<span class="maho-job-step-meta">${MahoJobDialog.escapeHtml(meta)}</span>`
            + '</div>'
            + (step.message ? `<pre class="maho-job-step-message">${MahoJobDialog.escapeHtml(step.message)}</pre>` : '')
            + '</li>';
    }

    showResult(state, message) {
        clearInterval(this.timer);

        const content = this.dialog?.querySelector('.dialog-content');
        if (!content) {
            return;
        }

        content.querySelector('.maho-job-result')?.remove();
        content.insertAdjacentHTML(
            'beforeend',
            `<div class="maho-job-result" data-state="${state}">${MahoJobDialog.escapeHtml(message)}</div>`,
        );
    }

    isOpen() {
        return this.dialog?.isConnected === true;
    }

    static escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    static formatElapsed(ms) {
        const totalSec = Math.floor(ms / 1000);
        if (totalSec < 60) {
            return `${totalSec}s`;
        }
        const min = Math.floor(totalSec / 60);
        if (min < 60) {
            return `${min}m ${totalSec % 60}s`;
        }
        return `${Math.floor(min / 60)}h ${min % 60}m`;
    }
}
