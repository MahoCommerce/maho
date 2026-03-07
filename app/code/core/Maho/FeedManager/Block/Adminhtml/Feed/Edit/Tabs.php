<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    use Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait;

    public function __construct()
    {
        parent::__construct();
        $this->setId('feed_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Feed Configuration'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('general', [
            'label' => $this->__('General Settings'),
            'title' => $this->__('General Settings'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_feed_edit_tab_general')->toHtml(),
        ]);

        $this->addTab('mapping', [
            'label' => $this->__('Feed Content'),
            'title' => $this->__('Feed Content'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_feed_edit_tab_mapping')->toHtml(),
        ]);

        $this->addTab('filters', [
            'label' => $this->__('Product Filters'),
            'title' => $this->__('Product Filters'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_feed_edit_tab_filters')->toHtml(),
        ]);

        // Only show logs tab for existing feeds
        if ($this->_getFeed()->getId()) {
            $this->addTab('logs', [
                'label' => $this->__('Generation History'),
                'title' => $this->__('Generation History'),
                'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_feed_edit_tab_logs')->toHtml(),
            ]);
        }

        return parent::_beforeToHtml();
    }

    #[\Override]
    protected function _afterToHtml($html)
    {
        $html = parent::_afterToHtml($html);

        // Add JavaScript to switch to tab from query parameter
        $script = <<<'JS'
<script>
(function() {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    if (!tabParam) return;

    function activateTab() {
        const tabId = 'feed_tabs_' + tabParam;
        const tabElement = document.getElementById(tabId);
        if (tabElement) {
            // Try clicking the tab link
            tabElement.click();
            return true;
        }
        return false;
    }

    // Try immediately, then with delays for tabs that initialize late
    if (!activateTab()) {
        setTimeout(activateTab, 100);
        setTimeout(activateTab, 300);
    }
    // Also try on window load as fallback
    window.addEventListener('load', activateTab);
})();
</script>
JS;

        return $html . $script;
    }
}
