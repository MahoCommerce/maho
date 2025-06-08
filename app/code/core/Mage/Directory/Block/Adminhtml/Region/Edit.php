<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Region_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_region';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Region'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Region'));

        $this->_addButton('save_and_continue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => 'DirectoryEditForm.saveAndContinueEdit()',
            'class' => 'save',
        ], -100);
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $region = Mage::registry('current_region');
        if ($region->getId()) {
            return Mage::helper('adminhtml')->__('Edit Region "%s"', $this->escapeHtml($region->getName()));
        } else {
            return Mage::helper('adminhtml')->__('New Region');
        }
    }
}
