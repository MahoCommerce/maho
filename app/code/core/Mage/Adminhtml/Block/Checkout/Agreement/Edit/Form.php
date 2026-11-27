<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Checkout_Agreement_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Init class
     */
    public function __construct()
    {
        parent::__construct();

        $this->setId('checkoutAgreementForm');
        $this->setTitle(Mage::helper('checkout')->__('Terms and Conditions Information'));
    }

    /**
     * return Mage_Adminhtml_Block_Widget_Form
     */
    #[\Override]
    protected function _prepareForm()
    {
        $model  = Mage::registry('checkout_agreement');
        $form   = new \Maho\Data\Form([
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
        ]);

        $fieldset   = $form->addFieldset('base_fieldset', [
            'legend'    => Mage::helper('checkout')->__('Terms and Conditions Information'),
            'class'     => 'fieldset-wide',
        ]);

        if ($model->getId()) {
            $fieldset->addField('agreement_id', 'hidden', [
                'name' => 'agreement_id',
            ]);
        }
        $fieldset->addField('name', 'text', [
            'name'      => 'name',
            'label'     => Mage::helper('checkout')->__('Condition Name'),
            'title'     => Mage::helper('checkout')->__('Condition Name'),
            'required'  => true,
        ]);

        $fieldset->addField('is_active', 'select', [
            'label'     => Mage::helper('checkout')->__('Status'),
            'title'     => Mage::helper('checkout')->__('Status'),
            'name'      => 'is_active',
            'required'  => true,
            'options'   => [
                '1' => Mage::helper('checkout')->__('Enabled'),
                '0' => Mage::helper('checkout')->__('Disabled'),
            ],
        ]);

        $fieldset->addField('is_html', 'select', [
            'label'     => Mage::helper('checkout')->__('Show Content as'),
            'title'     => Mage::helper('checkout')->__('Show Content as'),
            'name'      => 'is_html',
            'required'  => true,
            'options'   => [
                0 => Mage::helper('checkout')->__('Text'),
                1 => Mage::helper('checkout')->__('HTML'),
            ],
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $field = $fieldset->addField('store_id', 'multiselect', [
                'name'      => 'stores[]',
                'label'     => Mage::helper('checkout')->__('Store View'),
                'title'     => Mage::helper('checkout')->__('Store View'),
                'required'  => true,
                'values'    => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
            ]);
            $renderer = $this->getStoreSwitcherRenderer();
            $field->setRenderer($renderer);
        } else {
            $fieldset->addField('store_id', 'hidden', [
                'name'      => 'stores[]',
                'value'     => Mage::app()->getStore(true)->getId(),
            ]);
            $model->setStoreId(Mage::app()->getStore(true)->getId());
        }

        $fieldset->addField('checkbox_text', 'editor', [
            'name'      => 'checkbox_text',
            'label'     => Mage::helper('checkout')->__('Checkbox Text'),
            'title'     => Mage::helper('checkout')->__('Checkbox Text'),
            'rows'      => 5,
            'cols'      => 30,
            'wysiwyg'   => false,
            'required'  => true,
            'after_element_html' => $this->getSanitizePreviewHtml('checkbox_text', $model),
        ]);

        $fieldset->addField('content', 'editor', [
            'name'      => 'content',
            'label'     => Mage::helper('checkout')->__('Content'),
            'title'     => Mage::helper('checkout')->__('Content'),
            'style'     => 'height:24em;',
            'wysiwyg'   => false,
            'required'  => false,
            'after_element_html' => $this->getSanitizePreviewHtml('content', $model),
        ]);

        $fieldset->addField('content_height', 'text', [
            'name'      => 'content_height',
            'label'     => Mage::helper('checkout')->__('Content Height (css)'),
            'title'     => Mage::helper('checkout')->__('Content Height'),
            'maxlength' => 25,
            'class'     => 'validate-css-length',
        ]);

        $fieldset->addField('position', 'text', [
            'label'    => Mage::helper('checkout')->__('Position'),
            'title'    => Mage::helper('checkout')->__('Position'),
            'name'     => 'position',
            'value'    => '0',
            'required' => true,
            'class'    => 'validate-digits',
        ]);

        $placement = $form->addFieldset('placement_fieldset', [
            'legend'    => Mage::helper('checkout')->__('Placement and Consent'),
            'class'     => 'fieldset-wide',
        ]);
        $yesNo = Mage::getSingleton('adminhtml/system_config_source_yesno')->toArray();
        $placement->addField('show_on_checkout', 'select', [
            'label'     => Mage::helper('checkout')->__('Show in the Checkout'),
            'name'      => 'show_on_checkout',
            'options'   => $yesNo,
        ]);
        $placement->addField('show_on_registration', 'select', [
            'label'     => Mage::helper('checkout')->__('Show in the Registration Form'),
            'name'      => 'show_on_registration',
            'options'   => $yesNo,
            'note'      => Mage::helper('checkout')->__('An existing customer who did not accept these terms sees a request in the customer account.'),
        ]);
        $placement->addField('requires_reconsent', 'select', [
            'label'     => Mage::helper('checkout')->__('Ask Again When the Text Changes'),
            'name'      => 'requires_reconsent',
            'options'   => $yesNo,
            'note'      => Mage::helper('checkout')->__('A change of the name, the checkbox text or the content makes a new version. With Yes, customers who accepted an older version must accept the new one.'),
        ]);
        if ($model->getId()) {
            $placement->addField('versions', 'note', [
                'label'     => Mage::helper('checkout')->__('Versions'),
                'text'      => $this->getVersionsHtml((int) $model->getId()),
            ]);
        }

        if (!$model->getId()) {
            $model->setShowOnCheckout($model->getShowOnCheckout() ?? true);
        }
        $form->setValues($model->getData());
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * A table of the versions of the agreement with $agreementId, with the number of acceptances of each.
     */
    protected function getVersionsHtml(int $agreementId): string
    {
        $rows = Mage::getResourceSingleton('checkout/agreement')->getVersionsWithAcceptanceCount($agreementId);
        $helper = Mage::helper('checkout');
        $html = '<div class="grid np"><table cellspacing="0" class="data"><thead><tr><th>' . $helper->__('Version') . '</th><th>'
            . $helper->__('Created') . '</th><th>' . $helper->__('Acceptances') . '</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . (int) $row['version'] . '</td><td>'
                . $this->escapeHtml(Mage::helper('core')->formatDate($row['created_at'], Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, true)) . '</td><td>'
                . (int) $row['acceptances'] . '</td></tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /** The save only sanitizes an HTML agreement. A plain text agreement gets no warning. */
    protected function getSanitizePreviewHtml(string $field, \Maho\DataObject $model): string
    {
        return $model->getIsHtml() ? Mage::helper('cms')->getSanitizePreviewHtml($field) : '';
    }
}
