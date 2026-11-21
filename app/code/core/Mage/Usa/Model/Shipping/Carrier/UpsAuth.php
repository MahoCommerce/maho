<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2024-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_UpsAuth extends Mage_Usa_Model_Shipping_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * Cache key prefix for UPS API token
     */
    public const CACHE_KEY_PREFIX = 'ups_api_token_';

    /**
     * @throws Exception
     */
    public function getAccessToken(string $clientId, string $clientSecret, string $clientUrl): string
    {
        $cacheKey = $this->getCacheKey($clientId, $clientSecret, $clientUrl);
        $cache = Mage::app()->getCache();
        $result = $cache->load($cacheKey);
        if ($result) {
            return $result;
        }

        $result = $this->fetchAccessToken($clientId, $clientSecret, $clientUrl);
        $cache->save($result['access_token'], $cacheKey, [], $result['expires_in']);
        return $result['access_token'];
    }

    /**
     * @return array{access_token: string, expires_in: int}
     * @throws Exception
     */
    protected function fetchAccessToken(string $clientId, string $clientSecret, string $clientUrl): array
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            "x-merchant-id: $clientId",
            'Authorization: Basic ' . base64_encode("$clientId:$clientSecret"),
        ];
        $authPayload = http_build_query([
            'grant_type' => 'client_credentials',
        ]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $clientUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $authPayload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->getConfigFlag('verify_peer'));
        $responseData = curl_exec($ch);
        if ($responseData === false) {
            $code = curl_errno($ch);
            $description = curl_strerror($code);
            $message = curl_error($ch);
            Mage::throwException("cURL Error: ($code) $description - \"$message\"");
        }

        $responseData = json_decode($responseData);

        if (isset($responseData->errors)) {
            Mage::throwException('Failed to authenticate with UPS. Errors: ' . json_encode($responseData->errors));
        }

        if (!isset($responseData->access_token)) {
            Mage::throwException('Error decoding auth token from UPS');
        }

        return [
            'access_token' => (string) $responseData->access_token,
            'expires_in' => (int) ($responseData->expires_in ?? 10000),
        ];
    }

    protected function getCacheKey(string $clientId, string $clientSecret, string $clientUrl): string
    {
        return self::CACHE_KEY_PREFIX . hash('sha256', $clientId . "\0" . $clientSecret . "\0" . $clientUrl);
    }

    #[\Override]
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        return false;
    }

    #[\Override]
    protected function _doShipmentRequest(\Maho\DataObject $request)
    {
        return new \Maho\DataObject();
    }

    #[\Override]
    public function getAllowedMethods(): array
    {
        return [];
    }
}
