<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Destination_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'feedmanager';
        $this->_controller = 'adminhtml_destination';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save Destination'));
        $this->_updateButton('delete', 'label', $this->__('Delete Destination'));

        $this->_addButton('saveandcontinue', [
            'label' => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                var form = document.getElementById('edit_form');
                if (form) {
                    form.action = form.action + 'back/edit/';
                    form.submit();
                }
            }
        ";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $destination = $this->_getDestination();
        if ($destination->getId()) {
            return $this->__("Edit Destination '%s'", $this->escapeHtml($destination->getName()));
        }
        return $this->__('New Destination');
    }

    protected function _getDestination(): Maho_FeedManager_Model_Destination
    {
        return Mage::registry('current_destination') ?: Mage::getModel('feedmanager/destination');
    }

    public function getTestUrl(): string
    {
        return $this->getUrl('*/*/test', ['id' => $this->_getDestination()->getId()]);
    }
}
