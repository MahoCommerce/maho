<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    #[\Override]
    protected function _prepareForm(): self
    {
        $feed = $this->_getFeed();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('feed_');

        // Basic Information
        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Feed Information'),
        ]);

        if ($feed->getId()) {
            $fieldset->addField('feed_id', 'hidden', [
                'name' => 'feed_id',
            ]);

            // Show Feed URL and Last Generated at top for existing feeds
            $feedUrl = Mage::helper('feedmanager')->getFeedUrl($feed);
            $fieldset->addField('feed_url', 'note', [
                'label' => $this->__('Feed URL'),
                'text' => '<a href="' . $this->escapeHtml($feedUrl) . '" target="_blank">' . $this->escapeHtml($feedUrl) . '</a>',
            ]);

            if ($feed->getLastGeneratedAt()) {
                $fieldset->addField('last_generated', 'note', [
                    'label' => $this->__('Last Generated'),
                    'text' => Mage::helper('core')->formatDate($feed->getLastGeneratedAt(), 'medium', true) .
                        ' (' . $feed->getLastProductCount() . ' ' . $this->__('products') . ')',
                ]);
            }
        }

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => $this->__('Feed Name'),
            'title' => $this->__('Feed Name'),
            'required' => true,
        ]);

        $fieldset->addField('filename', 'text', [
            'name' => 'filename',
            'label' => $this->__('Filename'),
            'title' => $this->__('Filename'),
            'required' => true,
            'note' => $this->__('Without extension. E.g., "google_feed" will create "google_feed.xml"'),
        ]);

        // Hidden fields for platform and format (set by template selector)
        $fieldset->addField('platform', 'hidden', [
            'name' => 'platform',
            'value' => $feed->getPlatform() ?: 'custom',
        ]);

        $fieldset->addField('file_format', 'hidden', [
            'name' => 'file_format',
            'value' => $feed->getFileFormat() ?: 'xml',
        ]);

        // Combined template/format selector
        $fieldset->addField('template_selector', 'select', [
            'name' => 'template_selector',
            'label' => $this->__('Feed Template'),
            'title' => $this->__('Feed Template'),
            'required' => true,
            'values' => $this->_getTemplateOptions(),
            'value' => $this->_getCurrentTemplateValue($feed),
            'note' => $this->__('Select a platform template for pre-configured mappings, or choose a custom format.'),
            'after_element_html' => $this->_getTemplateSelectorScript(),
        ]);

        $fieldset->addField('store_id', 'select', [
            'name' => 'store_id',
            'label' => $this->__('Store View'),
            'title' => $this->__('Store View'),
            'required' => true,
            'values' => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, false),
        ]);

        $fieldset->addField('is_enabled', 'select', [
            'name' => 'is_enabled',
            'label' => $this->__('Enabled'),
            'title' => $this->__('Enabled'),
            'required' => true,
            'values' => [
                ['value' => 1, 'label' => $this->__('Yes')],
                ['value' => 0, 'label' => $this->__('No')],
            ],
        ]);

        // Schedule Settings
        $scheduleFieldset = $form->addFieldset('schedule_fieldset', [
            'legend' => $this->__('Schedule'),
        ]);

        $scheduleFieldset->addField('schedule', 'select', [
            'name' => 'schedule',
            'label' => $this->__('Generation Schedule'),
            'title' => $this->__('Generation Schedule'),
            'values' => [
                ['value' => '', 'label' => $this->__('Manual Only')],
                ['value' => 'hourly', 'label' => $this->__('Every Hour')],
                ['value' => 'twice_daily', 'label' => $this->__('Twice Daily (00:00, 12:00)')],
                ['value' => 'daily', 'label' => $this->__('Daily (00:00)')],
                ['value' => '0,6,12,18', 'label' => $this->__('Every 6 Hours')],
            ],
        ]);

        // Upload Settings
        $uploadFieldset = $form->addFieldset('upload_fieldset', [
            'legend' => $this->__('Upload Settings'),
        ]);

        $destinations = Mage::getResourceModel('feedmanager/destination_collection')
            ->addEnabledFilter()
            ->toOptionHash();

        $uploadFieldset->addField('destination_id', 'select', [
            'name' => 'destination_id',
            'label' => $this->__('Upload Destination'),
            'title' => $this->__('Upload Destination'),
            'values' => $destinations,
            'note' => $this->__('Configure destinations under Catalog > Feed Manager > Upload Destinations'),
        ]);

        $uploadFieldset->addField('auto_upload', 'select', [
            'name' => 'auto_upload',
            'label' => $this->__('Auto Upload'),
            'title' => $this->__('Auto Upload'),
            'values' => [
                ['value' => 0, 'label' => $this->__('No')],
                ['value' => 1, 'label' => $this->__('Yes')],
            ],
            'note' => $this->__('Automatically upload feed after generation'),
        ]);

        $uploadFieldset->addField('gzip_compression', 'select', [
            'name' => 'gzip_compression',
            'label' => $this->__('Gzip Compression'),
            'title' => $this->__('Gzip Compression'),
            'values' => [
                ['value' => 0, 'label' => $this->__('No')],
                ['value' => 1, 'label' => $this->__('Yes')],
            ],
            'note' => $this->__('Compress the feed file with gzip (.gz extension)'),
        ]);

        // Add Upload Now button for existing feeds with a generated file and destination
        if ($feed->getId() && $feed->getLastGeneratedAt() && $feed->getDestinationId()) {
            $uploadUrl = $this->getUrl('*/*/upload', ['id' => $feed->getId()]);
            $uploadFieldset->addField('upload_now', 'note', [
                'label' => $this->__('Manual Upload'),
                'text' => '<button type="button" class="scalable" onclick="FeedUploader.upload(\'' . $uploadUrl . '\')" id="upload-now-btn-fieldset">'
                    . '<span><span><span>' . $this->__('Upload Now') . '</span></span></span></button>'
                    . '<span id="upload-status" class="fm-test-result"></span>'
                    . $this->_getUploadScript(),
            ]);
        } elseif ($feed->getId() && !$feed->getDestinationId()) {
            $uploadFieldset->addField('upload_now', 'note', [
                'label' => $this->__('Manual Upload'),
                'text' => '<span class="note">' . $this->__('Select a destination to enable manual upload') . '</span>',
            ]);
        } elseif ($feed->getId() && !$feed->getLastGeneratedAt()) {
            $uploadFieldset->addField('upload_now', 'note', [
                'label' => $this->__('Manual Upload'),
                'text' => '<span class="note">' . $this->__('Generate the feed first to enable upload') . '</span>',
            ]);
        }

        $form->setValues($feed->getData());

        // Set template_selector value AFTER setValues to ensure it's not overwritten
        $templateSelector = $form->getElement('template_selector');
        if ($templateSelector) {
            $templateSelector->setValue($this->_getCurrentTemplateValue($feed));
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get JavaScript for upload button
     */
    protected function _getUploadScript(): string
    {
        $uploading = addslashes($this->__('Uploading...'));
        $uploadNow = addslashes($this->__('Upload Now'));
        $success = addslashes($this->__('Upload successful!'));
        $failed = addslashes($this->__('Upload failed'));

        return <<<JS
<script>
const FeedUploader = {
    upload: function(url) {
        const btn = document.getElementById('upload-now-btn-fieldset');
        const status = document.getElementById('upload-status');

        if (btn) {
            btn.disabled = true;
            btn.querySelector('span span span').textContent = '{$uploading}';
        }
        if (status) {
            status.innerHTML = '';
        }

        mahoFetch(url, {
            method: 'POST',
            body: new URLSearchParams({ form_key: FORM_KEY }),
            loaderArea: false
        })
        .then(data => {
            if (btn) {
                btn.disabled = false;
                btn.querySelector('span span span').textContent = '{$uploadNow}';
            }
            if (data.success) {
                if (status) {
                    status.innerHTML = '<span class="fm-status-success">✓ ' + this.escapeHtml(data.message || '{$success}') + '</span>';
                }
            } else {
                if (status) {
                    status.innerHTML = '<span class="fm-status-error">✗ ' + this.escapeHtml(data.message || '{$failed}') + '</span>';
                }
            }
        })
        .catch(error => {
            if (btn) {
                btn.disabled = false;
                btn.querySelector('span span span').textContent = '{$uploadNow}';
            }
            if (status) {
                status.innerHTML = '<span class="fm-status-error">✗ ' + this.escapeHtml(error.message || '{$failed}') + '</span>';
            }
        });
    },
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
</script>
JS;
    }

    /**
     * Get template options for combined dropdown
     */
    protected function _getTemplateOptions(): array
    {
        $options = [];

        // Add platform templates
        $platformOptions = [];
        foreach (Maho_FeedManager_Model_Platform::getAvailablePlatforms() as $code) {
            if ($code === 'custom') {
                continue; // Skip custom, we'll add custom options separately
            }

            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            if (!$adapter) {
                continue;
            }

            $format = strtoupper($adapter->getDefaultFormat());
            $platformOptions[] = [
                'value' => $code . ':' . $adapter->getDefaultFormat(),
                'label' => $adapter->getName() . ' (' . $format . ')',
            ];
        }

        if (!empty($platformOptions)) {
            $options[] = ['label' => $this->__('Platform Templates'), 'value' => $platformOptions];
        }

        // Add custom format options as optgroup
        $customOptions = [
            ['value' => 'custom:xml', 'label' => $this->__('Custom XML')],
            ['value' => 'custom:csv', 'label' => $this->__('Custom CSV')],
            ['value' => 'custom:json', 'label' => $this->__('Custom JSON')],
            ['value' => 'custom:jsonl', 'label' => $this->__('Custom JSONL')],
        ];
        $options[] = ['label' => $this->__('Custom Formats'), 'value' => $customOptions];

        return $options;
    }

    /**
     * Get current template value for existing feeds
     */
    protected function _getCurrentTemplateValue(Maho_FeedManager_Model_Feed $feed): string
    {
        $platform = $feed->getPlatform() ?: 'custom';
        $format = $feed->getFileFormat() ?: 'xml';

        return $platform . ':' . $format;
    }

    /**
     * Get JavaScript for template selector
     */
    protected function _getTemplateSelectorScript(): string
    {
        return <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateSelect = document.getElementById('feed_template_selector');
    const platformField = document.getElementById('feed_platform');
    const formatField = document.getElementById('feed_file_format');

    if (templateSelect) {
        // Only sync hidden fields when user changes the selector
        templateSelect.addEventListener('change', function() {
            const value = this.value;
            if (!value || value.indexOf(':') === -1) return;

            const parts = value.split(':');
            const platform = parts[0];
            const format = parts[1];

            if (platformField) platformField.value = platform;
            if (formatField) formatField.value = format;

            // Trigger change event on format field to update builder visibility
            formatField.dispatchEvent(new Event('change'));
        });
    }
});
</script>
JS;
    }
}
