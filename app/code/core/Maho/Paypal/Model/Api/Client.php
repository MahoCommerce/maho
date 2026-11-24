<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Logging\LoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\RequestLoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\ResponseLoggingConfigurationBuilder;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @phpstan-import-type OrderRequest from Maho_Paypal_Model_Api_OrderBuilder
 * @phpstan-type PaypalMoney array{currency_code?: string, value?: string, ...}
 * @phpstan-type PaypalProcessorResponse array{avs_code?: string, cvv_code?: string, response_code?: string, payment_advice_code?: string, ...}
 * @phpstan-type PaypalRelatedIds array{related_ids?: array{order_id?: string, authorization_id?: string, capture_id?: string, ...}, ...}
 * @phpstan-type PaypalCapture array{id?: string, status?: string, status_details?: array{reason?: string, ...}, amount?: PaypalMoney, invoice_id?: string, final_capture?: bool, processor_response?: PaypalProcessorResponse, supplementary_data?: PaypalRelatedIds, ...}
 * @phpstan-type PaypalAuthorization array{id?: string, status?: string, status_details?: array{reason?: string, ...}, amount?: PaypalMoney, invoice_id?: string, expiration_time?: string, processor_response?: PaypalProcessorResponse, supplementary_data?: PaypalRelatedIds, ...}
 * @phpstan-type PaypalRefund array{id?: string, status?: string, amount?: PaypalMoney, invoice_id?: string, ...}
 * @phpstan-type PaypalAddress array{address_line_1?: string, address_line_2?: string, admin_area_1?: string, admin_area_2?: string, postal_code?: string, country_code?: string, ...}
 * @phpstan-type PaypalPurchaseUnit array{reference_id?: string, invoice_id?: string, amount?: PaypalMoney, shipping?: array{name?: array{full_name?: string, ...}, address?: PaypalAddress, ...}, payments?: array{captures?: list<PaypalCapture>, authorizations?: list<PaypalAuthorization>, refunds?: list<PaypalRefund>, ...}, ...}
 * @phpstan-type PaypalPayer array{payer_id?: string, email_address?: string, name?: array{given_name?: string, surname?: string, ...}, phone?: array{phone_number?: array{national_number?: string, ...}, ...}, address?: PaypalAddress, ...}
 * @phpstan-type PaypalVault array{id?: string, status?: string, customer?: array{id?: string, ...}, ...}
 * @phpstan-type PaypalCard array{name?: string, last_digits?: string, brand?: string, type?: string, expiry?: string, attributes?: array{vault?: PaypalVault, ...}, authentication_result?: array{liability_shift?: string, three_d_secure?: array{enrollment_status?: string, authentication_status?: string, ...}, ...}, ...}
 * @phpstan-type PaypalWallet array{account_id?: string, email_address?: string, name?: array{given_name?: string, surname?: string, ...}, attributes?: array{vault?: PaypalVault, ...}, ...}
 * @phpstan-type PaypalPaymentSource array{card?: PaypalCard, paypal?: PaypalWallet, ...}
 * @phpstan-type PaypalOrder array{id?: string, status?: string, intent?: string, payer?: PaypalPayer, purchase_units?: list<PaypalPurchaseUnit>, payment_source?: PaypalPaymentSource, ...}
 * @phpstan-type PaypalPaymentToken array{id?: string, status?: string, customer?: array{id?: string, ...}, payment_source?: PaypalPaymentSource, ...}
 * @phpstan-type PaypalPaymentTokenList array{customer?: array{id?: string, ...}, payment_tokens?: list<PaypalPaymentToken>, total_items?: int, total_pages?: int, ...}
 * @phpstan-type PaypalWebhook array{id?: string, url?: string, event_types?: list<array{name?: string, ...}>, ...}
 * @phpstan-type PaypalToken array{access_token?: string, token_type?: string, expires_in?: int, scope?: string, app_id?: string, nonce?: string, ...}
 * @phpstan-type AmountRequest array{currency_code: string|null, value: string}
 * @phpstan-type VaultTokenRequest array{payment_source: array<string, array<string, mixed>>, customer?: array{id?: string, merchant_customer_id?: string}}
 */
class Maho_Paypal_Model_Api_Client
{
    /** Seconds before a token request gives up. The token endpoint answers in less than one second. */
    public const int TOKEN_REQUEST_TIMEOUT = 8;

    /** Seconds that a cached client token must still be valid when checkout receives it. */
    public const int CLIENT_TOKEN_MIN_LIFETIME = 600;

    protected ?PaypalServerSdkClient $_client = null;
    protected ?Maho_Paypal_Model_Config $_config = null;
    protected ?int $_storeId = null;
    protected ?string $_explicitClientId = null;
    protected ?string $_explicitClientSecret = null;
    protected ?bool $_explicitSandbox = null;
    protected ?string $_cachedAccessToken = null;

