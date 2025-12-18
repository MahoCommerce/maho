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

class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('current_giftcard');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => Mage::helper('giftcard')->__('Gift Card Information')]);

        if ($model->getId()) {
            $fieldset->addField('giftcard_id', 'hidden', [
                'name' => 'giftcard_id',
            ]);
        }

        $fieldset->addField('code', 'text', [
            'name'     => 'code',
            'label'    => Mage::helper('giftcard')->__('Code'),
            'title'    => Mage::helper('giftcard')->__('Code'),
            'required' => false,
            'note'     => 'Leave empty to auto-generate',
            'disabled' => $model->getId() ? true : false,
        ]);

        $fieldset->addField('status', 'select', [
            'label'    => Mage::helper('giftcard')->__('Status'),
            'title'    => Mage::helper('giftcard')->__('Status'),
            'name'     => 'status',
            'required' => true,
            'options'  => [
                Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE => 'Active',
                Maho_Giftcard_Model_Giftcard::STATUS_DISABLED => 'Disabled',
                Maho_Giftcard_Model_Giftcard::STATUS_USED => 'Used',
                Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED => 'Expired',
            ],
        ]);

        $fieldset->addField('balance', 'text', [
            'name'     => 'balance',
            'label'    => Mage::helper('giftcard')->__('Balance'),
            'title'    => Mage::helper('giftcard')->__('Balance'),
            'required' => true,
            'class'    => 'validate-number',
        ]);

        $fieldset->addField('initial_balance', 'text', [
            'name'     => 'initial_balance',
            'label'    => Mage::helper('giftcard')->__('Initial Balance'),
            'title'    => Mage::helper('giftcard')->__('Initial Balance'),
            'required' => !$model->getId(),
            'class'    => 'validate-number',
            'disabled' => $model->getId() ? true : false,
        ]);

        $fieldset->addField('currency_code', 'text', [
            'name'     => 'currency_code',
            'label'    => Mage::helper('giftcard')->__('Currency Code'),
            'title'    => Mage::helper('giftcard')->__('Currency Code'),
            'required' => !$model->getId(),
            'value'    => $model->getCurrencyCode() ?: Mage::app()->getStore()->getCurrentCurrencyCode(),
        ]);

        $fieldset->addField('expires_at', 'date', [
            'name'   => 'expires_at',
            'label'  => Mage::helper('giftcard')->__('Expires At'),
            'title'  => Mage::helper('giftcard')->__('Expires At'),
            'image'  => $this->getSkinUrl('images/grid-cal.gif'),
            'format' => 'yyyy-MM-dd',
            'note'   => 'Leave empty for no expiration',
        ]);

        $fieldset->addField('recipient_name', 'text', [
            'name'  => 'recipient_name',
            'label' => Mage::helper('giftcard')->__('Recipient Name'),
            'title' => Mage::helper('giftcard')->__('Recipient Name'),
        ]);

        $fieldset->addField('recipient_email', 'text', [
            'name'  => 'recipient_email',
            'label' => Mage::helper('giftcard')->__('Recipient Email'),
            'title' => Mage::helper('giftcard')->__('Recipient Email'),
            'class' => 'validate-email',
        ]);

        $fieldset->addField('sender_name', 'text', [
            'name'  => 'sender_name',
            'label' => Mage::helper('giftcard')->__('Sender Name'),
            'title' => Mage::helper('giftcard')->__('Sender Name'),
        ]);

        $fieldset->addField('sender_email', 'text', [
            'name'  => 'sender_email',
            'label' => Mage::helper('giftcard')->__('Sender Email'),
            'title' => Mage::helper('giftcard')->__('Sender Email'),
            'class' => 'validate-email',
        ]);

        $fieldset->addField('message', 'textarea', [
            'name'  => 'message',
            'label' => Mage::helper('giftcard')->__('Message'),
            'title' => Mage::helper('giftcard')->__('Message'),
        ]);

        $fieldset->addField('comment', 'textarea', [
            'name'  => 'comment',
            'label' => Mage::helper('giftcard')->__('Admin Comment'),
            'title' => Mage::helper('giftcard')->__('Admin Comment'),
            'note'  => 'For admin records (balance adjustments)',
        ]);

        // Show QR code and barcode for existing gift cards
        if ($model->getId()) {
            $helper = Mage::helper('giftcard');

            $fieldset->addField('qr_barcode_display', 'note', [
                'label' => Mage::helper('giftcard')->__('QR Code & Barcode'),
                'text'  => $this->_getQrBarcodeHtml($model, $helper),
            ]);
        }

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get QR code and barcode HTML
     *
     * @param Maho_Giftcard_Model_Giftcard $model
     * @param Maho_Giftcard_Helper_Data $helper
     */
    protected function _getQrBarcodeHtml($model, $helper): string
    {
        $html = '<div style="display: flex; gap: 20px; align-items: flex-start;">';

        if ($helper->isQrCodeEnabled()) {
            $qrUrl = $helper->getQrCodeDataUrl($model->getCode(), 200);
            $html .= '<div style="text-align: center;">';
            $html .= '<div style="margin-bottom: 5px;"><strong>QR Code (Scannable)</strong></div>';
            $html .= '<img src="' . htmlspecialchars($qrUrl) . '" alt="QR Code" style="border: 1px solid #ccc; padding: 5px; background: white;" />';
            $html .= '<div style="margin-top: 5px; font-family: monospace;">' . htmlspecialchars($model->getCode()) . '</div>';
            $html .= '</div>';
        }

        if ($helper->isBarcodeEnabled()) {
            $barcodeUrl = $helper->getBarcodeDataUrl($model->getCode());
            $html .= '<div style="text-align: center;">';
            $html .= '<div style="margin-bottom: 5px;"><strong>Barcode (Code128)</strong></div>';
            $html .= '<img src="' . htmlspecialchars($barcodeUrl) . '" alt="Barcode" style="border: 1px solid #ccc; padding: 5px; background: white; max-width: 300px;" />';
            $html .= '<div style="margin-top: 5px; font-family: monospace;">' . htmlspecialchars($model->getCode()) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
