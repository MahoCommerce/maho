<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Category extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_category';
        $this->_blockGroup = 'feedmanager';
        $this->_headerText = Mage::helper('feedmanager')->__('Category Mapping');
        parent::__construct();

        $this->removeButton('add');

        $this->_addButton('add_mapping', [
            'label' => $this->__('Add New Mapping'),
            'onclick' => 'CategoryPlatformPicker.toggle()',
            'class' => 'add',
            'id' => 'fm-add-mapping-btn',
        ]);
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        $html .= $this->_getPlatformPickerScript();
        return $html;
    }

    /**
     * Get platform picker dropdown JS/HTML
     */
    protected function _getPlatformPickerScript(): string
    {
        $platforms = [];
        foreach (Maho_FeedManager_Model_Platform::getAvailablePlatforms() as $code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            if ($adapter) {
                $platforms[] = [
                    'code' => $code,
                    'name' => $adapter->getName(),
                    'url' => $this->getUrl('*/*/edit', ['platform' => $code]),
                ];
            }
        }

        $platformsJson = Mage::helper('core')->jsonEncode($platforms);

        return <<<HTML
<script>
const CategoryPlatformPicker = {
    el: null,

    toggle: function() {
        if (!this.el) {
            this.create();
        }
        this.el.style.display = this.el.style.display === 'block' ? 'none' : 'block';
    },

    create: function() {
        const platforms = {$platformsJson};
        const btn = document.getElementById('fm-add-mapping-btn');
        if (!btn) return;

        const wrapper = btn.parentElement;
        wrapper.style.position = 'relative';

        this.el = document.createElement('div');
        this.el.className = 'fm-platform-dropdown';
        this.el.style.display = 'none';

        let html = '';
        platforms.forEach(function(p) {
            html += '<a class="fm-platform-dropdown-item" href="javascript:void(0)" '
                  + 'onclick="setLocation(\'' + p.url + '\')">'
                  + escapeHtml(p.name) + '</a>';
        });

        this.el.innerHTML = html;
        wrapper.appendChild(this.el);

        document.addEventListener('click', function(e) {
            if (!btn.contains(e.target) && !CategoryPlatformPicker.el.contains(e.target)) {
                CategoryPlatformPicker.el.style.display = 'none';
            }
        });
    }
};
</script>
HTML;
    }
}
