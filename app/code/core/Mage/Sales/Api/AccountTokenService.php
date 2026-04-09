<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

/**
 * HMAC-signed account token for guest order → customer account creation
 *
 * Token format: base64(orderId|email|timestamp|action=<action>).hmac_sha256(base64_payload, crypt_key)
 */
final class AccountTokenService
{
    /**
     * Generate an HMAC-signed account token
     */
    public static function generate(int $orderId, #[\SensitiveParameter]
        string $email, string $action = 'create_account'): string
    {
        $payload = $orderId . '|' . $email . '|' . time() . '|action=' . $action;
        $payloadBase64 = base64_encode($payload);
        $signature = hash_hmac('sha256', $payloadBase64, self::getCryptKey());

        return $payloadBase64 . '.' . $signature;
    }

    /**
     * Verify an HMAC-signed account token and return its parsed payload
     *
     * @return array{orderId: int, email: string, timestamp: int, action: string}
     *
     * @throws \Mage_Core_Exception on invalid or expired tokens
     */
    public static function verify(string $token, int $maxAgeSeconds = 86400): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new \Mage_Core_Exception('Invalid account token format.');
        }

        [$payloadBase64, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $payloadBase64, self::getCryptKey());

        if (!hash_equals($expectedSignature, $signature)) {
            throw new \Mage_Core_Exception('Invalid or expired account token.');
        }

        $payload = base64_decode($payloadBase64, true);
        if ($payload === false) {
            throw new \Mage_Core_Exception('Invalid token payload.');
        }

        $payloadParts = explode('|', $payload);
        if (count($payloadParts) < 4) {
            throw new \Mage_Core_Exception('Invalid token payload.');
        }

        $orderId = (int) $payloadParts[0];
        $email = $payloadParts[1];
        $timestamp = (int) $payloadParts[2];
        $actionPart = $payloadParts[3];

        if (!str_starts_with($actionPart, 'action=')) {
            throw new \Mage_Core_Exception('Invalid token action.');
        }

        $action = substr($actionPart, 7);

        if (time() - $timestamp > $maxAgeSeconds) {
            throw new \Mage_Core_Exception('Account creation token has expired.');
        }

        return [
            'orderId' => $orderId,
            'email' => $email,
            'timestamp' => $timestamp,
            'action' => $action,
        ];
    }

    private static function getCryptKey(): string
    {
        return (string) \Mage::app()->getConfig()->getNode('global/crypt/key');
    }
}
