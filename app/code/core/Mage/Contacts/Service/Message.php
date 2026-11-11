<?php

/**
 * Check a contact message and email it to the store, for the storefront form and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

class Mage_Contacts_Service_Message
{
    public const XML_PATH_HONEYPOT_ENABLED = 'contacts/abuse/honeypot_enabled';
    public const XML_PATH_RATE_LIMIT = 'system/rate_limit/contact';
    public const XML_PATH_IP_RATE_LIMIT = 'system/rate_limit/contact_ip';
    public const XML_PATH_RECIPIENT_RATE_LIMIT = 'system/rate_limit/contact_auto_reply';
    public const XML_PATH_EMAIL_SENDER = 'contacts/email/sender_email_identity';
    public const XML_PATH_EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    public const XML_PATH_EMAIL_TEMPLATE = 'contacts/email/email_template';
    public const XML_PATH_AUTO_REPLY_ENABLED = 'contacts/auto_reply/enabled';
    public const XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE = 'contacts/auto_reply/email_template';

    /** The longest value of each field. The service cuts a longer value. A field of a custom form gets the length of comment. */
    public const MAX_LENGTHS = ['name' => 255, 'email' => 255, 'comment' => 5000, 'telephone' => 50];

    public const RATE_LIMIT_WINDOW = 3600;

    /** Fields for the transport, the abuse checks and the aliases of the API. The email does not show them. */
    private const CONTROL_FIELDS = ['form_key', 'captchaToken', 'maho_captcha', 'message', 'phone'];

    public function isHoneypotEnabled(?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_HONEYPOT_ENABLED, $storeId);
    }

    /**
     * Check the message in $fields and email it to the store. When the message fills the honeypot, send nothing
     * and throw nothing, so that a bot cannot detect the trap. The caller checks the switch of its own endpoint
     * first: Mage_Contacts_Helper_Data::isEnabled() for the storefront form, isApiEnabled() for the API.
     *
     * $fields has name, email, comment (or message) and telephone (or phone), and the token of the CAPTCHA.
     * A field of a custom form goes to the email template as data.<field>.
     *
     * @param array<array-key, mixed> $fields the fields that the client sent: the form POST or the JSON body
     * @throws Mage_Core_Exception_Input with one error for each invalid field, or when the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email address is over its limit
     * @throws RuntimeException when the email to the store fails
     */
    public function send(array $fields, int $storeId): void
    {
        if ($this->isHoneypotEnabled($storeId) && Mage::helper('core')->isHoneypotTriggered($fields)) {
            return;
        }

        $message = $this->readMessage($fields);
        $this->validate($message);
        Mage::helper('core')->assertCaptcha($fields);
        $this->attemptRateLimits($storeId, (string) $message->getData('email'));

        $this->sendToStore($message, $storeId);
        $this->sendAutoReply($message, $storeId);
    }

    /**
     * Send the transactional email $templateId to $to and return whether it was sent.
     * The template gets $message as the variable data.
     */
    protected function sendTemplate(
        string $templateId,
        #[\SensitiveParameter]
        string $to,
        ?string $toName,
        #[\SensitiveParameter]
        string $replyTo,
        \Maho\DataObject $message,
        int $storeId,
    ): bool {
        /** @var Mage_Core_Model_Email_Template $mailTemplate */
        $mailTemplate = Mage::getModel('core/email_template');
        $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->setReplyTo($replyTo)
            ->sendTransactional(
                $templateId,
                Mage::getStoreConfig(self::XML_PATH_EMAIL_SENDER, $storeId),
                $to,
                $toName,
                ['data' => $message],
            );
        return (bool) $mailTemplate->getSentSuccess();
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    private function readMessage(array $fields): \Maho\DataObject
    {
        $hidden = [...self::CONTROL_FIELDS, Mage::helper('core')->getHoneypotFieldName()];
        $data = [];
        foreach ($fields as $key => $value) {
            if (is_string($key) && is_scalar($value) && !in_array($key, $hidden, true)) {
                $data[$key] = self::clean((string) $value, self::MAX_LENGTHS['comment']);
            }
        }
        $data['name'] = self::clean(self::stringValue($fields['name'] ?? null), self::MAX_LENGTHS['name']);
        $data['email'] = self::clean(self::stringValue($fields['email'] ?? null), self::MAX_LENGTHS['email']);
        $data['comment'] = self::clean(self::stringValue($fields['comment'] ?? $fields['message'] ?? null), self::MAX_LENGTHS['comment']);
        $data['telephone'] = self::clean(self::stringValue($fields['telephone'] ?? $fields['phone'] ?? null), self::MAX_LENGTHS['telephone']);

        return new \Maho\DataObject($data);
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    private function validate(\Maho\DataObject $message): void
    {
        $helper = Mage::helper('contacts');
        $core = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        if (!$core->isValidNotBlank($message->getData('name'))) {
            $errors->addError('name', $helper->__('Name is required'));
        }
        // The email validator accepts an empty value
        if (!$core->isValidNotBlank($message->getData('email')) || !$core->isValidEmail($message->getData('email'))) {
            $errors->addError('email', $helper->__('A valid email address is required'));
        }
        if (!$core->isValidNotBlank($message->getData('comment'))) {
            $errors->addError('comment', $helper->__('Message is required'));
        }

        $errors->throwIfErrors();
    }

    /**
     * The limit of one client and the limit of one email address. The storefront form and the API count together.
     *
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function attemptRateLimits(int $storeId, #[\SensitiveParameter] string $email): void
    {
        $core = Mage::helper('core');
        $core->rateLimiter(
            "contact:{$storeId}",
            (int) Mage::getStoreConfig(self::XML_PATH_IP_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
            \Maho\Security\RateLimitScope::Ip,
        )->attemptOrThrow();
        $core->rateLimiterBy(
            'contact',
            $storeId . ':' . strtolower($email),
            (int) Mage::getStoreConfig(self::XML_PATH_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
        )->attemptOrThrow();
    }

    /**
     * @throws RuntimeException
     */
    private function sendToStore(\Maho\DataObject $message, int $storeId): void
    {
        $sent = $this->sendTemplate(
            (string) Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, $storeId),
            (string) Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT, $storeId),
            null,
            (string) $message->getData('email'),
            $message,
            $storeId,
        );
        if (!$sent) {
            throw new RuntimeException('The contact message could not be sent to the store.');
        }
    }

    /**
     * The client chooses the recipient of the auto-reply, so one address gets a limited number of them in one hour.
     */
    private function sendAutoReply(\Maho\DataObject $message, int $storeId): void
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_AUTO_REPLY_ENABLED, $storeId)) {
            return;
        }
        $email = (string) $message->getData('email');
        $limit = (int) Mage::getStoreConfig(self::XML_PATH_RECIPIENT_RATE_LIMIT, $storeId);
        if (!Mage::helper('core')->rateLimiterBy('contacts_recipient', strtolower($email), $limit, self::RATE_LIMIT_WINDOW)->attempt()) {
            return;
        }

        try {
            $this->sendTemplate(
                (string) Mage::getStoreConfig(self::XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE, $storeId),
                $email,
                (string) $message->getData('name'),
                (string) Mage::getStoreConfig(self::XML_PATH_EMAIL_RECIPIENT, $storeId),
                $message,
                $storeId,
            );
        } catch (Exception $e) {
            // The store has the message, so a failed auto-reply does not fail the submission
            Mage::logException($e);
        }
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function clean(string $value, int $maxLength): string
    {
        return strip_tags(mb_substr(trim($value), 0, $maxLength));
    }
}
