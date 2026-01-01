<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_country';

        parent::__construct();

        $this->_updateButton('save', 'label', Mage::helper('directory')->__('Save Country'));
        $this->_updateButton('delete', 'label', Mage::helper('directory')->__('Delete Country'));

        $this->_addButton('save_and_continue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => 'DirectoryEditForm.saveAndContinueEdit()',
            'class' => 'save',
        ], -100);
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $country = Mage::registry('current_country');
        if ($country->getOrigData('country_id')) {
            return Mage::helper('directory')->__('Edit Country "%s"', $this->escapeHtml($country->getName()));
        }
        return Mage::helper('directory')->__('New Country');
    }
}
