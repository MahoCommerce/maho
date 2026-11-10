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
     * Save $template. The transport puts its input into the template through the setters first.
     * The model checks the fields and refuses a name that another template has.
     *
     * @throws Mage_Core_Exception_Conflict when another template has the name
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    public function save(Mage_Adminhtml_Model_Email_Template $template): Mage_Adminhtml_Model_Email_Template
    {
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

    /**
     * Send a plain text test email to $recipient through the mail transport of the store. The email does not go
     * through the queue, so that a fault of the transport shows at once.
     *
     * @throws Mage_Core_Exception_Input when $recipient is not a valid email address
     * @throws Mage_Core_Exception when the store has no mail transport, or the transport does not send the email
     */
    public function sendTest(
        #[\SensitiveParameter]
        string $recipient,
    ): void {
        $helper = Mage::helper('core');
        if (!$helper->isValidNotBlank($recipient) || !$helper->isValidEmail($recipient)) {
            throw new Mage_Core_Exception_Input()->addError('recipient', $helper->__('Invalid email address.'));
        }
        if ($helper->getMailerDsn() === '') {
            throw new Mage_Core_Exception($helper->__('Email transport is disabled. Please configure an email transport in Mail Sending Settings.'));
        }

        /** @var Mage_Core_Model_Email_Template $template */
        $template = Mage::getModel('core/email_template');
        $template->setSenderName(Mage::getStoreConfig('trans_email/ident_general/name'))
            ->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'))
            ->setTemplateType(Mage_Core_Model_Template::TYPE_TEXT)
            ->setTemplateText($helper->__('This is a test email from Maho.'))
            ->setTemplateSubject($helper->__('Maho Test Email'));
        if (!$this->sendTemplate($template, $recipient)) {
            throw new Mage_Core_Exception($helper->__('Failed to send test email. Please check your email configuration and error logs.'));
        }
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
