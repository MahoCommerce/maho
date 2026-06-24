<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

/**
 * HMAC-signed account token for guest order → customer account creation
 *
 * Token format: base64(json{orderId,email,timestamp,action}).hmac_sha256(base64_payload, crypt_key)
 */
final class AccountTokenService
{
    /**
     * Generate an HMAC-signed account token
     */
    public static function generate(int $orderId, #[\SensitiveParameter]
        string $email, string $action = 'create_account'): string
    {
        // JSON-encode the payload so no field value (e.g. an email containing a
        // delimiter) can shift parse boundaries and forge another field.
        $payload = json_encode([
            'orderId' => $orderId,
            'email' => $email,
            'timestamp' => time(),
            'action' => $action,
        ], JSON_THROW_ON_ERROR);
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

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \Mage_Core_Exception('Invalid token payload.');
        }

        if (!is_array($data)
            || !isset($data['orderId'], $data['email'], $data['timestamp'], $data['action'])
        ) {
            throw new \Mage_Core_Exception('Invalid token payload.');
        }

        $orderId = (int) $data['orderId'];
        $email = (string) $data['email'];
        $timestamp = (int) $data['timestamp'];
        $action = (string) $data['action'];

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
