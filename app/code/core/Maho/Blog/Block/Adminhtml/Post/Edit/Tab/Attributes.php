<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Attributes extends Mage_Adminhtml_Block_Catalog_Form
{
    /**
     * Load Wysiwyg on demand and prepare layout
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if ($this->isModuleEnabled('Mage_Cms', 'catalog')
            && Mage::getSingleton('cms/wysiwyg_config')->isEnabled()
        ) {
            $this->getLayout()->getBlock('head')->setCanLoadTinyMce(true);
        }
        return $this;
    }
}
