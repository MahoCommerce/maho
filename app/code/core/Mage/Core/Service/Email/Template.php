<?php

/**
 * Load, save and delete the transactional email templates of the database, for the admin page and the API, and
 * send the test email of the admin and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Email_Template
{
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
        $template = Mage::getModel('adminhtml/email_template')->load($id);
        if (!$template->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This email template no longer exists.'));
        }
        return $template;
    }

    /**
     * Save $template. The transport puts its input into the template through the setters first.
     * The name, the subject and the text must be set, and only the text may hold HTML tags. The text keeps its
     * template directives as they are, because only an admin user writes a template. A plain text template has
     * no styles. The model refuses a name that another template has.
     *
     * @throws Mage_Core_Exception_Conflict when another template has the name
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    public function save(Mage_Adminhtml_Model_Email_Template $template): Mage_Adminhtml_Model_Email_Template
    {
        $helper = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        // A name in use is a conflict whatever the other fields hold, so it comes before the field errors
        $template->assertCodeNotInUse();
        if (trim((string) $template->getTemplateCode()) === '') {
            $errors->addError('template_code', $helper->__('The template Name must not be empty.'));
        }
        if (trim((string) $template->getTemplateText()) === '') {
            $errors->addError('template_text', $helper->__('The template text must not be empty.'));
        }
        if (trim((string) $template->getTemplateSubject()) === '') {
            $errors->addError('template_subject', $helper->__('The template subject must not be empty.'));
        }
        // Accept a stored type, so that an old template without one can still be saved
        $type = (int) $template->getData('template_type');
        if ((!$template->getId() || $template->dataHasChangedFor('template_type'))
            && !in_array($type, [Mage_Core_Model_Template::TYPE_TEXT, Mage_Core_Model_Template::TYPE_HTML], true)
        ) {
            $errors->addError('template_type', $helper->__('The template type must be text or html.'));
        }
        $senderEmail = (string) $template->getTemplateSenderEmail();
        if ($senderEmail !== '' && !$helper->isValidEmail($senderEmail)) {
            $errors->addError('template_sender_email', $helper->__('The sender email is not a valid email address.'));
        }
        foreach ($this->getTagFreeFields() as $field => $label) {
            if ($helper->hasTags((string) $template->getData($field))) {
                $errors->addError($field, $helper->__('%s must not contain HTML tags.', $label));
            }
        }
        $errors->throwIfErrors();

        if ($type === Mage_Core_Model_Template::TYPE_TEXT) {
            $template->setData('template_styles', '');
        }
        $template->save();
        return $template;
    }

    /**
     * Return the fields that must not hold HTML tags, with their admin labels. Only the text of a template may hold HTML.
     *
     * @return array<string, string>
     */
    protected function getTagFreeFields(): array
    {
        $helper = Mage::helper('core');
        return [
            'template_code' => $helper->__('Template Name'),
            'template_subject' => $helper->__('Template Subject'),
            'template_styles' => $helper->__('Template Styles'),
            'template_sender_name' => $helper->__('Sender Name'),
            'template_sender_email' => $helper->__('Sender Email'),
            'orig_template_code' => $helper->__('Original Template'),
            'orig_template_variables' => $helper->__('Template Variables'),
        ];
    }

    /**
     * Delete $template. Each config path that uses it goes back to its default template.
     */
    public function delete(Mage_Adminhtml_Model_Email_Template $template): void
    {
        $template->delete();
    }

    /**
     * Send a plain text test email to $recipient through the mail transport of the store. The email does not go
     * through the queue, so that a fault of the transport shows at once.
     *
     * @throws Mage_Core_Exception_Input when $recipient is not a valid email address
     * @throws Mage_Core_Exception when the store has no mail transport
     * @throws RuntimeException when the transport does not send the email
     */
    public function sendTest(
        #[\SensitiveParameter]
        string $recipient,
    ): void {
        $helper = Mage::helper('core');
        if (!$this->sendTemplate($this->newTestTemplate($recipient), $recipient)) {
            throw new RuntimeException($helper->__('Failed to send test email. Please check your email configuration and error logs.'));
        }
    }

    /**
     * Put the test email of sendTest() for $recipient in the email queue, with the same checks. A queue worker sends it.
     *
     * @throws Mage_Core_Exception_Input when $recipient is not a valid email address
     * @throws Mage_Core_Exception when the store has no mail transport
     * @throws RuntimeException when the queue does not take the email
     */
    public function queueTest(
        #[\SensitiveParameter]
        string $recipient,
    ): void {
        if (!$this->queueTemplate($this->newTestTemplate($recipient), $recipient)) {
            throw new RuntimeException(Mage::helper('core')->__('Could not add the test email to the queue. Please check the error logs.'));
        }
    }

    /**
     * @throws Mage_Core_Exception_Input when $recipient is not a valid email address
     * @throws Mage_Core_Exception when the store has no mail transport
     */
    private function newTestTemplate(
        #[\SensitiveParameter]
        string $recipient,
    ): Mage_Core_Model_Email_Template {
        $helper = Mage::helper('core');
        if (!$helper->isValidNotBlank($recipient) || !$helper->isValidEmail($recipient)) {
            throw new Mage_Core_Exception_Input()->addError('recipient', $helper->__('Invalid email address.'));
        }
        if ($helper->getMailerDsn() === '') {
            throw new Mage_Core_Exception($helper->__('Email transport is disabled. Please configure an email transport in Mail Sending Settings.'));
        }

        /** @var Mage_Core_Model_Email_Template $template */
        $template = Mage::getModel('core/email_template');
        return $template->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'))
            ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
            ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText($helper->__('This is a test email from Maho.'))
            ->setTemplateSubject($helper->__('Maho Test Email'));
    }

    /**
     * Give $template for $recipient to the email queue, and return whether the queue took it.
     */
    protected function queueTemplate(
        Mage_Core_Model_Email_Template $template,
        #[\SensitiveParameter]
        string $recipient,
    ): bool {
        return $template->setQueue(Mage::getModel('core/email_queue'))->send($recipient);
    }

    /**
     * Send $template to $recipient now, without the queue, and return whether the transport sent it.
     */
    protected function sendTemplate(
        Mage_Core_Model_Email_Template $template,
        #[\SensitiveParameter]
        string $recipient,
    ): bool {
        // In developer mode, send() calls the mail transport itself and does not queue the email
        $developerMode = Mage::getIsDeveloperMode();
        Mage::setIsDeveloperMode(true);
        try {
            return $template->send($recipient);
        } finally {
            Mage::setIsDeveloperMode($developerMode);
        }
    }
}
