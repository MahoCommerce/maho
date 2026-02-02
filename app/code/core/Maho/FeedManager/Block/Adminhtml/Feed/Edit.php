<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'feedmanager';
        $this->_controller = 'adminhtml_feed';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save Feed'));
        $this->_updateButton('delete', 'label', $this->__('Delete Feed'));

        $this->_addButton('saveandcontinue', [
            'label' => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ], -100);

        // Add buttons for existing feeds
        if ($this->_getFeed()->getId()) {
            $this->_addButton('duplicate', [
                'label' => $this->__('Duplicate'),
                'onclick' => "setLocation('" . $this->getUrl('*/*/duplicate', ['id' => $this->_getFeed()->getId()]) . "')",
                'class' => 'add',
            ], -95);

            $this->_addButton('generate', [
                'label' => $this->__('Generate Now'),
                'onclick' => 'FeedGenerator.saveAndGenerate(' . $this->_getFeed()->getId() . ')',
                'class' => 'add',
            ], -90);
        }

        $this->_formScripts[] = $this->_getFormScripts();
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $feed = $this->_getFeed();
        if ($feed->getId()) {
            return $this->__("Edit Feed '%s'", $this->escapeHtml($feed->getName()));
        }
        return $this->__('New Feed');
    }

    /**
     * Convert PHP bool to JavaScript boolean string
     */
    protected function _boolToJs(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    /**
     * Get feed ID for JavaScript (returns 'null' if no ID)
     */
    protected function _getFeedIdForJs(): string
    {
        $id = $this->_getFeed()->getId();
        return $id ? (string) $id : 'null';
    }

    public function getGenerateUrl(): string
    {
        return $this->getUrl('*/*/generate', ['id' => $this->_getFeed()->getId()]);
    }

    /**
     * Get form scripts including batch generation JavaScript
     */
    protected function _getFormScripts(): string
    {
        $initUrl = $this->getUrl('*/*/generateInit');
        $batchUrl = $this->getUrl('*/*/generateBatch');
        $finalizeUrl = $this->getUrl('*/*/generateFinalize');
        $cancelUrl = $this->getUrl('*/*/generateCancel');
        $resetUrl = $this->getUrl('*/*/forceReset');
        $uploadUrl = $this->getUrl('*/*/upload');

        // Check if feed has a destination configured
        $hasDestination = (bool) $this->_getFeed()->getDestinationId();

        $translations = Mage::helper('core')->jsonEncode([
            'generating' => $this->__('Generating Feed...'),
            'initializing' => $this->__('Initializing...'),
            'processing' => $this->__('Processing batch %s of %s...'),
            'finalizing' => $this->__('Finalizing...'),
            'completed' => $this->__('Feed generated successfully!'),
            'failed' => $this->__('Generation failed'),
            'cancelled' => $this->__('Generation cancelled'),
            'products' => $this->__('%s products'),
            'cancel' => $this->__('Cancel'),
            'close' => $this->__('Close'),
            'download' => $this->__('Download'),
            'upload' => $this->__('Upload Now'),
            'uploading' => $this->__('Uploading...'),
            'upload_success' => $this->__('Upload successful!'),
            'upload_failed' => $this->__('Upload failed'),
            'upload_skipped' => $this->__('Upload skipped'),
            'confirm' => $this->__('Are you sure you want to generate this feed now?'),
            'confirm_reset' => $this->__('This will clear any stuck generation status. Continue?'),
            'reset_success' => $this->__('Generation status has been reset.'),
        ]);

        return <<<JS

            function saveAndContinueEdit() {
                var form = document.getElementById('edit_form');
                if (form) {
                    var action = form.action;
                    if (action.indexOf('?') !== -1) {
                        form.action = action + '&back=edit';
                    } else {
                        form.action = action + 'back/edit/';
                    }
                    form.submit();
                }
            }

            const FeedGenerator = {
                urls: {
                    init: '{$initUrl}',
                    batch: '{$batchUrl}',
                    finalize: '{$finalizeUrl}',
                    cancel: '{$cancelUrl}',
                    reset: '{$resetUrl}',
                    upload: '{$uploadUrl}'
                },
                translations: {$translations},
                hasDestination: {$this->_boolToJs($hasDestination)},
                currentJobId: null,
                currentFeedId: null,
                cancelled: false,
                modal: null,

                saveAndGenerate: function(feedId) {
                    if (!confirm(this.translations.confirm)) {
                        return;
                    }

                    // Always save first, then generate
                    var form = document.getElementById('edit_form');
                    if (form) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'generate_after_save';
                        input.value = '1';
                        form.appendChild(input);
                        form.submit();
                    } else {
                        // Fallback if no form
                        this.start(feedId);
                    }
                },

                start: function(feedId, force) {
                    this.cancelled = false;
                    this.currentFeedId = feedId;
                    this.showModal();
                    this.updateProgress(0, 0, this.translations.initializing);

                    var params = { id: feedId, form_key: FORM_KEY };
                    if (force) {
                        params.force = '1';
                    }

                    mahoFetch(this.urls.init, {
                        method: 'POST',
                        body: new URLSearchParams(params),
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
                    this.hideModal();
                },

                reset: function(feedId) {
                    if (!confirm(this.translations.confirm_reset)) {
                        return;
                    }

                    mahoFetch(this.urls.reset, {
                        method: 'POST',
                        body: new URLSearchParams({ id: feedId, form_key: FORM_KEY }),
                        loaderArea: false
                    })
                    .then(data => {
                        if (data.error) {
                            alert(data.message);
                        } else {
                            alert(this.translations.reset_success);
                        }
                    })
                    .catch(error => {
                        alert(error.message || 'Error resetting generation');
                    });
                },

                showModal: function() {
                    if (!this.modal) {
                        this.createModal();
                    }
                    this.modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                },

                hideModal: function() {
                    if (this.modal) {
                        this.modal.style.display = 'none';
                    }
                    document.body.style.overflow = '';
                    this.currentJobId = null;
                },

                createModal: function() {
                    const modal = document.createElement('div');
                    modal.id = 'feed-generator-modal';
                    modal.innerHTML = `
                        <style>
                            #feed-generator-modal {
                                position: fixed;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background: rgba(0,0,0,0.6);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                z-index: 10000;
                            }
                            #feed-generator-modal .modal-content {
                                background: #fff;
                                border-radius: 8px;
                                padding: 24px;
                                min-width: 400px;
                                max-width: 500px;
                                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                            }
                            #feed-generator-modal .modal-title {
                                font-size: 18px;
                                font-weight: 600;
                                margin-bottom: 16px;
                                color: #333;
                            }
                            #feed-generator-modal .progress-container {
                                margin-bottom: 16px;
                            }
                            #feed-generator-modal .progress-bar-bg {
                                height: 8px;
                                background: #e0e0e0;
                                border-radius: 4px;
                                overflow: hidden;
                            }
                            #feed-generator-modal .progress-bar {
                                height: 100%;
                                background: linear-gradient(90deg, #4CAF50, #8BC34A);
                                border-radius: 4px;
                                transition: width 0.3s ease;
                                width: 0%;
                            }
                            #feed-generator-modal .progress-text {
                                margin-top: 8px;
                                font-size: 13px;
                                color: #666;
                            }
                            #feed-generator-modal .status-message {
                                font-size: 14px;
                                color: #333;
                                margin-bottom: 16px;
                            }
                            #feed-generator-modal .error-message {
                                color: #d32f2f;
                                background: #ffebee;
                                padding: 12px;
                                border-radius: 4px;
                                margin-bottom: 16px;
                            }
                            #feed-generator-modal .success-message {
                                color: #2e7d32;
                                background: #e8f5e9;
                                padding: 12px;
                                border-radius: 4px;
                                margin-bottom: 16px;
                            }
                            #feed-generator-modal .modal-buttons {
                                display: flex;
                                gap: 8px;
                                justify-content: flex-end;
                            }
                            #feed-generator-modal .modal-btn {
                                padding: 8px 16px;
                                border: none;
                                border-radius: 4px;
                                cursor: pointer;
                                font-size: 14px;
                                transition: background 0.2s;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                text-decoration: none;
                                line-height: 1;
                            }
                            #feed-generator-modal .btn-cancel {
                                background: #f5f5f5;
                                color: #333;
                            }
                            #feed-generator-modal .btn-cancel:hover {
                                background: #e0e0e0;
                            }
                            #feed-generator-modal .btn-primary {
                                background: #1976d2;
                                color: #fff;
                            }
                            #feed-generator-modal .btn-primary:hover {
                                background: #1565c0;
                            }
                            #feed-generator-modal .btn-success {
                                background: #4CAF50;
                                color: #fff;
                            }
                            #feed-generator-modal .btn-success:hover {
                                background: #43A047;
                            }
                        </style>
                        <div class="modal-content">
                            <div class="modal-title">\${this.translations.generating}</div>
                            <div class="status-message" id="gen-status"></div>
                            <div class="progress-container">
                                <div class="progress-bar-bg">
                                    <div class="progress-bar" id="gen-progress-bar"></div>
                                </div>
                                <div class="progress-text" id="gen-progress-text"></div>
                            </div>
                            <div class="modal-buttons" id="gen-buttons">
                                <button class="modal-btn btn-cancel" onclick="FeedGenerator.cancel()">\${this.translations.cancel}</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    this.modal = modal;
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

                showError: function(message) {
                    const statusEl = document.getElementById('gen-status');
                    const buttonsEl = document.getElementById('gen-buttons');

                    if (statusEl) {
                        statusEl.innerHTML = '<div class="error-message">' + this.escapeHtml(message) + '</div>';
                    }
                    if (buttonsEl) {
                        buttonsEl.innerHTML = '<button class="modal-btn btn-cancel" onclick="FeedGenerator.hideModal()">' +
                            this.translations.close + '</button>';
                    }
                },

                showSuccess: function(data) {
                    const statusEl = document.getElementById('gen-status');
                    const buttonsEl = document.getElementById('gen-buttons');
                    const progressBar = document.getElementById('gen-progress-bar');

                    if (progressBar) progressBar.style.width = '100%';

                    let successHtml = '<div class="success-message">' +
                        this.translations.completed + '<br>' +
                        this.translations.products.replace('%s', data.product_count || 0);
                    if (data.file_size_formatted) {
                        successHtml += ' (' + data.file_size_formatted + ')';
                    }
                    successHtml += '</div>';

                    // Show upload status if available
                    if (data.upload_status) {
                        const uploadStatusClass = data.upload_status === 'success' ? 'success-message' :
                            (data.upload_status === 'failed' ? 'error-message' : 'info-message');
                        const uploadIcon = data.upload_status === 'success' ? '✓' :
                            (data.upload_status === 'failed' ? '✗' : '⊘');
                        successHtml += '<div class="' + uploadStatusClass + '" style="margin-top:8px">' +
                            uploadIcon + ' Upload: ' + (data.upload_message || data.upload_status) + '</div>';
                    }

                    if (statusEl) statusEl.innerHTML = successHtml;

                    let buttonsHtml = '<button class="modal-btn btn-cancel" onclick="FeedGenerator.hideModal()">' +
                        this.translations.close + '</button>';

                    // Show Upload Now button if destination is configured and upload wasn't successful
                    if (this.hasDestination && data.upload_status !== 'success') {
                        buttonsHtml += ' <button class="modal-btn btn-primary" id="upload-now-btn" onclick="FeedGenerator.upload()">' +
                            this.translations.upload + '</button>';
                    }

                    if (data.file_url) {
                        const cacheBuster = data.file_url.includes('?') ? '&_=' : '?_=';
                        buttonsHtml += ' <a href="' + data.file_url + cacheBuster + Date.now() + '" class="modal-btn btn-success" target="_blank">' +
                            this.translations.download + '</a>';
                    }
                    if (buttonsEl) buttonsEl.innerHTML = buttonsHtml;
                },

                upload: function() {
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
                        const statusEl = document.getElementById('gen-status');
                        if (data.success) {
                            // Update status area with success
                            const existingContent = statusEl ? statusEl.innerHTML : '';
                            const uploadHtml = '<div class="success-message" style="margin-top:8px">✓ ' +
                                this.escapeHtml(data.message) + '</div>';
                            if (statusEl) {
                                // Remove any existing upload status and add new one
                                statusEl.innerHTML = existingContent.replace(/<div class="(success|error|info)-message" style="margin-top:8px">.*?<\\/div>/g, '') + uploadHtml;
                            }
                            if (btn) btn.remove();
                        } else {
                            if (btn) {
                                btn.disabled = false;
                                btn.textContent = this.translations.upload;
                            }
                            alert(data.message || this.translations.upload_failed);
                        }
                    })
                    .catch(error => {
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = this.translations.upload;
                        }
                        alert(error.message || this.translations.upload_failed);
                    });
                },

                escapeHtml: function(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }
            };

            // Auto-start generation if URL has generate/1 parameter (after save)
            document.addEventListener('DOMContentLoaded', function() {
                // Check for both path-based (/generate/1/) and query string (?generate=1) formats
                var shouldGenerate = window.location.pathname.indexOf('/generate/1') !== -1 ||
                                     window.location.search.indexOf('generate=1') !== -1;
                var feedId = {$this->_getFeedIdForJs()};

                if (shouldGenerate && feedId) {
                    // Remove the generate param from URL to prevent re-triggering on refresh
                    var newUrl = window.location.pathname.replace(/\\/generate\\/1\\/?/, '/');
                    window.history.replaceState({}, '', newUrl);

                    // Start generation after a short delay to let the page load
                    // Use force=true to clean up any stuck jobs from the previous save attempt
                    setTimeout(function() {
                        FeedGenerator.start(feedId, true);
                    }, 500);
                }
            });

JS;
    }
}
