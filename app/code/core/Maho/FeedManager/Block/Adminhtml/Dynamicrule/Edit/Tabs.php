<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('dynamicrule_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle($this->__('Dynamic Rule'));
    }

    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->addTab('general', [
            'label' => $this->__('General'),
            'title' => $this->__('General'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_dynamicrule_edit_tab_general')->toHtml(),
        ]);

        $this->addTab('conditions', [
            'label' => $this->__('Output Rules'),
            'title' => $this->__('Output Rules'),
            'content' => $this->getLayout()->createBlock('feedmanager/adminhtml_dynamicrule_edit_tab_conditions')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