    /**
     * @param array{store_id?: int|string|null} $args
     */
    public function __construct(array $args = [])
    {
        if (isset($args['store_id'])) {
            $this->_storeId = (int) $args['store_id'];
        }
    }

    public function setStoreId(int $storeId): self
    {
        $this->_storeId = $storeId;
        return $this;
    }

    public function setExplicitCredentials(string $clientId, string $clientSecret, bool $sandbox): self
    {
        $this->_explicitClientId = $clientId;
        $this->_explicitClientSecret = $clientSecret;
        $this->_explicitSandbox = $sandbox;
        $this->_client = null;
        return $this;
    }

    public function getConfig(): Maho_Paypal_Model_Config
    {
        if ($this->_config === null) {
            $model = Mage::getModel('paypal/config');
            $this->_config = $model;
        }
        return $this->_config;
    }

    public function getSdkClient(): PaypalServerSdkClient
    {
        if ($this->_client === null) {
            $config = $this->getConfig();
            $clientId = $this->_explicitClientId ?? $config->getClientId($this->_storeId);
            $clientSecret = $this->_explicitClientSecret ?? $config->getClientSecret($this->_storeId);
            $sandbox = $this->_explicitSandbox ?? $config->isSandbox($this->_storeId);

            $builder = PaypalServerSdkClientBuilder::init()
                ->clientCredentialsAuthCredentials(
                    ClientCredentialsAuthCredentialsBuilder::init($clientId, $clientSecret),
                )
                ->environment($sandbox ? Environment::SANDBOX : Environment::PRODUCTION);

            if ($config->isDebug($this->_storeId)) {
                $logger = new \Monolog\Logger('paypal');
                $logger->pushHandler(new \Monolog\Handler\StreamHandler(
                    Mage::getBaseDir('log') . '/paypal.log',
                ));
                $builder->loggingConfiguration(
                    LoggingConfigurationBuilder::init()
                        ->logger($logger)
                        ->maskSensitiveHeaders(true)
                        ->requestConfiguration(RequestLoggingConfigurationBuilder::init()->body(false)->headers(true))
                        ->responseConfiguration(ResponseLoggingConfigurationBuilder::init()->body(false)->headers(true)),
                );
            }

            $this->_client = $builder->build();
        }
        return $this->_client;
    }

