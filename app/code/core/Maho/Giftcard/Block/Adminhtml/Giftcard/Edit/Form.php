<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('current_giftcard');

        $form = new Maho\Data\Form([
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

        // Website selector with currency mapping. Multiselect so a card can
        // be valid on more than one website; on save the controller persists
        // these via setWebsiteIds() into the giftcard_website junction.
        // The legacy single `website_id` scalar is still written for back-compat
        // (currency derivation, FK constraint) — set to the first selection.
        $websites = Mage::app()->getWebsites();
        $websiteCurrencies = [];
        $websiteValues = [];
        foreach ($websites as $website) {
            $websiteValues[$website->getId()] = $website->getName();
            $websiteCurrencies[$website->getId()] = $website->getBaseCurrencyCode();
        }

        if (!$model->getId()) {
            $defaultWebsiteId = (int) array_key_first($websiteCurrencies);
            $defaultSelection = [$defaultWebsiteId];
            $defaultCurrency = $websiteCurrencies[$defaultWebsiteId] ?? '';
            $currencyNote = '<span class="giftcard-currency-note">[' . $defaultCurrency . ']</span>';
        } else {
            // Existing card — pre-select the websites already associated via
            // the junction. Editable so an admin can re-scope after the fact.
            $defaultSelection = $model->getWebsiteIds();
            if (empty($defaultSelection) && $model->getWebsiteId()) {
                // Pre-1.1.0 row that hasn't been backfilled yet — fall back
                // to the legacy scalar so the multiselect isn't blank.
                $defaultSelection = [(int) $model->getWebsiteId()];
            }
            $currencyNote = '[' . $model->getCurrencyCode() . ']';
        }

        $fieldset->addField('website_ids', 'multiselect', [
            'name'     => 'website_ids[]',
            'label'    => Mage::helper('giftcard')->__('Websites'),
            'title'    => Mage::helper('giftcard')->__('Websites'),
            'required' => true,
            'values'   => array_map(
                static fn($id, $name) => ['value' => (int) $id, 'label' => $name],
                array_keys($websiteValues),
                $websiteValues,
            ),
            'value'    => $defaultSelection,
            'note'     => Mage::helper('giftcard')->__('Hold Ctrl/Cmd to select multiple. Card balance is in the first selected website\'s base currency.'),
            'after_element_html' => $this->_getWebsiteCurrencyScript($websiteCurrencies),
        ]);

        if (!$model->getId()) {
            $fieldset->addField('balance', 'text', [
                'name'     => 'balance',
                'label'    => Mage::helper('giftcard')->__('Amount'),
                'title'    => Mage::helper('giftcard')->__('Amount'),
                'required' => true,
                'class'    => 'validate-number validate-greater-than-zero',
                'note'     => $currencyNote,
            ]);

            // Hidden field to sync initial_balance with balance on create
            $fieldset->addField('initial_balance', 'hidden', [
                'name'  => 'initial_balance',
            ]);
        } else {
            // Existing gift card - show initial balance as read-only reference
            $website = Mage::app()->getWebsite($model->getWebsiteId());
            $formattedInitialBalance = $website->getBaseCurrency()->formatPrecision(
                $model->getInitialBalance(),
                2,
                [],
                false,
            );

            $fieldset->addField('initial_balance_display', 'note', [
                'label' => Mage::helper('giftcard')->__('Initial Balance'),
                'text'  => $formattedInitialBalance,
            ]);

            // Current balance is editable for manual adjustments
            $fieldset->addField('balance', 'text', [
                'name'     => 'balance',
                'label'    => Mage::helper('giftcard')->__('Current Balance'),
                'title'    => Mage::helper('giftcard')->__('Current Balance'),
                'required' => true,
                'class'    => 'validate-number',
                'note'     => $currencyNote . '<br>' . Mage::helper('giftcard')->__('Edit this to manually adjust the balance. Use "Admin Comment" to explain the adjustment.'),
            ]);
        }

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
     * Get JavaScript for updating currency display when website changes
     *
     * @param array<int, string> $websiteCurrencies
     */
    protected function _getWebsiteCurrencyScript(array $websiteCurrencies): string
    {
        $currenciesJson = Mage::helper('core')->jsonEncode($websiteCurrencies);

        return <<<HTML
<script>
(function() {
    const websiteCurrencies = {$currenciesJson};

    function updateCurrencyDisplay() {
        const websiteSelect = document.getElementById('website_id');
        if (!websiteSelect) return;

        const websiteId = websiteSelect.value;
        const currency = websiteCurrencies[websiteId] || '';
        const currencyText = '[' + currency + ']';

        document.querySelectorAll('.giftcard-currency-note').forEach(function(el) {
            el.textContent = currencyText;
        });
    }

    function init() {
        const websiteSelect = document.getElementById('website_id');
        if (websiteSelect) {
            websiteSelect.addEventListener('change', updateCurrencyDisplay);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
HTML;
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
            $html .= '<img src="' . htmlspecialchars($qrUrl) . '" alt="QR Code" style="border: 1px solid #ccc; padding: 5px; background: white;">';
            $html .= '<div style="margin-top: 5px; font-family: monospace;">' . htmlspecialchars($model->getCode()) . '</div>';
            $html .= '</div>';
        }

        if ($helper->isBarcodeEnabled()) {
            $barcodeUrl = $helper->getBarcodeDataUrl($model->getCode());
            $html .= '<div style="text-align: center;">';
            $html .= '<div style="margin-bottom: 5px;"><strong>Barcode (Code128)</strong></div>';
            $html .= '<img src="' . htmlspecialchars($barcodeUrl) . '" alt="Barcode" style="border: 1px solid #ccc; padding: 5px; background: white; max-width: 300px;">';
            $html .= '<div style="margin-top: 5px; font-family: monospace;">' . htmlspecialchars($model->getCode()) . '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
