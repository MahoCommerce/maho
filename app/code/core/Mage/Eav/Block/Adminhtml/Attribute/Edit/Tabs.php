<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('eav_attribute_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('eav')->__('Attribute Information'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addTab('main', [
            'label'     => Mage::helper('eav')->__('Properties'),
            'title'     => Mage::helper('eav')->__('Properties'),
            'content'   => $this->getLayout()->createBlock('eav/adminhtml_attribute_edit_tab_main')->toHtml(),
            'active'    => true
        ]);

        $model = Mage::registry('entity_attribute');

        $this->addTab('labels', [
            'label'     => Mage::helper('eav')->__('Manage Label / Options'),
            'title'     => Mage::helper('eav')->__('Manage Label / Options'),
            'content'   => $this->getLayout()->createBlock('eav/adminhtml_attribute_edit_tab_options')->toHtml(),
        ]);

        return parent::_beforeToHtml();
    }
}
