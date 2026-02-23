<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Contact Controller
 * Handles contact form submissions with CAPTCHA verification, honeypot, and rate limiting
 */
class ContactController extends AbstractController
{
    private const CONFIG_ENABLED = 'maho_apiplatform/contact/enabled';
    private const CONFIG_CAPTCHA_PROVIDER = 'maho_apiplatform/contact/captcha_provider';
    private const CONFIG_CAPTCHA_SECRET = 'maho_apiplatform/contact/captcha_secret_key';
    private const CONFIG_HONEYPOT = 'maho_apiplatform/contact/honeypot_enabled';
    private const CONFIG_RATE_LIMIT = 'maho_apiplatform/contact/rate_limit';

    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    private const RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const EMAIL_TEMPLATE = 'contacts/email/email_template';
    private const EMAIL_SENDER = 'contacts/email/sender_email_identity';
    private const EMAIL_RECIPIENT = 'contacts/email/recipient_email';
    private const AUTO_REPLY_ENABLED = 'contacts/auto_reply/enabled';
    private const AUTO_REPLY_TEMPLATE = 'contacts/auto_reply/email_template';

    #[Route('/api/contact', name: 'api_contact_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        // Check if contact form is enabled
        if (!\Mage::getStoreConfigFlag(self::CONFIG_ENABLED, $storeId)) {
            return new JsonResponse([
                'error' => 'disabled',
                'message' => 'Contact form is not available',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Honeypot check — if the hidden field has a value, it's a bot
        if (\Mage::getStoreConfigFlag(self::CONFIG_HONEYPOT, $storeId)) {
            $honeypot = $data['company'] ?? $data['website'] ?? null;
            if ($honeypot !== null && $honeypot !== '') {
                // Silently accept to not reveal the trap
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Your message has been sent. We will get back to you soon.',
                ]);
            }
        }

        // Validate required fields
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $message = trim((string) ($data['comment'] ?? $data['message'] ?? ''));
        $phone = trim((string) ($data['telephone'] ?? $data['phone'] ?? ''));

        if (!\Mage::helper('core')->isValidNotBlank($name)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Name is required',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!\Mage::helper('core')->isValidEmail($email)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'A valid email address is required',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (!\Mage::helper('core')->isValidNotBlank($message)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Message is required',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Sanitize inputs
        $name = strip_tags(mb_substr($name, 0, 255));
        $email = strip_tags(mb_substr($email, 0, 255));
        $message = strip_tags(mb_substr($message, 0, 5000));
        $phone = strip_tags(mb_substr($phone, 0, 50));

        // CAPTCHA verification
        $captchaError = $this->verifyCaptcha($data, $storeId, $request);
        if ($captchaError !== null) {
            return $captchaError;
        }

        // Rate limiting
        $rateLimitError = $this->checkRateLimit($email, $storeId);
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        // Send email
        try {
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
                \Mage::log('Contact form: email send failed', \Mage::LOG_ERROR);
                return new JsonResponse([
                    'error' => 'server_error',
                    'message' => 'Unable to send your message. Please try again later.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Auto-reply
            if (\Mage::getStoreConfigFlag(self::AUTO_REPLY_ENABLED, $storeId)) {
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

            // Record for rate limiting
            $this->recordSubmission($email, $storeId);

            return new JsonResponse([
                'success' => true,
                'message' => 'Your message has been sent. We will get back to you soon.',
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'Unable to send your message. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get contact form configuration (site key for CAPTCHA widget, honeypot field name)
     */
    #[Route('/api/contact/config', name: 'api_contact_config', methods: ['GET'])]
    public function config(): JsonResponse
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        $provider = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_PROVIDER, $storeId) ?: 'none';
        $siteKey = null;

        if ($provider !== 'none') {
            $siteKey = \Mage::getStoreConfig('maho_apiplatform/contact/captcha_site_key', $storeId);
        }

        return new JsonResponse([
            'enabled' => (bool) \Mage::getStoreConfigFlag(self::CONFIG_ENABLED, $storeId),
            'captchaProvider' => $provider,
            'captchaSiteKey' => $siteKey,
            'honeypotField' => \Mage::getStoreConfigFlag(self::CONFIG_HONEYPOT, $storeId) ? 'company' : null,
        ]);
    }

    private function verifyCaptcha(array $data, int $storeId, Request $request): ?JsonResponse
    {
        $provider = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_PROVIDER, $storeId) ?: 'none';

        if ($provider === 'none') {
            return null;
        }

        $token = $data['captchaToken'] ?? '';
        if (empty($token)) {
            return new JsonResponse([
                'error' => 'captcha_required',
                'message' => 'CAPTCHA verification is required',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $secret = \Mage::getStoreConfig(self::CONFIG_CAPTCHA_SECRET, $storeId);
        if (empty($secret)) {
            \Mage::log('Contact form: CAPTCHA secret key not configured', \Mage::LOG_WARNING);
            return null; // Don't block if misconfigured
        }

        $verifyUrl = match ($provider) {
            'turnstile' => self::TURNSTILE_VERIFY_URL,
            'recaptcha_v3' => self::RECAPTCHA_VERIFY_URL,
            default => null,
        };

        if ($verifyUrl === null) {
            return null;
        }

        try {
            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 5]);
            $response = $client->request('POST', $verifyUrl, [
                'body' => [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $request->getClientIp(),
                ],
            ]);

            $result = $response->toArray(false);

            if (empty($result['success'])) {
                return new JsonResponse([
                    'error' => 'captcha_failed',
                    'message' => 'CAPTCHA verification failed. Please try again.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // For reCAPTCHA v3, check score (0.0 = bot, 1.0 = human)
            if ($provider === 'recaptcha_v3' && isset($result['score']) && $result['score'] < 0.5) {
                return new JsonResponse([
                    'error' => 'captcha_failed',
                    'message' => 'CAPTCHA verification failed. Please try again.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } catch (\Exception $e) {
            \Mage::logException($e);
            // Don't block on verification service failure
            return null;
        }

        return null;
    }

    private function checkRateLimit(#[\SensitiveParameter]
    string $email, int $storeId): ?JsonResponse
    {
        $limit = (int) \Mage::getStoreConfig(self::CONFIG_RATE_LIMIT, $storeId);
        if ($limit <= 0) {
            return null;
        }

        $cacheKey = 'api_contact_rate_' . md5($email . '_' . $storeId);
        $cache = \Mage::app()->getCache();
        $count = (int) $cache->load($cacheKey);

        if ($count >= $limit) {
            return new JsonResponse([
                'error' => 'rate_limited',
                'message' => 'Too many submissions. Please try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return null;
    }

    private function recordSubmission(#[\SensitiveParameter]
    string $email, int $storeId): void
    {
        $limit = (int) \Mage::getStoreConfig(self::CONFIG_RATE_LIMIT, $storeId);
        if ($limit <= 0) {
            return;
        }

        $cacheKey = 'api_contact_rate_' . md5($email . '_' . $storeId);
        $cache = \Mage::app()->getCache();
        $count = (int) $cache->load($cacheKey);
        $cache->save((string) ($count + 1), $cacheKey, [], 3600); // 1 hour TTL
    }
}
