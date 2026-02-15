/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Shared base for FeedGenerator on both grid and edit pages.
 * Page-specific objects spread this and add their own methods/overrides.
 *
 * Uses a unified card-based UI for both single and multi-feed generation.
 * Each feed is rendered as a card with its own name, progress bar, and status.
 */
const FeedGeneratorBase = {
    currentJobId: null,
    cancelled: false,
    dialog: null,

    // -- Card rendering helpers ------------------------------------------

    _buildFeedCard(idx, feedName, statusText) {
        return '<div class="gen-feed-entry" id="gen-feed-' + idx + '">' +
            '<div class="gen-feed-header">' +
                '<span class="gen-feed-name">' + escapeHtml(feedName) + '</span>' +
                '<span class="gen-feed-status" id="gen-feed-status-' + idx + '">' + escapeHtml(statusText) + '</span>' +
            '</div>' +
            '<div class="progress-container">' +
                '<div class="progress-bar-bg">' +
                    '<div class="progress-bar" id="gen-feed-bar-' + idx + '"></div>' +
                '</div>' +
                '<div class="progress-text" id="gen-feed-text-' + idx + '"></div>' +
            '</div>' +
        '</div>';
    },

    _updateFeedProgress(idx, processed, total, matched) {
        const percent = total > 0 ? Math.round((processed / total) * 100) : 0;
        const bar = document.getElementById('gen-feed-bar-' + idx);
        const text = document.getElementById('gen-feed-text-' + idx);
        if (bar) bar.style.width = percent + '%';
        if (text) {
            if (matched !== undefined && matched !== processed) {
                const excluded = processed - matched;
                text.textContent = (this.translations.progress_filtered || '%i included + %e excluded / %t (%p%)')
                    .replace('%i', matched).replace('%e', excluded).replace('%t', total).replace('%p', percent);
            } else {
                text.textContent = (this.translations.progress || '%c / %t (%p%)')
                    .replace('%c', processed).replace('%t', total).replace('%p', percent);
            }
        }
    },

    _updateFeedStatus(idx, message) {
        const el = document.getElementById('gen-feed-status-' + idx);
        if (el) el.textContent = message;
    },

    _markFeedActive(idx) {
        const entry = document.getElementById('gen-feed-' + idx);
        if (entry) entry.classList.add('active');
    },

    _markFeedCompleted(idx, data) {
        const entry = document.getElementById('gen-feed-' + idx);
        if (entry) {
            entry.classList.remove('active');
            entry.classList.add('completed');
        }

        const bar = document.getElementById('gen-feed-bar-' + idx);
        if (bar) bar.style.width = '100%';

        let info = this.translations.products.replace('%s', data.product_count || 0);
        if (data.file_size_formatted) info += ' (' + data.file_size_formatted + ')';

        const statusEl = document.getElementById('gen-feed-status-' + idx);
        if (statusEl) {
            statusEl.innerHTML = '<span class="gen-status-ok">' + escapeHtml(info) + '</span>';
        }

        const text = document.getElementById('gen-feed-text-' + idx);
        if (text) {
            const total = data.total_products || 0;
            const included = data.product_count || 0;
            const excluded = total - included;
            if (excluded > 0) {
                text.textContent = (this.translations.excluded_summary || '%s excluded due to filters').replace('%s', excluded);
            } else {
                text.textContent = '';
            }
        }
    },

    _markFeedFailed(idx, message) {
        const entry = document.getElementById('gen-feed-' + idx);
        if (entry) {
            entry.classList.remove('active');
            entry.classList.add('failed');
        }

        const bar = document.getElementById('gen-feed-bar-' + idx);
        if (bar) {
            bar.style.width = '100%';
            bar.classList.add('error');
        }

        const statusEl = document.getElementById('gen-feed-status-' + idx);
        if (statusEl) {
            statusEl.innerHTML = '<span class="gen-status-err">' + escapeHtml(message) + '</span>';
        }

        const text = document.getElementById('gen-feed-text-' + idx);
        if (text) text.textContent = '';
    },

    // -- Dialog management -----------------------------------------------

    showDialog(title, content) {
        const self = this;
        title = title || this.translations.generating;
        this.dialog = Dialog.info(content, {
            title: title,
            className: 'feed-generator-dialog',
            width: 500,
            extraButtons: [
                { id: 'gen-cancel-btn', class: 'cancel', label: this.translations.cancel }
            ],
            onOpen(dialog) {
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

    _showCloseButton(onclick) {
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');
        if (buttonsEl) {
            buttonsEl.innerHTML = '<button type="button" class="cancel" onclick="' +
                (onclick || 'FeedGenerator.closeDialog()') + '">' +
                this.translations.close + '</button>';
        }
    },

    // -- Batch processing (shared by single and multi) -------------------

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

            const matched = data.progress || 0;
            const processed = data.processed || matched;
            const total = data.total || this.totalProducts;

            if (data.status === 'finalizing') {
                this._updateFeedProgress(0, processed, total, matched);
                this._updateFeedStatus(0, this.translations.finalizing);
                this.finalize();
            } else if (data.status === 'processing') {
                const batchNum = data.batches_processed || 0;
                const batchTotal = data.batches_total || this.batchesTotal;
                this._updateFeedProgress(0, processed, total, matched);
                this._updateFeedStatus(0,
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

    // -- Default showSuccess / showError for single-feed -----------------
    // Pages override these for extra features (upload, view, download).

    showSuccess(data) {
        this._markFeedCompleted(0, data);
        this._showCloseButton('FeedGenerator.closeDialog(); window.location.reload();');
    },

    showError(message) {
        this._markFeedFailed(0, message);
        this._showCloseButton();
    }
};
