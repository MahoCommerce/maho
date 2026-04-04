<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Contacts
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Contacts\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ContactFormProcessor extends \Maho\ApiPlatform\Processor
{
    private const CONFIG_ENABLED = 'apiplatform/contact/enabled';
    private const CONFIG_CAPTCHA_PROVIDER = 'apiplatform/contact/captcha_provider';
    private const CONFIG_CAPTCHA_SECRET = 'apiplatform/contact/captcha_secret_key';
    private const CONFIG_HONEYPOT = 'apiplatform/contact/honeypot_enabled';
    private const CONFIG_RATE_LIMIT = 'apiplatform/contact/rate_limit';

    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const EMAIL_TEMPLATE = 'contacts/email/email_template';
    private const EMAIL_SENDER = 'contacts/email/sender_email_identity';
    private const EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    private const AUTO_REPLY_ENABLED = 'contacts/auto_reply/enabled';
    private const AUTO_REPLY_TEMPLATE = 'contacts/auto_reply/email_template';

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ContactForm
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        $request = $context['request'] ?? null;
        $body = $request ? (json_decode($request->getContent(), true) ?? []) : [];

        if (!\Mage::getStoreConfigFlag(self::CONFIG_ENABLED, $storeId)) {
            throw new NotFoundHttpException('Contact form is not available');
        }

        // Silently accept honeypot submissions to not reveal the trap
        if ($this->isHoneypotTriggered($body, $storeId)) {
            return $this->successResponse();
        }

        $name = $this->sanitize($body['name'] ?? '', 255);
        $email = $this->sanitize($body['email'] ?? '', 255);
        $message = $this->sanitize($body['comment'] ?? $body['message'] ?? '', 5000);
        $phone = $this->sanitize($body['telephone'] ?? $body['phone'] ?? '', 50);

        if (!\Mage::helper('core')->isValidNotBlank($name)) {
            throw new UnprocessableEntityHttpException('Name is required');
        }
        if (!\Mage::helper('core')->isValidEmail($email)) {
            throw new UnprocessableEntityHttpException('A valid email address is required');
        }
        if (!\Mage::helper('core')->isValidNotBlank($message)) {
            throw new UnprocessableEntityHttpException('Message is required');
        }

        $this->verifyCaptcha($body, $storeId, $request);
        $this->checkRateLimit($email, $storeId);

        try {
            $this->sendEmail($name, $email, $message, $phone, $storeId);
            $this->sendAutoReply($name, $email, $message, $phone, $storeId);
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'Unable to send your message. Please try again later.');
        }

        return $this->successResponse();
    }

    private function isHoneypotTriggered(array $body, int $storeId): bool
    {
        if (!\Mage::getStoreConfigFlag(self::CONFIG_HONEYPOT, $storeId)) {
            return false;
        }
        $honeypot = $body['company'] ?? $body['website'] ?? null;
        return $honeypot !== null && $honeypot !== '';
    }

    private function sanitize(string $value, int $maxLength): string
    {
        return strip_tags(mb_substr(trim($value), 0, $maxLength));
    }

    private function verifyCaptcha(array $body, int $storeId, mixed $request): void
    {
        $provider = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_PROVIDER, $storeId) ?: 'none';
        if ($provider === 'none') {
            return;
        }

        $token = $body['captchaToken'] ?? '';
        if (empty($token)) {
            throw new HttpException(422, 'CAPTCHA verification is required');
        }

        $secret = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_SECRET, $storeId);
        if (empty($secret)) {
            \Mage::log('Contact form: CAPTCHA secret key not configured', \Mage::LOG_WARNING);
            return;
        }

        $verifyUrl = match ($provider) {
            'turnstile' => self::TURNSTILE_VERIFY_URL,
            'recaptcha_v3' => self::RECAPTCHA_VERIFY_URL,
            default => null,
        };

        if ($verifyUrl === null) {
            return;
        }

        try {
            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 5]);
            $response = $client->request('POST', $verifyUrl, [
                'body' => [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request?->getClientIp(),
                ],
            ]);

            $result = $response->toArray(false);

            if (empty($result['success'])) {
                throw new HttpException(422, 'CAPTCHA verification failed. Please try again.');
            }

            if ($provider === 'recaptcha_v3' && isset($result['score']) && $result['score'] < 0.5) {
                throw new HttpException(422, 'CAPTCHA verification failed. Please try again.');
            }
        } catch (HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Mage::logException($e);
        }
    }

    private function checkRateLimit(#[\SensitiveParameter]
        string $email, int $storeId): void
    {
        $limit = (int) \Mage::getStoreConfig(self::CONFIG_RATE_LIMIT, $storeId);
        if ($limit > 0 && \Mage::helper('core')->isRateLimitExceeded(false, true, "contact:{$storeId}:" . strtolower($email), $limit, 3600)) {
            throw new TooManyRequestsHttpException('3600', 'Too many submissions. Please try again later.');
        }
    }

    private function sendEmail(string $name, #[\SensitiveParameter]
        string $email, string $message, string $phone, int $storeId): void
    {
        $postObject = new \Maho\DataObject();
        $postObject->setData([
            'name' => $name,
            'email' => $email,
            'comment' => $message,
            'telephone' => $phone,
        ]);

        $mailTemplate = \Mage::getModel('core/email_template');
        $mailTemplate->setDesignConfig(['area' => \Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->setReplyTo($email)
            ->sendTransactional(
                \Mage::getStoreConfig(self::EMAIL_TEMPLATE, $storeId),
                \Mage::getStoreConfig(self::EMAIL_SENDER, $storeId),
                \Mage::getStoreConfig(self::EMAIL_RECIPIENT, $storeId),
                null,
                ['data' => $postObject],
            );

        if (!$mailTemplate->getSentSuccess()) {
            throw new \RuntimeException('Email send failed');
        }
    }

    private function sendAutoReply(string $name, #[\SensitiveParameter]
        string $email, string $message, string $phone, int $storeId): void
    {
        if (!\Mage::getStoreConfigFlag(self::AUTO_REPLY_ENABLED, $storeId)) {
            return;
        }

        $postObject = new \Maho\DataObject();
        $postObject->setData([
            'name' => $name,
            'email' => $email,
            'comment' => $message,
            'telephone' => $phone,
        ]);

        $autoReply = \Mage::getModel('core/email_template');
        $autoReply->setDesignConfig(['area' => \Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->setReplyTo(\Mage::getStoreConfig(self::EMAIL_RECIPIENT, $storeId))
            ->sendTransactional(
                \Mage::getStoreConfig(self::AUTO_REPLY_TEMPLATE, $storeId),
                \Mage::getStoreConfig(self::EMAIL_SENDER, $storeId),
                $email,
                $name,
                ['data' => $postObject],
            );
    }

    private function successResponse(): ContactForm
    {
        $dto = new ContactForm();
        $dto->success = true;
        $dto->message = 'Your message has been sent. We will get back to you soon.';
        return $dto;
    }
}
