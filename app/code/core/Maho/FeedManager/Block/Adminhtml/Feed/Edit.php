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
                ...FeedGeneratorBase,
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
                currentFeedId: null,

                saveAndGenerate(feedId) {
                    if (!confirm(this.translations.confirm)) {
                        return;
                    }

                    // Always save first, then generate
                    const form = document.getElementById('edit_form');
                    if (form) {
                        const input = document.createElement('input');
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

                start(feedId, force) {
                    this.cancelled = false;
                    this.currentFeedId = feedId;
                    this.showDialog();
                    this.updateProgress(0, 0, this.translations.initializing);

                    const params = { id: feedId, form_key: FORM_KEY };
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

                reset(feedId) {
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

                showSuccess(data) {
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

                    // Show upload status if available
                    if (data.upload_status) {
                        const uploadStatusClass = data.upload_status === 'success' ? 'success-msg' :
                            (data.upload_status === 'failed' ? 'error-msg' : 'notice-msg');
                        const uploadIcon = data.upload_status === 'success' ? '✓' :
                            (data.upload_status === 'failed' ? '✗' : '⊘');
                        successHtml += '<div class="' + uploadStatusClass + ' fm-upload-status">' +
                            uploadIcon + ' Upload: ' + (data.upload_message || data.upload_status) + '</div>';
                    }

                    if (statusEl) statusEl.innerHTML = successHtml;

                    let buttonsHtml = '<button type="button" class="cancel" onclick="FeedGenerator.closeDialog()">' +
                        this.translations.close + '</button>';

                    // Show Upload Now button if destination is configured and upload wasn't successful
                    if (this.hasDestination && data.upload_status !== 'success') {
                        buttonsHtml += ' <button type="button" id="upload-now-btn" onclick="FeedGenerator.upload()">' +
                            this.translations.upload + '</button>';
                    }

                    if (data.file_url) {
                        const cacheBuster = data.file_url.includes('?') ? '&_=' : '?_=';
                        buttonsHtml += ' <a href="' + escapeHtml(data.file_url + cacheBuster + Date.now(), true) + '" class="form-button" target="_blank">' +
                            this.translations.download + '</a>';
                    }
                    if (buttonsEl) buttonsEl.innerHTML = buttonsHtml;
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
                        const statusEl = document.getElementById('gen-status');
                        if (data.success) {
                            // Update status area with success
                            const existingContent = statusEl ? statusEl.innerHTML : '';
                            const uploadHtml = '<div class="success-msg fm-upload-status">✓ ' +
                                escapeHtml(data.message) + '</div>';
                            if (statusEl) {
                                // Remove any existing upload status and add new one
                                statusEl.innerHTML = existingContent.replace(/<div class="(success|error|notice)-msg fm-upload-status">.*?<\\/div>/g, '') + uploadHtml;
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
