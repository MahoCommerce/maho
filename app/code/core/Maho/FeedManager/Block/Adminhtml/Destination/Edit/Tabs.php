<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Destination_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('destination_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Destination Configuration'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('general', [
            'label' => $this->__('General Settings'),
            'title' => $this->__('General Settings'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_destination_edit_tab_general')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
