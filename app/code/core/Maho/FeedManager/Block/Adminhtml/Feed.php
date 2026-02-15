<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_feed';
        $this->_blockGroup = 'feedmanager';
        $this->_headerText = Mage::helper('feedmanager')->__('Product Feeds');
        $this->_addButtonLabel = Mage::helper('feedmanager')->__('Add New Feed');
        parent::__construct();
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        $html .= $this->_getBatchGeneratorScript();
        return $html;
    }

    /**
     * Get batch generator JavaScript for grid
     */
    protected function _getBatchGeneratorScript(): string
    {
        $initUrl = $this->getUrl('*/*/generateInit');
        $batchUrl = $this->getUrl('*/*/generateBatch');
        $finalizeUrl = $this->getUrl('*/*/generateFinalize');
        $cancelUrl = $this->getUrl('*/*/generateCancel');
        $massBatchUrl = $this->getUrl('*/*/massBatchGenerate');

        $uploadUrl = $this->getUrl('*/*/upload');

        $translations = Mage::helper('core')->jsonEncode([
            'generating' => $this->__('Generating Feed...'),
            'generating_multiple' => $this->__('Generating Feeds...'),
            'initializing' => $this->__('Initializing...'),
            'processing' => $this->__('Processing batch %s of %s...'),
            'finalizing' => $this->__('Finalizing...'),
            'completed' => $this->__('Feed generated successfully!'),
            'failed' => $this->__('Generation failed'),
            'cancelled' => $this->__('Generation cancelled'),
            'products' => $this->__('%s products'),
            'waiting' => $this->__('Waiting...'),
            'cancel' => $this->__('Cancel'),
            'close' => $this->__('Close'),
            'view' => $this->__('View'),
            'download' => $this->__('Download'),
            'upload' => $this->__('Upload Now'),
            'uploading' => $this->__('Uploading...'),
            'progress' => $this->__('%c / %t (%p%)'),
            'progress_filtered' => $this->__('%i included + %e excluded / %t (%p%)'),
            'excluded_summary' => $this->__('%s excluded due to filters'),
            'confirm' => $this->__('Are you sure you want to generate this feed now?'),
            'confirm_multiple' => $this->__('Generate selected feeds?'),
        ]);

        return <<<HTML
<script>
const FeedGenerator = {
    ...FeedGeneratorBase,
    urls: {
        init: '{$initUrl}',
        batch: '{$batchUrl}',
        finalize: '{$finalizeUrl}',
        cancel: '{$cancelUrl}',
        massBatch: '{$massBatchUrl}',
        upload: '{$uploadUrl}'
    },
    translations: {$translations},
    currentJobs: [],
    currentFeedId: null,

    // ── Single feed generation ──────────────────────────────────────────

    start(feedId) {
        if (!confirm(this.translations.confirm)) {
            return false;
        }

        this.cancelled = false;
        this.currentFeedId = feedId;

        // Show dialog with a single initializing card
        this.showDialog(this.translations.generating,
            '<div class="gen-multi-list">' +
                this._buildFeedCard(0, this.translations.initializing, '') +
            '</div>'
        );
        this._markFeedActive(0);

        mahoFetch(this.urls.init, {
            method: 'POST',
            body: new URLSearchParams({ id: feedId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.error) {
                this.showError(data.message);
                return;
            }

            this.currentJobId = data.job_id;
            this.totalProducts = data.total_products;
            this.batchesTotal = data.batches_total;

            // Update card with feed name from response
            const nameEl = document.querySelector('#gen-feed-0 .gen-feed-name');
            if (nameEl) nameEl.textContent = data.feed_name || '';

            this._updateFeedProgress(0, 0, data.total_products);
            this._updateFeedStatus(0,
                this.translations.processing.replace('%s', '1').replace('%s', data.batches_total));

            this.processBatch();
        })
        .catch(error => {
            this.showError(error.message || 'Network error');
        });

        return false;
    },

    showSuccess(data) {
        this._markFeedCompleted(0, data);

        // Add upload status below the card if available
        if (data.upload_status) {
            const uploadClass = data.upload_status === 'success' ? 'success-msg' :
                (data.upload_status === 'failed' ? 'error-msg' : 'notice-msg');
            this._showUploadStatus(0, uploadClass, data.upload_message || data.upload_status);
        }

        // Build buttons with view/download/upload links
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');
        let buttonsHtml = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog(); window.location.reload();">' +
            this.translations.close + '</button>';

        if (data.has_destination && data.upload_status !== 'success') {
            buttonsHtml += ' <button type="button" id="upload-now-btn" onclick="FeedGenerator.upload()">' +
                this.translations.upload + '</button>';
        }

        if (data.file_url) {
            const cacheBuster = data.file_url.includes('?') ? '&_=' : '?_=';
            const fileUrlWithCb = escapeHtml(data.file_url + cacheBuster + Date.now(), true);
            buttonsHtml += ' <a href="' + fileUrlWithCb + '" class="form-button" target="_blank">' +
                this.translations.view + '</a>';
            buttonsHtml += ' <a href="' + fileUrlWithCb + '" class="form-button" download>' +
                this.translations.download + '</a>';
        }
        if (buttonsEl) buttonsEl.innerHTML = buttonsHtml;
    },

    _showUploadStatus(idx, cssClass, message) {
        const entry = document.getElementById('gen-feed-' + idx);
        if (entry) {
            entry.querySelectorAll('.fm-upload-status').forEach(el => el.remove());
            entry.insertAdjacentHTML('beforeend',
                '<div class="' + cssClass + ' fm-upload-status" style="margin-top:8px">' +
                escapeHtml(message) + '</div>');
        }
    },

    upload() {
        const btn = document.getElementById('upload-now-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = this.translations.uploading;
        }

        mahoFetch(this.urls.upload, {
            method: 'POST',
            body: new URLSearchParams({ id: this.currentFeedId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.success) {
                this._showUploadStatus(0, 'success-msg', data.message);
                if (btn) btn.remove();
            } else {
                this._showUploadStatus(0, 'error-msg', data.message || 'Upload failed');
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = this.translations.upload;
                }
            }
        })
        .catch(error => {
            this._showUploadStatus(0, 'error-msg', error.message || 'Upload failed');
            if (btn) {
                btn.disabled = false;
                btn.textContent = this.translations.upload;
            }
        });
    },

    // ── Multi feed generation ───────────────────────────────────────────

    startMultiple(feedIds) {
        this.cancelled = false;
        this.currentJobs = [];
        this.currentJobIndex = 0;

        this.showDialog(this.translations.generating_multiple,
            '<div id="gen-multi-list" class="gen-multi-list">' +
                this._buildFeedCard(0, this.translations.initializing, '') +
            '</div>' +
            '<div id="gen-multi-errors"></div>'
        );
        this._markFeedActive(0);

        const params = new URLSearchParams();
        feedIds.forEach(id => params.append('feed_ids[]', id));
        params.append('form_key', FORM_KEY);

        mahoFetch(this.urls.massBatch, {
            method: 'POST',
            body: params,
            loaderArea: false
        })
        .then(data => {
            if (data.error || !data.jobs || data.jobs.length === 0) {
                this.showError(data.message || 'No feeds to generate');
                return;
            }

            this.currentJobs = data.jobs;

            // Build the per-feed cards
            const list = document.getElementById('gen-multi-list');
            if (list) {
                list.innerHTML = this.currentJobs.map((job, i) =>
                    this._buildFeedCard(i, job.feed_name, this.translations.waiting)
                ).join('');
            }

            // Show init errors if any
            if (data.errors && data.errors.length > 0) {
                const errorsEl = document.getElementById('gen-multi-errors');
                if (errorsEl) {
                    errorsEl.innerHTML = data.errors.map(e =>
                        '<div class="error-msg">' + escapeHtml(e) + '</div>'
                    ).join('');
                }
            }

            this.processNextJob();
        })
        .catch(error => {
            this.showError(error.message || 'Network error');
        });

        return false;
    },

    processNextJob() {
        if (this.cancelled || this.currentJobIndex >= this.currentJobs.length) {
            this._showCloseButton('FeedGenerator.closeDialog(); window.location.reload();');
            return;
        }

        const job = this.currentJobs[this.currentJobIndex];
        const idx = this.currentJobIndex;
        this.currentJobId = job.job_id;
        this.totalProducts = job.total_products;
        this.batchesTotal = job.batches_total;

        this._markFeedActive(idx);
        this._updateFeedStatus(idx,
            this.translations.processing.replace('%s', '1').replace('%s', this.batchesTotal));
        this._updateFeedProgress(idx, 0, job.total_products);

        const entry = document.getElementById('gen-feed-' + idx);
        if (entry) entry.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        this.processBatchForMultiple();
    },

    processBatchForMultiple() {
        if (this.cancelled) return;

        const idx = this.currentJobIndex;

        mahoFetch(this.urls.batch, {
            method: 'POST',
            body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (this.cancelled) return;

            if (data.status === 'failed') {
                this._markFeedFailed(idx, data.message || this.translations.failed);
                this.currentJobIndex++;
                this.processNextJob();
                return;
            }

            const matched = data.progress || 0;
            const processed = data.processed || matched;
            const total = data.total || this.totalProducts;

            if (data.status === 'finalizing') {
                this._updateFeedStatus(idx, this.translations.finalizing);
                this._updateFeedProgress(idx, processed, total, matched);
                this.finalizeForMultiple();
            } else if (data.status === 'processing') {
                const batchNum = data.batches_processed || 0;
                const batchTotal = data.batches_total || this.batchesTotal;
                this._updateFeedStatus(idx,
                    this.translations.processing.replace('%s', batchNum + 1).replace('%s', batchTotal));
                this._updateFeedProgress(idx, processed, total, matched);
                this.processBatchForMultiple();
            } else if (data.status === 'completed') {
                this._markFeedCompleted(idx, data);
                this.currentJobIndex++;
                this.processNextJob();
            }
        })
        .catch(error => {
            this._markFeedFailed(idx, error.message || 'Network error');
            this.currentJobIndex++;
            this.processNextJob();
        });
    },

    finalizeForMultiple() {
        const idx = this.currentJobIndex;

        mahoFetch(this.urls.finalize, {
            method: 'POST',
            body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.status === 'completed') {
                this._markFeedCompleted(idx, data);
            } else {
                this._markFeedFailed(idx, data.message || this.translations.failed);
            }
            this.currentJobIndex++;
            this.processNextJob();
        })
        .catch(error => {
            this._markFeedFailed(idx, error.message || 'Network error');
            this.currentJobIndex++;
            this.processNextJob();
        });
    }
};

// Intercept grid action dropdown for Generate (async batch) and View (new tab)
const _origGridActionExecute = varienGridAction.execute;
varienGridAction.execute = function(select) {
    if (!select.value) return;
    try {
        const config = JSON.parse(select.value);
        if (config.href && config.href.includes('/generate/id/')) {
            select.options[0].selected = true;
            const match = config.href.match(/\/id\/(\d+)/);
            if (match) FeedGenerator.start(match[1]);
            return;
        }
        if (config.href && config.href.includes('/view/id/')) {
            select.options[0].selected = true;
            window.open(config.href, '_blank');
            return;
        }
    } catch (e) {}
    _origGridActionExecute(select);
};

// Override mass action for generate
const _origMassactionApply = varienGridMassaction.prototype.apply;
varienGridMassaction.prototype.apply = function() {
    const item = this.getSelectedItem();
    if (item && item.id === 'generate') {
        if (varienStringArray.count(this.checkedString) === 0) {
            alert(this.errorText);
            return;
        }
        if (item.confirm && !window.confirm(item.confirm)) {
            return;
        }
        const feedIds = this.checkedString.split(',').filter(Boolean);
        if (feedIds.length > 0) {
            FeedGenerator.startMultiple(feedIds);
        }
        return;
    }
    _origMassactionApply.call(this);
};
</script>
HTML;
    }
}
