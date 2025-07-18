<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Newsletter_Template_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Retrieve template object
     *
     * @return Mage_Newsletter_Model_Template
     */
    public function getModel()
    {
        return Mage::registry('_current_template');
    }

    /**
     * Prepare form before rendering HTML
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        $model  = $this->getModel();
        $identity = Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_UNSUBSCRIBE_EMAIL_IDENTITY);
        $identityName = Mage::getStoreConfig('trans_email/ident_' . $identity . '/name');
        $identityEmail = Mage::getStoreConfig('trans_email/ident_' . $identity . '/email');

        $form   = new Varien_Data_Form([
            'id'        => 'edit_form',
            'action'    => $this->getData('action'),
            'method'    => 'post',
        ]);

        $fieldset   = $form->addFieldset('base_fieldset', [
            'legend'    => Mage::helper('newsletter')->__('Template Information'),
            'class'     => 'fieldset-wide',
        ]);

        if ($model->getId()) {
            $fieldset->addField('id', 'hidden', [
                'name'      => 'id',
                'value'     => $model->getId(),
            ]);
        }

        $fieldset->addField('code', 'text', [
            'name'      => 'code',
            'label'     => Mage::helper('newsletter')->__('Template Name'),
            'title'     => Mage::helper('newsletter')->__('Template Name'),
            'required'  => true,
            'value'     => $model->getTemplateCode(),
        ]);

        $fieldset->addField('subject', 'text', [
            'name'      => 'subject',
            'label'     => Mage::helper('newsletter')->__('Template Subject'),
            'title'     => Mage::helper('newsletter')->__('Template Subject'),
            'required'  => true,
            'value'     => $model->getTemplateSubject(),
        ]);

        $fieldset->addField('sender_name', 'text', [
            'name'      => 'sender_name',
            'label'     => Mage::helper('newsletter')->__('Sender Name'),
            'title'     => Mage::helper('newsletter')->__('Sender Name'),
            'required'  => true,
            'value'     => $model->getId() !== null
                ? $model->getTemplateSenderName()
                : $identityName,
        ]);

        $fieldset->addField('sender_email', 'text', [
            'name'      => 'sender_email',
            'label'     => Mage::helper('newsletter')->__('Sender Email'),
            'title'     => Mage::helper('newsletter')->__('Sender Email'),
            'class'     => 'validate-email',
            'required'  => true,
            'value'     => $model->getId() !== null
                ? $model->getTemplateSenderEmail()
                : $identityEmail,
        ]);

        $widgetFilters = ['is_email_compatible' => 1];
        $wysiwygConfig = Mage::getSingleton('cms/wysiwyg_config')->getConfig(['widget_filters' => $widgetFilters]);
        if ($model->isPlain()) {
            $wysiwygConfig->setEnabled(false);
        }
        $fieldset->addField('text', 'editor', [
            'name'      => 'text',
            'label'     => Mage::helper('newsletter')->__('Template Content'),
            'title'     => Mage::helper('newsletter')->__('Template Content'),
            'required'  => true,
            'state'     => 'html',
            'style'     => 'height:36em;',
            'value'     => $model->getTemplateText(),
            'config'    => $wysiwygConfig,
        ]);

        if (!$model->isPlain()) {
            $fieldset->addField('template_styles', 'textarea', [
                'name'          => 'styles',
                'label'         => Mage::helper('newsletter')->__('Template Styles'),
                'value'         => $model->getTemplateStyles(),
            ]);
        }

        $form->setAction($this->getUrl('*/*/save'));
        $form->setUseContainer(true);
        $this->setForm($form);

        return parent::_prepareForm();
    }
}
