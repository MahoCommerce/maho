<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Model_Shipping_Carrier_Usps_RestClient
{
    private const BASE_URL_PRODUCTION = 'https://apis.usps.com';
    private const BASE_URL_TEST = 'https://apis-tem.usps.com';

    private const ENDPOINT_SHIPPING_OPTIONS = '/shipments/v3/options/search';
    private const ENDPOINT_TRACKING = '/tracking/v3/tracking';
    private const ENDPOINT_DOMESTIC_LABEL = '/labels/v3/label';
    private const ENDPOINT_INTERNATIONAL_LABEL = '/international-labels/v3/label';
    private const ENDPOINT_PAYMENT_AUTH = '/payments/v3/payment-authorization';

    private Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient $oauthClient;
    private string $baseUrl;
    private bool $debugMode;
    private ?string $paymentAuthToken = null;

    public function __construct(
        Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient $oauthClient,
        string $environment = 'production',
        bool $debugMode = false,
    ) {
        $this->oauthClient = $oauthClient;
        $this->baseUrl = ($environment === 'test') ? self::BASE_URL_TEST : self::BASE_URL_PRODUCTION;
        $this->debugMode = $debugMode;
    }

    /**
     * Get shipping options (all available rates including flat-rate containers)
     */
    public function getShippingOptions(array $requestData): array
    {
        return $this->makeRequest(
            'POST',
            self::ENDPOINT_SHIPPING_OPTIONS,
            $requestData,
        );
    }

    /**
     * Get tracking information
     */
    public function getTracking(string $trackingNumber): array
    {
        return $this->makeRequest(
            'GET',
            self::ENDPOINT_TRACKING . '/' . $trackingNumber,
            null,
        );
    }

    /**
     * Create payment authorization token
     */
    public function createPaymentAuthorization(array $paymentData): string
    {
        $response = $this->makeRequest(
            'POST',
            self::ENDPOINT_PAYMENT_AUTH,
            $paymentData,
        );

        if (empty($response['paymentAuthorizationToken'])) {
            throw new Exception('Failed to obtain payment authorization token');
        }

        $this->paymentAuthToken = $response['paymentAuthorizationToken'];
        return $this->paymentAuthToken;
    }

    /**
     * Set payment authorization token
     */
    public function setPaymentAuthToken(string $token): void
    {
        $this->paymentAuthToken = $token;
    }

    /**
     * Create domestic label
     */
    public function createDomesticLabel(array $labelData): array
    {
        if (!$this->paymentAuthToken) {
            throw new Exception('Payment authorization token is required for label creation');
        }

        return $this->makeRequest(
            'POST',
            self::ENDPOINT_DOMESTIC_LABEL,
            $labelData,
            ['X-Payment-Authorization-Token' => $this->paymentAuthToken],
        );
    }

    /**
     * Create international label
     */
    public function createInternationalLabel(array $labelData): array
    {
        if (!$this->paymentAuthToken) {
            throw new Exception('Payment authorization token is required for label creation');
        }

        return $this->makeRequest(
            'POST',
            self::ENDPOINT_INTERNATIONAL_LABEL,
            $labelData,
            ['X-Payment-Authorization-Token' => $this->paymentAuthToken],
        );
    }

    /**
     * Make HTTP request to USPS REST API
     */
    private function makeRequest(string $method, string $endpoint, ?array $data, array $additionalHeaders = []): array
    {
        $accessToken = $this->oauthClient->getAccessToken();

        $client = \Symfony\Component\HttpClient\HttpClient::create([
            'timeout' => 30,
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        // Merge additional headers (like payment auth token)
        $headers = array_merge($headers, $additionalHeaders);

        $options = [
            'headers' => $headers,
        ];

        if ($data !== null && $method === 'POST') {
            $options['json'] = $data;
        }

        $debugData = [
            'request' => [
                'method' => $method,
                'url' => $this->baseUrl . $endpoint,
                'data' => $data,
                'headers' => array_keys($additionalHeaders), // Log header names only, not values
            ],
        ];

        try {
            $response = $client->request($method, $this->baseUrl . $endpoint, $options);
            $contentType = $response->getHeaders()['content-type'][0] ?? '';

            // Handle JSON responses
            if (str_contains($contentType, 'application/json')) {
                $responseData = Mage::helper('core')->jsonDecode($response->getContent());
            } else {
                // Handle binary responses (labels)
                $responseData = [
                    'content' => base64_encode($response->getContent()),
                    'contentType' => $contentType,
                ];
            }

            $debugData['response'] = is_array($responseData) && isset($responseData['content'])
                ? ['contentType' => $responseData['contentType'], 'size' => strlen($responseData['content'])]
                : $responseData;

            if ($this->debugMode) {
                Mage::log($debugData, Mage::LOG_DEBUG, 'usps_rest_api.log');
            }

            return $responseData;
        } catch (Exception $e) {
            $debugData['error'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

            // Try to get the error response body for debugging
            if (method_exists($e, 'getResponse')) {
                try {
                    $errorResponse = $e->getResponse();
                    $debugData['error']['response_body'] = $errorResponse->getContent(false);
                } catch (Exception $ex) {
                    // Ignore if we can't get the response body
                }
            }

            if ($this->debugMode) {
                Mage::log($debugData, Mage::LOG_ERROR, 'usps_rest_api.log');
            }

            throw $e;
        }
    }
}
