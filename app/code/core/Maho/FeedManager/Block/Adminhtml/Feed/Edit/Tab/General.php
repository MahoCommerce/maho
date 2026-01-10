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

        $form->setValues($feed->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _getFeed(): Maho_FeedManager_Model_Feed
    {
        return Mage::registry('current_feed') ?: Mage::getModel('feedmanager/feed');
    }
}
