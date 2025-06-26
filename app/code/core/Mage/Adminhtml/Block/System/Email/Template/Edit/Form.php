<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Email_Template_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Add files to use dialog windows
     */
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        /** @var Mage_Page_Block_Html_Head $head */
        $head = $this->getLayout()->getBlock('head');
        if ($head && Mage::getSingleton('cms/wysiwyg_config')->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadWysiwyg(true);
        }
        return $this;
    }

    /**
     * Add fields to form and create template info form
     */
    #[\Override]
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('adminhtml')->__('Template Information'),
            'class' => 'fieldset-wide',
        ]);

        $templateId = $this->getEmailTemplate()->getId();
        if ($templateId) {
            $fieldset->addField('used_currently_for', 'label', [
                'label' => Mage::helper('adminhtml')->__('Used Currently For'),
                'container_id' => 'used_currently_for',
                'after_element_html' =>
                    '<script type="text/javascript">' .
                    (!$this->getEmailTemplate()->getSystemConfigPathsWhereUsedCurrently()
                        ? '$(\'' . 'used_currently_for' . '\').hide(); ' : '') .
                    '</script>',
            ]);
        }

        if (!$templateId) {
            $fieldset->addField('used_default_for', 'label', [
                'label' => Mage::helper('adminhtml')->__('Used as Default For'),
                'container_id' => 'used_default_for',
                'after_element_html' =>
                    '<script type="text/javascript">' .
                    (!(bool) $this->getEmailTemplate()->getOrigTemplateCode()
                        ? '$(\'' . 'used_default_for' . '\').hide(); ' : '') .
                    '</script>',
            ]);
        }

        $fieldset->addField('template_code', 'text', [
            'name' => 'template_code',
            'label' => Mage::helper('adminhtml')->__('Template Name'),
            'required' => true,

        ]);

        $fieldset->addField('template_subject', 'text', [
            'name' => 'template_subject',
            'label' => Mage::helper('adminhtml')->__('Template Subject'),
            'required' => true,
        ]);

        $fieldset->addField('orig_template_variables', 'hidden', [
            'name' => 'orig_template_variables',
        ]);

        $fieldset->addField('variables', 'hidden', [
            'name' => 'variables',
            'value' => Zend_Json::encode($this->getVariables()),
        ]);

        $fieldset->addField('template_variables', 'hidden', [
            'name' => 'template_variables',
        ]);

        $widgetFilters = ['is_email_compatible' => 1];
        $wysiwygConfig = Mage::getSingleton('cms/wysiwyg_config')
            ->getConfig(['widget_filters' => $widgetFilters]);

        $fieldset->addField('template_text', 'editor', [
            'name'      => 'template_text',
            'label'     => Mage::helper('adminhtml')->__('Template Content'),
            'title'     => Mage::helper('adminhtml')->__('Template Content'),
            'state'     => 'html', // TODO?
            'required'  => true,
            'style'     => 'height:24em;',
            'config'    => $wysiwygConfig,
        ]);

        if (!$this->getEmailTemplate()->isPlain()) {
            $fieldset->addField('template_styles', 'textarea', [
                'name' => 'template_styles',
                'label' => Mage::helper('adminhtml')->__('Template Styles'),
            ]);
        }

        if ($templateId) {
            $form->addValues($this->getEmailTemplate()->getData());
        }

        if ($values = Mage::getSingleton('adminhtml/session')->getData('email_template_form_data', true)) {
            $form->setValues($values);
        }

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Return current email template model
     *
     * @return Mage_Core_Model_Email_Template
     */
    public function getEmailTemplate()
    {
        return Mage::registry('current_email_template');
    }

    /**
     * Retrieve variables to insert into email
     *
     * @return array
     */
    public function getVariables()
    {
        $variables = [];
        $variables[] = Mage::getModel('core/source_email_variables')
            ->toOptionArray(true);
        $customVariables = Mage::getModel('core/variable')
            ->getVariablesOptionArray(true);
        if ($customVariables) {
            $variables[] = $customVariables;
        }
        /** @var Mage_Core_Model_Email_Template $template */
        $template = Mage::registry('current_email_template');
        if ($template->getId() && $templateVariables = $template->getVariablesOptionArray(true)) {
            $variables[] = $templateVariables;
        }
        return $variables;
    }
}
