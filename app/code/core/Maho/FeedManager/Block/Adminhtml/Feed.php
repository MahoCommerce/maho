<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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

        $translations = Mage::helper('core')->jsonEncode([
            'generating' => $this->__('Generating Feed...'),
            'generating_multiple' => $this->__('Generating Feeds...'),
            'initializing' => $this->__('Initializing...'),
            'processing' => $this->__('Processing batch %s of %s...'),
            'processing_feed' => $this->__('Processing feed %s of %s...'),
            'finalizing' => $this->__('Finalizing...'),
            'completed' => $this->__('Feed generated successfully!'),
            'completed_multiple' => $this->__('%s feed(s) generated successfully'),
            'failed' => $this->__('Generation failed'),
            'cancelled' => $this->__('Generation cancelled'),
            'products' => $this->__('%s products'),
            'cancel' => $this->__('Cancel'),
            'close' => $this->__('Close'),
            'download' => $this->__('Download'),
            'confirm' => $this->__('Are you sure you want to generate this feed now?'),
            'confirm_multiple' => $this->__('Generate selected feeds?'),
        ]);

        return <<<HTML
<script>
const FeedGenerator = {
    urls: {
        init: '{$initUrl}',
        batch: '{$batchUrl}',
        finalize: '{$finalizeUrl}',
        cancel: '{$cancelUrl}',
        massBatch: '{$massBatchUrl}'
    },
    translations: {$translations},
    currentJobId: null,
    currentJobs: [],
    cancelled: false,
    dialog: null,

    start: function(feedId) {
        if (!confirm(this.translations.confirm)) {
            return false;
        }

        this.cancelled = false;
        this.showDialog(false);
        this.updateProgress(0, 0, this.translations.initializing);

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

            this.updateProgress(0, data.total_products,
                this.translations.processing.replace('%s', '1').replace('%s', data.batches_total));

            this.processBatch();
        })
        .catch(error => {
            this.showError(error.message || 'Network error');
        });

        return false;
    },

    startMultiple: function(feedIds) {
        if (!confirm(this.translations.confirm_multiple)) {
            return false;
        }

        this.cancelled = false;
        this.currentJobs = [];
        this.currentJobIndex = 0;
        this.showDialog(true);
        this.updateProgress(0, feedIds.length, this.translations.initializing);

        mahoFetch(this.urls.massBatch, {
            method: 'POST',
            body: new URLSearchParams({ 'feed_ids[]': feedIds, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.error && !data.jobs) {
                this.showError(data.message);
                return;
            }

            this.currentJobs = data.jobs || [];
            this.totalJobs = this.currentJobs.length;
            this.completedJobs = 0;
            this.failedJobs = data.errors ? data.errors.length : 0;

            if (this.currentJobs.length === 0) {
                this.showError(data.message || 'No feeds to generate');
                return;
            }

            this.processNextJob();
        })
        .catch(error => {
            this.showError(error.message || 'Network error');
        });

        return false;
    },

    processNextJob: function() {
        if (this.cancelled || this.currentJobIndex >= this.currentJobs.length) {
            this.showMultipleSuccess();
            return;
        }

        const job = this.currentJobs[this.currentJobIndex];
        this.currentJobId = job.job_id;
        this.totalProducts = job.total_products;
        this.batchesTotal = job.batches_total;

        this.updateMultipleProgress(
            this.translations.processing_feed.replace('%s', this.currentJobIndex + 1).replace('%s', this.totalJobs) +
            ' (' + job.feed_name + ')'
        );

        this.processBatchForMultiple();
    },

    processBatchForMultiple: function() {
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
                this.failedJobs++;
                this.currentJobIndex++;
                this.processNextJob();
                return;
            }

            if (data.status === 'finalizing') {
                this.finalizeForMultiple();
            } else if (data.status === 'processing') {
                this.processBatchForMultiple();
            } else if (data.status === 'completed') {
                this.completedJobs++;
                this.currentJobIndex++;
                this.processNextJob();
            }
        })
        .catch(error => {
            this.failedJobs++;
            this.currentJobIndex++;
            this.processNextJob();
        });
    },

    finalizeForMultiple: function() {
        mahoFetch(this.urls.finalize, {
            method: 'POST',
            body: new URLSearchParams({ job_id: this.currentJobId, form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (data.status === 'completed') {
                this.completedJobs++;
            } else {
                this.failedJobs++;
            }
            this.currentJobIndex++;
            this.processNextJob();
        })
        .catch(error => {
            this.failedJobs++;
            this.currentJobIndex++;
            this.processNextJob();
        });
    },

    processBatch: function() {
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

    finalize: function() {
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

    cancel: function() {
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

    showDialog: function(isMultiple) {
        const self = this;
        const title = isMultiple ? this.translations.generating_multiple : this.translations.generating;
        this.dialog = Dialog.info(this.getDialogContent(), {
            title: title,
            className: 'feed-generator-dialog',
            width: 450,
            height: 'auto',
            extraButtons: [
                { id: 'gen-cancel-btn', class: 'cancel', label: this.translations.cancel }
            ],
            onOpen: function(dialog) {
                dialog.style.height = 'auto';
                dialog.querySelector('#gen-cancel-btn')?.addEventListener('click', () => self.cancel());
            }
        });
    },

    closeDialog: function() {
        if (this.dialog) {
            this.dialog.remove();
            this.dialog = null;
        }
        this.currentJobId = null;
    },

    getDialogContent: function() {
        return \`
            <div class="status" id="gen-status"></div>
            <div class="progress-container">
                <div class="progress-bar-bg">
                    <div class="progress-bar" id="gen-progress-bar"></div>
                </div>
                <div class="progress-text" id="gen-progress-text"></div>
            </div>
        \`;
    },

    updateProgress: function(current, total, message) {
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        const progressBar = document.getElementById('gen-progress-bar');
        const progressText = document.getElementById('gen-progress-text');
        const statusEl = document.getElementById('gen-status');

        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = current + ' / ' + total + ' (' + percent + '%)';
        if (statusEl) statusEl.textContent = message;
    },

    updateMultipleProgress: function(message) {
        const percent = this.totalJobs > 0 ? Math.round((this.currentJobIndex / this.totalJobs) * 100) : 0;
        const progressBar = document.getElementById('gen-progress-bar');
        const progressText = document.getElementById('gen-progress-text');
        const statusEl = document.getElementById('gen-status');

        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = (this.currentJobIndex) + ' / ' + this.totalJobs + ' feeds';
        if (statusEl) statusEl.textContent = message;
    },

    showError: function(message) {
        const statusEl = document.getElementById('gen-status');
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');

        if (statusEl) {
            statusEl.innerHTML = '<div class="error-msg">' + escapeHtml(message) + '</div>';
        }
        if (buttonsEl) {
            buttonsEl.innerHTML = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog()">' +
                this.translations.close + '</button>';
        }
    },

    showSuccess: function(data) {
        const statusEl = document.getElementById('gen-status');
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');
        const progressBar = document.getElementById('gen-progress-bar');

        if (progressBar) progressBar.style.width = '100%';

        let successHtml = '<div class="success-msg">' +
            this.translations.completed + '<br>' +
            this.translations.products.replace('%s', data.product_count || 0);
        if (data.file_size_formatted) {
            successHtml += ' (' + data.file_size_formatted + ')';
        }
        successHtml += '</div>';

        if (statusEl) statusEl.innerHTML = successHtml;

        let buttonsHtml = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog(); window.location.reload();">' +
            this.translations.close + '</button>';
        if (data.file_url) {
            const cacheBuster = data.file_url.includes('?') ? '&_=' : '?_=';
            buttonsHtml += ' <a href="' + escapeHtml(data.file_url + cacheBuster + Date.now(), true) + '" class="form-button" target="_blank">' +
                this.translations.download + '</a>';
        }
        if (buttonsEl) buttonsEl.innerHTML = buttonsHtml;
    },

    showMultipleSuccess: function() {
        const statusEl = document.getElementById('gen-status');
        const buttonsEl = this.dialog?.querySelector('.dialog-buttons');
        const progressBar = document.getElementById('gen-progress-bar');

        if (progressBar) progressBar.style.width = '100%';

        let message = this.translations.completed_multiple.replace('%s', this.completedJobs);
        if (this.failedJobs > 0) {
            message += ' (' + this.failedJobs + ' failed)';
        }

        let successHtml = '<div class="success-msg">' + message + '</div>';
        if (statusEl) statusEl.innerHTML = successHtml;

        let buttonsHtml = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog(); window.location.reload();">' +
            this.translations.close + '</button>';
        if (buttonsEl) buttonsEl.innerHTML = buttonsHtml;
    }
};

// Intercept Generate link clicks in the grid
document.addEventListener('click', function(e) {
    const link = e.target.closest('a');
    if (link && link.href && link.href.includes('/generate/id/')) {
        e.preventDefault();
        const match = link.href.match(/\/id\/(\d+)/);
        if (match) {
            FeedGenerator.start(match[1]);
        }
    }
});

// Override mass action for generate
document.addEventListener('DOMContentLoaded', function() {
    const massactionForm = document.getElementById('feedmanagerFeedGrid_massaction-form');
    if (massactionForm) {
        massactionForm.addEventListener('submit', function(e) {
            const select = this.querySelector('select[name="massaction"]');
            if (select && select.value === 'generate') {
                e.preventDefault();
                const checkboxes = document.querySelectorAll('#feedmanagerFeedGrid_table input[type="checkbox"]:checked');
                const feedIds = Array.from(checkboxes).map(cb => cb.value).filter(v => v && v !== 'on');
                if (feedIds.length > 0) {
                    FeedGenerator.startMultiple(feedIds);
                }
            }
        });
    }
});
</script>
HTML;
    }
}
