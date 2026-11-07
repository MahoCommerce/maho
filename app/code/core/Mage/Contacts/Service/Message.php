<?php

/**
 * Check a contact message and email it to the store, for the storefront form and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

use Mage_Contacts_Model_Channel as Channel;

class Mage_Contacts_Service_Message
{
    public const XML_PATH_ENABLED = 'contacts/contacts/enabled';
    public const XML_PATH_API_ENABLED = 'contacts/api/enabled';
    public const XML_PATH_HONEYPOT_ENABLED = 'contacts/abuse/honeypot_enabled';
    public const XML_PATH_API_HONEYPOT_ENABLED = 'contacts/api/honeypot_enabled';
    public const XML_PATH_IP_RATE_LIMIT = 'contacts/abuse/ip_rate_limit_per_hour';
    public const XML_PATH_RECIPIENT_RATE_LIMIT = 'contacts/abuse/recipient_rate_limit_per_hour';
    public const XML_PATH_EMAIL_RATE_LIMIT = 'system/rate_limit/contact';
    public const XML_PATH_API_CAPTCHA_PROVIDER = 'contacts/api/captcha_provider';
    public const XML_PATH_API_CAPTCHA_SITE_KEY = 'contacts/api/captcha_site_key';
    public const XML_PATH_API_CAPTCHA_SECRET = 'contacts/api/captcha_secret_key';
    public const XML_PATH_EMAIL_SENDER = 'contacts/email/sender_email_identity';
    public const XML_PATH_EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    public const XML_PATH_EMAIL_TEMPLATE = 'contacts/email/email_template';
    public const XML_PATH_AUTO_REPLY_ENABLED = 'contacts/auto_reply/enabled';
    public const XML_PATH_AUTO_REPLY_EMAIL_TEMPLATE = 'contacts/auto_reply/email_template';

    public const CAPTCHA_NONE = 'none';

    /** The longest value of each field. The service cuts a longer value. A field of a custom form gets the length of comment. */
    public const MAX_LENGTHS = ['name' => 255, 'email' => 255, 'comment' => 5000, 'telephone' => 50];

    public const RATE_LIMIT_WINDOW = 3600;

    private const CAPTCHA_VERIFY_URLS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    private const RECAPTCHA_MIN_SCORE = 0.5;

    /** Fields for the transport, the abuse checks and the aliases of the API. The email does not show them. */
    private const CONTROL_FIELDS = ['form_key', 'captchaToken', 'maho_captcha', 'message', 'phone'];

    public function isEnabled(Channel $channel, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(match ($channel) {
            Channel::Web => self::XML_PATH_ENABLED,
            Channel::Api => self::XML_PATH_API_ENABLED,
        }, $storeId);
    }

    public function isHoneypotEnabled(Channel $channel, ?int $storeId = null): bool
    {
        return Mage::getStoreConfigFlag(match ($channel) {
            Channel::Web => self::XML_PATH_HONEYPOT_ENABLED,
            Channel::Api => self::XML_PATH_API_HONEYPOT_ENABLED,
        }, $storeId);
    }

    /**
     * Return the CAPTCHA provider that send() checks: none, turnstile or recaptcha_v3.
     * The web channel has none, because Maho_Captcha checks the storefront form before the controller runs.
     */
    public function getCaptchaProvider(Channel $channel, ?int $storeId = null): string
    {
        if ($channel === Channel::Web) {
            return self::CAPTCHA_NONE;
        }
        return (string) Mage::getStoreConfig(self::XML_PATH_API_CAPTCHA_PROVIDER, $storeId) ?: self::CAPTCHA_NONE;
    }

    /**
     * Return the public key that the CAPTCHA widget of the client uses, or null when the channel has no CAPTCHA provider.
     */
    public function getCaptchaSiteKey(Channel $channel, ?int $storeId = null): ?string
    {
        if ($this->getCaptchaProvider($channel, $storeId) === self::CAPTCHA_NONE) {
            return null;
        }
        $siteKey = Mage::getStoreConfig(self::XML_PATH_API_CAPTCHA_SITE_KEY, $storeId);
        return $siteKey === null ? null : (string) $siteKey;
    }

    /**
     * Check the message in $fields and email it to the store. When the message fills the honeypot, send nothing
     * and throw nothing, so that a bot cannot detect the trap.
     *
     * $fields has name, email, comment (or message) and telephone (or phone), and the API sends captchaToken.
     * A field of a custom form goes to the email template as data.<field>.
     *
     * @param array<array-key, mixed> $fields the fields that the client sent: the form POST or the JSON body
     * @param string|null $clientIp the address of the client, for the CAPTCHA provider
     * @throws Mage_Core_Exception_NoSuchEntity when the channel is disabled
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_TooManyRequests when the client or the email address is over its limit
     * @throws RuntimeException when the CAPTCHA provider does not answer or the email to the store fails
     */
    public function send(array $fields, Channel $channel, int $storeId, ?string $clientIp = null): void
    {
        if (!$this->isEnabled($channel, $storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('contacts')->__('Contact form is not available'));
        }
        if ($this->isHoneypotEnabled($channel, $storeId) && Mage::helper('core')->isHoneypotTriggered($fields)) {
            return;
        }

        $message = $this->readMessage($fields);
        $captchaProvider = $this->getCaptchaProvider($channel, $storeId);
        $captchaToken = self::stringValue($fields['captchaToken'] ?? null);

        $this->validate($message, $captchaProvider, $captchaToken);
        $this->verifyCaptcha($captchaProvider, $captchaToken, $storeId, $clientIp);
        $this->checkRateLimits($channel, $storeId, (string) $message->getData('email'));

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
    private function validate(\Maho\DataObject $message, string $captchaProvider, string $captchaToken): void
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
        if ($captchaProvider !== self::CAPTCHA_NONE && $captchaToken === '') {
            $errors->addError('captcha_token', $helper->__('CAPTCHA verification is required'));
        }

        $errors->throwIfErrors();
    }

    /**
     * @throws Mage_Core_Exception_Input when the provider refuses the token
     * @throws RuntimeException when the provider does not answer
     */
    private function verifyCaptcha(
        string $provider,
        #[\SensitiveParameter]
        string $token,
        int $storeId,
        ?string $clientIp,
    ): void {
        if ($provider === self::CAPTCHA_NONE) {
            return;
        }
        $secret = (string) Mage::getStoreConfig(self::XML_PATH_API_CAPTCHA_SECRET, $storeId);
        if ($secret === '') {
            Mage::log('Contact form: CAPTCHA secret key not configured', Mage::LOG_WARNING);
            return;
        }
        $verifyUrl = self::CAPTCHA_VERIFY_URLS[$provider] ?? null;
        if ($verifyUrl === null) {
            return;
        }

        try {
            $result = \Maho\Http\Client::create(['timeout' => 5])->request('POST', $verifyUrl, [
                'body' => ['secret' => $secret, 'response' => $token, 'remoteip' => $clientIp],
            ])->toArray(false);
        } catch (Exception $e) {
            // Fail closed: a client that can stop the provider must not get past the CAPTCHA
            throw new RuntimeException('The CAPTCHA provider of the contact form did not answer.', 0, $e);
        }

        $passed = !empty($result['success'])
            && !($provider === 'recaptcha_v3' && isset($result['score']) && $result['score'] < self::RECAPTCHA_MIN_SCORE);
        if (!$passed) {
            throw new Mage_Core_Exception_Input()
                ->addError('captcha_token', Mage::helper('contacts')->__('CAPTCHA verification failed. Please try again.'));
        }
    }

    /**
     * Each channel keeps its own limit for one client. Both channels share the limit for one email address.
     *
     * @throws Mage_Core_Exception_TooManyRequests
     */
    private function checkRateLimits(Channel $channel, int $storeId, #[\SensitiveParameter] string $email): void
    {
        $core = Mage::helper('core');
        $emailLimit = (int) Mage::getStoreConfig(self::XML_PATH_EMAIL_RATE_LIMIT, $storeId);
        $clientLimiter = match ($channel) {
            Channel::Web => $core->rateLimiter(
                'contacts_ip',
                (int) Mage::getStoreConfig(self::XML_PATH_IP_RATE_LIMIT, $storeId),
                self::RATE_LIMIT_WINDOW,
                \Maho\Security\RateLimitScope::Ip,
            ),
            Channel::Api => $core->rateLimiter("contact:{$storeId}", $emailLimit, self::RATE_LIMIT_WINDOW, \Maho\Security\RateLimitScope::Ip),
        };
        $emailLimiter = $core->rateLimiterBy('contact', $storeId . ':' . strtolower($email), $emailLimit, self::RATE_LIMIT_WINDOW);

        if (!$clientLimiter->attempt() || !$emailLimiter->attempt()) {
            throw new Mage_Core_Exception_TooManyRequests(
                Mage::helper('contacts')->__('Too many requests. Please try again later.'),
                self::RATE_LIMIT_WINDOW,
            );
        }
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
