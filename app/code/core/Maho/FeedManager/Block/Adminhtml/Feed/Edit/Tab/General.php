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

        // Platform is set automatically when loading a preset in the Mapping tab
        $fieldset->addField('platform', 'hidden', [
            'name' => 'platform',
            'value' => $feed->getPlatform() ?: 'custom',
        ]);

        $fieldset->addField('file_format', 'select', [
            'name' => 'file_format',
            'label' => $this->__('File Format'),
            'title' => $this->__('File Format'),
            'required' => true,
            'values' => Mage::helper('feedmanager')->getFileFormatOptions(),
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
}
