<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
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

        $form   = new \Maho\Data\Form([
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

        // Add available template variables for the WYSIWYG editor
        $variables = $this->getNewsletterVariables();

        // Add both 'variables' and 'template_variables' for compatibility
        $fieldset->addField('variables', 'hidden', [
            'name' => 'variables',
            'value' => Mage::helper('core')->jsonEncode($variables),
        ]);

        $fieldset->addField('template_variables', 'hidden', [
            'name' => 'template_variables',
            'value' => Mage::helper('core')->jsonEncode($variables),
        ]);

        $widgetFilters = ['is_email_compatible' => 1];
        $wysiwygConfig = Mage::getSingleton('cms/wysiwyg_config')->getConfig([
            'widget_filters' => $widgetFilters,
            'add_variables' => true,
            'add_slideshows' => false,
            'variable_window_url' => Mage::getSingleton('adminhtml/url')->getUrl('*/newsletter_template/wysiwygVariable'),
        ]);
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

    /**
     * Get available newsletter template variables
     */
    public function getNewsletterVariables(): array
    {
        $variables = [];

        // Store variables
        $variables[] = Mage::getModel('core/source_email_variables')->toOptionArray(true);

        // Newsletter-specific variables
        $newsletterVars = [
            [
                'value' => '{{var subscriber.getUnsubscriptionLink()}}',
                'label' => Mage::helper('newsletter')->__('Unsubscribe Link'),
            ],
            [
                'value' => '{{var subscriber.email}}',
                'label' => Mage::helper('newsletter')->__('Subscriber Email'),
            ],
        ];

        // Customer variables (for email automation)
        $customerVars = [
            [
                'value' => '{{var customer.name}}',
                'label' => Mage::helper('newsletter')->__('Customer Name (Object)'),
            ],
            [
                'value' => '{{var customer_name}}',
                'label' => Mage::helper('newsletter')->__('Customer Name'),
            ],
            [
                'value' => '{{var customer_firstname}}',
                'label' => Mage::helper('newsletter')->__('Customer First Name'),
            ],
            [
                'value' => '{{var customer_lastname}}',
                'label' => Mage::helper('newsletter')->__('Customer Last Name'),
            ],
            [
                'value' => '{{var customer_email}}',
                'label' => Mage::helper('newsletter')->__('Customer Email'),
            ],
        ];

        // Segment & automation variables
        $automationVars = [
            [
                'value' => '{{var segment_name}}',
                'label' => Mage::helper('newsletter')->__('Segment Name'),
            ],
            [
                'value' => '{{var step_number}}',
                'label' => Mage::helper('newsletter')->__('Sequence Step Number'),
            ],
            [
                'value' => '{{var coupon_code}}',
                'label' => Mage::helper('newsletter')->__('Coupon Code'),
            ],
            [
                'value' => '{{var coupon_discount_amount}}',
                'label' => Mage::helper('newsletter')->__('Coupon Discount Amount (Raw Number)'),
            ],
            [
                'value' => '{{var coupon_discount_text}}',
                'label' => Mage::helper('newsletter')->__('Coupon Discount (Formatted Text)'),
            ],
            [
                'value' => '{{var coupon_description}}',
                'label' => Mage::helper('newsletter')->__('Coupon Rule Description'),
            ],
            [
                'value' => '{{var coupon_expires_date}}',
                'label' => Mage::helper('newsletter')->__('Coupon Expiration Date (Raw)'),
            ],
            [
                'value' => '{{var coupon_expires_formatted}}',
                'label' => Mage::helper('newsletter')->__('Coupon Expiration Date (Formatted)'),
            ],
        ];

        $variables[] = [
            'label' => Mage::helper('newsletter')->__('Newsletter Variables'),
            'value' => $newsletterVars,
        ];

        $variables[] = [
            'label' => Mage::helper('newsletter')->__('Customer Variables'),
            'value' => $customerVars,
        ];

        $variables[] = [
            'label' => Mage::helper('newsletter')->__('Email Automation Variables'),
            'value' => $automationVars,
        ];

        return $variables;
    }
}
