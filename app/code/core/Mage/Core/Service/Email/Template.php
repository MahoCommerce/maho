<?php

/**
 * Load, check, save and delete the transactional email templates of the database, for the admin page and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Email_Template
{
    /**
     * The fields that must not hold HTML tags, with their admin labels. Only the body of a template may hold HTML.
     */
    public const TAG_FREE_FIELDS = [
        'template_code' => 'Template Name',
        'template_subject' => 'Template Subject',
        'template_styles' => 'Template Styles',
        'template_sender_name' => 'Sender Name',
        'template_sender_email' => 'Sender Email',
        'orig_template_code' => 'Original Template',
        'orig_template_variables' => 'Template Variables',
    ];

    /**
     * Return an unsaved HTML template. A delete of the returned model also resets each config path that uses it.
     */
    public function newTemplate(): Mage_Adminhtml_Model_Email_Template
    {
        /** @var Mage_Adminhtml_Model_Email_Template $template */
        $template = Mage::getModel('adminhtml/email_template');
        return $template->setTemplateType(Mage_Core_Model_Template::TYPE_HTML);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Adminhtml_Model_Email_Template
    {
        /** @var Mage_Adminhtml_Model_Email_Template $template */
        $template = Mage::getModel('adminhtml/email_template');
        try {
            $template->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        if (!$template->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This email template no longer exists.'));
        }
        return $template;
    }

    /**
     * Check $template and save it. The transport puts its input into the template through the setters first.
     * The body keeps its template directives as they are, because only an admin user writes a template.
     *
     * @throws Mage_Core_Exception_Conflict when another template has the name
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    public function save(Mage_Adminhtml_Model_Email_Template $template): Mage_Adminhtml_Model_Email_Template
    {
        $helper = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        $code = trim((string) $template->getTemplateCode());
        $template->setTemplateCode($code);
        if ($code === '') {
            $errors->addError('template_code', $helper->__('The template Name must not be empty.'));
        } elseif ($template->getResource()->checkCodeUsage($template)) {
            throw new Mage_Core_Exception_Conflict($helper->__("An email template with code '%s' already exists", $code));
        }

        if (trim((string) $template->getTemplateText()) === '') {
            $errors->addError('template_text', $helper->__('The template text must not be empty.'));
        }
        if (trim((string) $template->getTemplateSubject()) === '') {
            $errors->addError('template_subject', $helper->__('The template subject must not be empty.'));
        }
        // Accept a stored type, so that an old template without one can still be saved
        $type = (int) $template->getData('template_type');
        if ($template->dataHasChangedFor('template_type')
            && !in_array($type, [Mage_Core_Model_Template::TYPE_TEXT, Mage_Core_Model_Template::TYPE_HTML], true)
        ) {
            $errors->addError('template_type', $helper->__('The template type must be text or html.'));
        }
        $senderEmail = (string) $template->getTemplateSenderEmail();
        if ($senderEmail !== '' && !$helper->isValidEmail($senderEmail)) {
            $errors->addError('template_sender_email', $helper->__('The sender email is not a valid email address.'));
        }
        foreach (self::TAG_FREE_FIELDS as $field => $label) {
            if ($helper->hasTags((string) $template->getData($field))) {
                $errors->addError($field, $helper->__('%s must not contain HTML tags.', $helper->__($label)));
            }
        }

        $errors->throwIfErrors();

        // A plain text template has no styles
        if ($type === Mage_Core_Model_Template::TYPE_TEXT) {
            $template->setData('template_styles', '');
        }
        $template->save();
        return $template;
    }

    /**
     * Delete $template. Each config path that uses it goes back to its default template.
     */
    public function delete(Mage_Adminhtml_Model_Email_Template $template): void
    {
        $template->delete();
    }
}
