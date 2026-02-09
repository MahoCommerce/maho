/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Shared base for FeedGenerator on both grid and edit pages.
 * Page-specific objects spread this and add their own methods/overrides.
 */
const FeedGeneratorBase = {
    currentJobId: null,
    cancelled: false,
    dialog: null,

    processBatch() {
        if (this.cancelled) {
            return;
        }

        mahoFetch(this.urls.batch, {
            method: 'POST',
            body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (this.cancelled) {
                return;
            }

            if (data.status === 'failed') {
                this.showError(data.message);
                return;
            }

            const progress = data.progress || 0;
            const total = data.total || this.totalProducts;

            if (data.status === 'finalizing') {
                this.updateProgress(progress, total, this.translations.finalizing);
                this.finalize();
            } else if (data.status === 'processing') {
                const batchNum = data.batches_processed || 0;
                const batchTotal = data.batches_total || this.batchesTotal;
                this.updateProgress(progress, total,
                    this.translations.processing.replace('%s', batchNum + 1).replace('%s', batchTotal));
                this.processBatch();
            } else if (data.status === 'completed') {
                this.showSuccess(data);
            }
        })
        .catch(error => {
            if (!this.cancelled) {
                this.showError(error.message || 'Network error');
            }
        });
    },

    finalize() {
        mahoFetch(this.urls.finalize, {
            method: 'POST',
            body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.status === 'completed') {
                this.showSuccess(data);
            } else {
                this.showError(data.message || this.translations.failed);
            }
        })
        .catch(error => {
            this.showError(error.message || 'Network error');
        });
    },

    cancel() {
        this.cancelled = true;
        if (this.currentJobId) {
            mahoFetch(this.urls.cancel, {
                method: 'POST',
                body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
                loaderArea: false
            }).catch(() => {});
        }
        this.closeDialog();
    },

    showDialog(title) {
        const self = this;
        title = title || this.translations.generating;
        this.dialog = Dialog.info(this.getDialogContent(), {
            title: title,
            className: 'feed-generator-dialog',
            width: 450,
            height: 'auto',
            extraButtons: [
                { id: 'gen-cancel-btn', class: 'cancel', label: this.translations.cancel }
            ],
            onOpen(dialog) {
                dialog.style.height = 'auto';
                dialog.querySelector('#gen-cancel-btn')?.addEventListener('click', () => self.cancel());
            }
        });
    },

    closeDialog() {
        if (this.dialog) {
            this.dialog.remove();
            this.dialog = null;
        }
        this.currentJobId = null;
    },

    getDialogContent() {
        return `
            <div class="status" id="gen-status"></div>
            <div class="progress-container">
                <div class="progress-bar-bg">
                    <div class="progress-bar" id="gen-progress-bar"></div>
                </div>
                <div class="progress-text" id="gen-progress-text"></div>
            </div>
        `;
    },

    updateProgress(current, total, message) {
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        const progressBar = document.getElementById('gen-progress-bar');
        const progressText = document.getElementById('gen-progress-text');
        const statusEl = document.getElementById('gen-status');

        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = current + ' / ' + total + ' (' + percent + '%)';
        if (statusEl) statusEl.textContent = message;
    },

    showError(message) {
        const statusEl = document.getElementById('gen-status');
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');

        if (statusEl) {
            statusEl.innerHTML = '<div class="error-msg">' + escapeHtml(message) + '</div>';
        }
        if (buttonsEl) {
            buttonsEl.innerHTML = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog()">' +
                this.translations.close + '</button>';
        }
    }
};
