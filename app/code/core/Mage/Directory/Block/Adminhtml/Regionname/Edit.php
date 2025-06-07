<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'directory';
        $this->_controller = 'adminhtml_regionname';

        $this->_updateButton('save', 'label', Mage::helper('adminhtml')->__('Save Region Name'));
        $this->_updateButton('delete', 'label', Mage::helper('adminhtml')->__('Delete Region Name'));
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild('form', $this->getLayout()->createBlock('directory/adminhtml_regionname_edit_form'));

        $result = parent::_prepareLayout();

        // Handle delete button based on whether we're editing or creating
        $regionName = Mage::registry('current_region_name');
        if ($regionName && isset($regionName['locale']) && isset($regionName['region_id']) && $regionName['locale'] && $regionName['region_id']) {
            // Add delete button for existing records
            $deleteUrl = $this->getUrl('*/*/delete', [
                'locale' => $regionName['locale'],
                'region_id' => $regionName['region_id'],
            ]);

            $this->_addButton('delete', [
                'label' => Mage::helper('adminhtml')->__('Delete'),
                'onclick' => "deleteConfirm('" . Mage::helper('adminhtml')->__('Are you sure you want to delete this region name?') . "', '" . $deleteUrl . "')",
                'class' => 'delete',
            ], -1);
        }

        return $result;
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $regionName = Mage::registry('current_region_name');
        if ($regionName && isset($regionName['region_id']) && $regionName['region_id']) {
            return Mage::helper('adminhtml')->__('Edit Region Name');
        } else {
            return Mage::helper('adminhtml')->__('New Region Name');
        }
    }
}