    /**
     * @param array{body: OrderRequest, paypalRequestId?: string, prefer?: string} $orderRequest
     * @return PaypalOrder
     */
    public function createOrder(array $orderRequest): array
    {
        $orderRequest['paypalRequestId'] ??= uniqid('maho-', true);
        $orderRequest['prefer'] = 'return=representation';
        $response = $this->getSdkClient()->getOrdersController()->createOrder($orderRequest);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalOrder
     */
    public function getOrder(string $orderId, ?string $fields = null): array
    {
        $options = ['id' => $orderId];
        if ($fields !== null) {
            $options['fields'] = $fields;
        }
        $response = $this->getSdkClient()->getOrdersController()->getOrder($options);
        return $this->_decodeResponse($response);
    }

    /**
     * @param list<array{op: 'add'|'remove'|'replace'|'move'|'copy'|'test', path: string, value?: mixed, from?: string}> $patchOperations
     */
    public function patchOrder(string $orderId, array $patchOperations): void
    {
        $client = $this->_createHttpClient();
        $response = $client->request('PATCH', $this->_getApiUrl("/v2/checkout/orders/{$orderId}"), [
            'json' => $patchOperations,
            'headers' => $this->_getAuthHeaders(),
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 204) {
            $body = $response->getContent(false);
            throw new \RuntimeException("PayPal PATCH order failed with HTTP {$statusCode}: {$body}");
        }
    }

    /**
     * @return PaypalOrder
     */
    public function authorizeOrder(string $orderId): array
    {
        $response = $this->getSdkClient()->getOrdersController()->authorizeOrder([
            'id' => $orderId,
            'body' => new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalOrder
     */
    public function captureOrder(string $orderId): array
    {
        $response = $this->getSdkClient()->getOrdersController()->captureOrder([
            'id' => $orderId,
            'body' => new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @param array{amount?: AmountRequest, final_capture?: bool} $body
     * @return PaypalCapture
     */
    public function captureAuthorization(string $authorizationId, array $body = []): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->captureAuthorizedPayment([
            'authorizationId' => $authorizationId,
            'body' => $body ?: new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @param array{amount?: AmountRequest} $body
     * @return PaypalRefund
     */
    public function refundCapture(string $captureId, array $body = []): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->refundCapturedPayment([
            'captureId' => $captureId,
            'body' => $body ?: new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalAuthorization
     */
    public function voidAuthorization(string $authorizationId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->voidPayment([
            'authorizationId' => $authorizationId,
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalAuthorization
     */
    public function getAuthorization(string $authorizationId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->getAuthorizedPayment([
            'authorizationId' => $authorizationId,
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalCapture
     */
    public function getCapture(string $captureId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->getCapturedPayment([
            'captureId' => $captureId,
        ]);
        return $this->_decodeResponse($response);
    }

    /**
     * @param VaultTokenRequest $body
     * @return PaypalPaymentToken
     */
    public function createSetupToken(array $body): array
    {
        $response = $this->getSdkClient()->getVaultController()->createSetupToken(['body' => $body]);
        return $this->_decodeResponse($response);
    }

    /**
     * @param VaultTokenRequest $body
     * @return PaypalPaymentToken
     */
    public function createPaymentToken(array $body): array
    {
        $response = $this->getSdkClient()->getVaultController()->createPaymentToken(['body' => $body]);
        return $this->_decodeResponse($response);
    }

    /**
     * @return PaypalPaymentTokenList
     */
    public function listPaymentTokens(string $customerId): array
    {
        $response = $this->getSdkClient()->getVaultController()->listCustomerPaymentTokens([
            'customerId' => $customerId,
        ]);
        return $this->_decodeResponse($response);
    }

    public function deletePaymentToken(string $tokenId): void
    {
        $this->getSdkClient()->getVaultController()->deletePaymentToken($tokenId);
    }

    public function generateClientToken(): string
    {
        $cacheId = 'PAYPAL_CLIENT_TOKEN_' . hash(
            'sha256',
            implode('|', [(int) $this->_isSandbox(), $this->_getClientId(), $this->_getClientSecret()]),
        );
        $cached = Mage::app()->loadCache($cacheId);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $result = $this->_requestToken('grant_type=client_credentials&response_type=client_token&intent=sdk_init');
        $clientToken = $result['access_token'] ?? '';

        if ($clientToken === '') {
            throw new \RuntimeException('Failed to generate PayPal client token');
        }

        $lifetime = (int) ($result['expires_in'] ?? 0) - self::CLIENT_TOKEN_MIN_LIFETIME;
        if ($lifetime > 0) {
            Mage::app()->saveCache($clientToken, $cacheId, [Mage_Core_Model_Config::CACHE_TAG], $lifetime);
        }

        return $clientToken;
    }

    /**
     * @param list<string> $eventTypes
     * @return PaypalWebhook
     */
    public function createWebhook(string $url, array $eventTypes): array
    {
        $body = [
            'url' => $url,
            'event_types' => array_map(fn(string $type) => ['name' => $type], $eventTypes),
        ];

        $client = $this->_createHttpClient();
        $response = $client->request('POST', $this->_getApiUrl('/v1/notifications/webhooks'), [
            'json' => $body,
            'headers' => $this->_getAuthHeaders(),
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 201) {
            $errorBody = $response->getContent(false);
            $errorData = Mage::helper('core')->jsonDecode($errorBody);
            $message = $errorData['message'] ?? $errorBody;
            if (!empty($errorData['details'])) {
                $details = array_map(fn(array $d) => $d['description'] ?? $d['issue'] ?? '', $errorData['details']);
                $message .= ': ' . implode('; ', array_filter($details, static fn(mixed $detail): bool => $detail !== ''));
            }
            $this->_log('Webhook registration failed', ['url' => $url, 'status' => $statusCode, 'response' => $errorData]);
            throw new \RuntimeException($message);
        }

        return Mage::helper('core')->jsonDecode($response->getContent());
    }

    /**
     * @param array<string, string> $headers
     */
    public function verifyWebhookSignature(array $headers, string $body, string $webhookId): bool
    {
        $certUrl = $headers['PAYPAL-CERT-URL'] ?? '';
        if (!preg_match('#^https://api(-m)?\.(?:sandbox\.)?paypal\.com/#', $certUrl)) {
            $this->_log('Webhook verification rejected', ['reason' => 'cert_url', 'cert_url' => $certUrl]);
            return false;
        }

        if (!json_validate($body)) {
            $this->_log('Webhook verification rejected', ['reason' => 'malformed_body']);
            return false;
        }

        $client = $this->_createHttpClient();
        $response = $client->request('POST', $this->_getApiUrl('/v1/notifications/verify-webhook-signature'), [
            'body' => self::buildVerificationRequest($headers, $webhookId, $body),
            'headers' => $this->_getAuthHeaders(),
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->_log('Webhook signature verification failed', ['status' => $statusCode, 'response' => $response->getContent(false)]);
            return false;
        }

        $result = Mage::helper('core')->jsonDecode($response->getContent(false));
        $verdict = $result['verification_status'] ?? '';
        if ($verdict !== 'SUCCESS') {
            $this->_log('Webhook signature verification failed', [
                'reason' => 'verdict',
                'verification_status' => $verdict,
                'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            ]);
        }

        return $verdict === 'SUCCESS';
    }

    /**
     * The transmission signature covers the exact bytes PayPal delivered, so the event
     * must be spliced into the request verbatim — a decode/encode round-trip changes
     * JSON semantics (PHP folds {} into []), which fails verification for any payload
     * containing an empty object, e.g. every CHECKOUT.ORDER.APPROVED event.
     *
     * @param array<string, string> $headers
     */
    public static function buildVerificationRequest(array $headers, string $webhookId, string $rawBody): string
    {
        return sprintf(
            '{"auth_algo":%s,"cert_url":%s,"transmission_id":%s,"transmission_sig":%s,'
            . '"transmission_time":%s,"webhook_id":%s,"webhook_event":%s}',
            json_encode($headers['PAYPAL-AUTH-ALGO'] ?? ''),
            json_encode($headers['PAYPAL-CERT-URL'] ?? ''),
            json_encode($headers['PAYPAL-TRANSMISSION-ID'] ?? ''),
            json_encode($headers['PAYPAL-TRANSMISSION-SIG'] ?? ''),
            json_encode($headers['PAYPAL-TRANSMISSION-TIME'] ?? ''),
            json_encode($webhookId),
            $rawBody,
        );
    }

    /**
     * Test API credentials by fetching an access token
     */
    public function testConnection(): bool
    {
        $this->_requestToken('grant_type=client_credentials');
        return true;
    }

    /**
     * @return array<mixed>
     */
    protected function _decodeResponse(mixed $response): array
    {
        if (is_object($response) && method_exists($response, 'getResult')) {
            $result = $response->getResult();
            if (is_object($result)) {
                return Mage::helper('core')->jsonDecode(
                    Mage::helper('core')->jsonEncode($result),
                );
            }
            if (is_array($result)) {
                return $result;
            }
        }

        if (is_object($response) && method_exists($response, 'getBody')) {
            // Empty body (HTTP 204) decodes to "", not an array.
            $body = (string) $response->getBody();
            $decoded = $body === '' ? [] : Mage::helper('core')->jsonDecode($body);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function _getClientId(): string
    {
        return $this->_explicitClientId ?? $this->getConfig()->getClientId($this->_storeId);
    }

    protected function _getClientSecret(): string
    {
        return $this->_explicitClientSecret ?? $this->getConfig()->getClientSecret($this->_storeId);
    }

    protected function _isSandbox(): bool
    {
        return $this->_explicitSandbox ?? $this->getConfig()->isSandbox($this->_storeId);
    }

    protected function _getApiUrl(string $path): string
    {
        $base = $this->_isSandbox()
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        return $base . $path;
    }

    /**
     * @return array{Authorization: string, 'Content-Type': string}
     */
    protected function _getAuthHeaders(): array
    {
        if ($this->_cachedAccessToken !== null) {
            return [
                'Authorization' => 'Bearer ' . $this->_cachedAccessToken,
                'Content-Type' => 'application/json',
            ];
        }

        $token = $this->_requestToken('grant_type=client_credentials');
        $accessToken = $token['access_token'] ?? '';

        if ($accessToken === '') {
            throw new \RuntimeException('PayPal OAuth response missing access_token');
        }

        $this->_cachedAccessToken = $accessToken;

        return [
            'Authorization' => 'Bearer ' . $this->_cachedAccessToken,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Request an OAuth token from PayPal. Send the request again once if it does not reach PayPal.
     *
     * @return PaypalToken
     */
    protected function _requestToken(string $body): array
    {
        try {
            $response = $this->_sendTokenRequest($body);
        } catch (TransportExceptionInterface) {
            $response = $this->_sendTokenRequest($body);
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $content = $response->getContent(false);
            throw new \RuntimeException("PayPal OAuth token request failed with HTTP {$statusCode}: {$content}");
        }

        return Mage::helper('core')->jsonDecode($response->getContent());
    }

    /**
     * @throws TransportExceptionInterface When the request does not reach PayPal
     */
    protected function _sendTokenRequest(string $body): ResponseInterface
    {
        $response = $this->_createHttpClient()->request('POST', $this->_getApiUrl('/v1/oauth2/token'), [
            'auth_basic' => [$this->_getClientId(), $this->_getClientSecret()],
            'body' => $body,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'max_duration' => self::TOKEN_REQUEST_TIMEOUT,
        ]);
        // The response is lazy: read the body here, so that a transport error throws inside the retry
        $response->getContent(false);
        return $response;
    }

    protected function _createHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return \Maho\Http\Client::create(['timeout' => 30]);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function _log(string $message, array $context = []): void
    {
        if ($this->getConfig()->isDebug($this->_storeId)) {
            Mage::log($message . ' ' . Mage::helper('core')->jsonEncode($context), Mage::LOG_DEBUG, 'paypal.log');
        }
    }
}
