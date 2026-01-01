<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'giftcard';
        $this->_controller = 'adminhtml_giftcard';

        $this->_updateButton('save', 'label', Mage::helper('giftcard')->__('Save Gift Card'));
        $this->_updateButton('delete', 'label', Mage::helper('giftcard')->__('Delete Gift Card'));

        $this->_addButton('saveandcontinue', [
            'label'   => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save',
        ], -100);

        $model = Mage::registry('current_giftcard');
        if ($model && $model->getId()) {
            // Add Print PDF button
            $this->_addButton('print_pdf', [
                'label'   => Mage::helper('giftcard')->__('Print PDF'),
                'onclick' => 'window.open(\'' . $this->getUrl('*/giftcard_print/pdf', ['id' => $model->getId()]) . '\')',
                'class'   => 'go',
            ], -1);

            // Add Send Email button (only if recipient email is set)
            if ($model->getRecipientEmail()) {
                $this->_addButton('send_email', [
                    'label'   => Mage::helper('giftcard')->__('Send Email'),
                    'onclick' => 'if(confirm(\'' . Mage::helper('giftcard')->__('Send gift card email to %s?', $model->getRecipientEmail()) . '\')) { setLocation(\'' . $this->getUrl('*/giftcard_print/email', ['id' => $model->getId()]) . '\'); }',
                    'class'   => 'go',
                ], -1);
            }
        }

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                const form = document.getElementById('edit_form');
                if (form) {
                    editForm.submit(form.action + 'back/edit/');
                }
            }
        ";
    }

    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('current_giftcard') && Mage::registry('current_giftcard')->getId()) {
            return Mage::helper('giftcard')->__(
                "Edit Gift Card '%s'",
                $this->escapeHtml(Mage::registry('current_giftcard')->getCode()),
            );
        }
        return Mage::helper('giftcard')->__('New Gift Card');
    }
}
