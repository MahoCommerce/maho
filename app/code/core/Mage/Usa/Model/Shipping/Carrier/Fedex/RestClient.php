<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient
{
    public const BASE_URL_PRODUCTION = 'https://apis.fedex.com';
    public const BASE_URL_SANDBOX = 'https://apis-sandbox.fedex.com';

    public const ENDPOINT_RATES = '/rate/v1/rates/quotes';
    public const ENDPOINT_RATES_COMPREHENSIVE = '/rate/v1/comprehensiverates/quotes';

    private const ENDPOINT_TRACK = '/track/v1/trackingnumbers';
    private const ENDPOINT_SHIP = '/ship/v1/shipments';
    private const ENDPOINT_SHIP_CANCEL = '/ship/v1/shipments/cancel';

    private Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient $oauthClient;
    private string $baseUrl;
    private bool $debugMode;
    private string $rateEndpoint;

    public static function getBaseUrl(bool $sandbox): string
    {
        return $sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PRODUCTION;
    }

    public function __construct(
        Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient $oauthClient,
        bool $sandbox = false,
        bool $debugMode = false,
        string $rateEndpoint = Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_STANDARD,
    ) {
        $this->oauthClient = $oauthClient;
        $this->baseUrl = self::getBaseUrl($sandbox);
        $this->debugMode = $debugMode;
        $this->rateEndpoint = $rateEndpoint === Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_COMPREHENSIVE
            ? self::ENDPOINT_RATES_COMPREHENSIVE
            : self::ENDPOINT_RATES;
    }

    public function getRateEndpoint(): string
    {
        return $this->rateEndpoint;
    }

    public function getRates(array $requestData): array
    {
        return $this->makeRequest('POST', $this->rateEndpoint, $requestData);
    }

    public function track(string $trackingNumber): array
    {
        return $this->makeRequest('POST', self::ENDPOINT_TRACK, [
            'includeDetailedScans' => true,
            'trackingInfo' => [
                ['trackingNumberInfo' => ['trackingNumber' => $trackingNumber]],
            ],
        ]);
    }

    public function createShipment(array $requestData): array
    {
        return $this->makeRequest('POST', self::ENDPOINT_SHIP, $requestData);
    }

    public function cancelShipment(array $requestData): array
    {
        return $this->makeRequest('PUT', self::ENDPOINT_SHIP_CANCEL, $requestData);
    }

    /**
     * Flatten a FedEx REST error payload into a single message.
     *
     * FedEx answers both HTTP-error and HTTP-200 failures with a top-level
     * errors[] of {code, message}, so one extractor covers every path.
     */
    public static function extractErrorMessage(array $data): ?string
    {
        if (empty($data['errors']) || !is_array($data['errors'])) {
            return null;
        }

        $messages = [];
        foreach ($data['errors'] as $error) {
            if (!empty($error['message'])) {
                $messages[] = $error['message'];
            } elseif (!empty($error['code'])) {
                $messages[] = $error['code'];
            }
        }

        return $messages === [] ? null : implode('; ', $messages);
    }

    /**
     * Make an HTTP request to the FedEx REST API.
     *
     * Error payloads are returned rather than thrown so callers can surface them
     * through the carrier's own rate/tracking error results.
     */
    private function makeRequest(string $method, string $endpoint, array $data): array
    {
        $client = \Symfony\Component\HttpClient\HttpClient::create([
            'timeout' => 30,
        ]);

        $url = $this->baseUrl . $endpoint;
        $debugData = ['request' => ['method' => $method, 'url' => $url, 'data' => $data]];

        try {
            $response = $client->request($method, $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->oauthClient->getAccessToken(),
                    'Content-Type' => 'application/json',
                    'X-locale' => 'en_US',
                ],
                'json' => $data,
            ]);

            // getContent(false) keeps 4xx/5xx bodies readable: FedEx puts the actionable
            // message in the body of an error response, not in the status line.
            $responseData = Mage::helper('core')->jsonDecode($response->getContent(false));
            $debugData['result'] = $responseData;
        } catch (Exception $e) {
            $responseData = ['errors' => [['code' => (string) $e->getCode(), 'message' => $e->getMessage()]]];
            $debugData['result'] = $responseData;
            Mage::logException($e);
        }

        if ($this->debugMode) {
            Mage::log($debugData, Mage::LOG_DEBUG, 'fedex_rest_api.log');
        }

        return is_array($responseData) ? $responseData : [];
    }
}
