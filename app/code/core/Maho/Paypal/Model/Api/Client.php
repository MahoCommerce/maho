<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\Logging\LoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\RequestLoggingConfigurationBuilder;
use PaypalServerSdkLib\Logging\ResponseLoggingConfigurationBuilder;

class Maho_Paypal_Model_Api_Client
{
    protected ?PaypalServerSdkClient $_client = null;
    protected ?Maho_Paypal_Model_Config $_config = null;
    protected ?int $_storeId = null;
    protected ?string $_explicitClientId = null;
    protected ?string $_explicitClientSecret = null;
    protected ?bool $_explicitSandbox = null;

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
                        ->requestConfiguration(RequestLoggingConfigurationBuilder::init()->body(true))
                        ->responseConfiguration(ResponseLoggingConfigurationBuilder::init()->body(true)),
                );
            }

            $this->_client = $builder->build();
        }
        return $this->_client;
    }

    public function createOrder(array $orderRequest): array
    {
        $response = $this->getSdkClient()->getOrdersController()->createOrder($orderRequest);
        return $this->_decodeResponse($response);
    }

    public function getOrder(string $orderId): array
    {
        $response = $this->getSdkClient()->getOrdersController()->getOrder(['id' => $orderId]);
        return $this->_decodeResponse($response);
    }

    public function authorizeOrder(string $orderId): array
    {
        $response = $this->getSdkClient()->getOrdersController()->authorizeOrder([
            'id' => $orderId,
            'body' => new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    public function captureOrder(string $orderId): array
    {
        $response = $this->getSdkClient()->getOrdersController()->captureOrder([
            'id' => $orderId,
            'body' => new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    public function captureAuthorization(string $authorizationId, array $body = []): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->captureAuthorizedPayment([
            'authorizationId' => $authorizationId,
            'body' => $body ?: new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    public function refundCapture(string $captureId, array $body = []): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->refundCapturedPayment([
            'captureId' => $captureId,
            'body' => $body ?: new \stdClass(),
        ]);
        return $this->_decodeResponse($response);
    }

    public function voidAuthorization(string $authorizationId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->voidPayment([
            'authorizationId' => $authorizationId,
        ]);
        return $this->_decodeResponse($response);
    }

    public function getAuthorization(string $authorizationId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->getAuthorizedPayment([
            'authorizationId' => $authorizationId,
        ]);
        return $this->_decodeResponse($response);
    }

    public function getCapture(string $captureId): array
    {
        $response = $this->getSdkClient()->getPaymentsController()->getCapturedPayment([
            'captureId' => $captureId,
        ]);
        return $this->_decodeResponse($response);
    }

    public function createSetupToken(array $body): array
    {
        $response = $this->getSdkClient()->getVaultController()->createSetupToken(['body' => $body]);
        return $this->_decodeResponse($response);
    }

    public function createPaymentToken(array $body): array
    {
        $response = $this->getSdkClient()->getVaultController()->createPaymentToken(['body' => $body]);
        return $this->_decodeResponse($response);
    }

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

        return Mage::helper('core')->jsonDecode($response->getContent());
    }

    public function verifyWebhookSignature(array $headers, string $body, string $webhookId): bool
    {
        $verifyBody = [
            'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
            'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
            'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
            'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => Mage::helper('core')->jsonDecode($body),
        ];

        $client = $this->_createHttpClient();
        $response = $client->request('POST', $this->_getApiUrl('/v1/notifications/verify-webhook-signature'), [
            'json' => $verifyBody,
            'headers' => $this->_getAuthHeaders(),
        ]);

        $result = Mage::helper('core')->jsonDecode($response->getContent());
        return ($result['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * Test API credentials by fetching an access token
     */
    public function testConnection(): bool
    {
        $client = $this->_createHttpClient();

        $response = $client->request('POST', $this->_getApiUrl('/v1/oauth2/token'), [
            'auth_basic' => [$this->_getClientId(), $this->_getClientSecret()],
            'body' => 'grant_type=client_credentials',
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $body = $response->getContent(false);
            throw new \RuntimeException("PayPal API returned HTTP {$statusCode}: {$body}");
        }

        return true;
    }

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
            return Mage::helper('core')->jsonDecode((string) $response->getBody());
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

    protected function _getAuthHeaders(): array
    {
        $client = $this->_createHttpClient();

        $response = $client->request('POST', $this->_getApiUrl('/v1/oauth2/token'), [
            'auth_basic' => [$this->_getClientId(), $this->_getClientSecret()],
            'body' => 'grant_type=client_credentials',
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        $token = Mage::helper('core')->jsonDecode($response->getContent());

        return [
            'Authorization' => 'Bearer ' . ($token['access_token'] ?? ''),
            'Content-Type' => 'application/json',
        ];
    }

    protected function _createHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
    }

    protected function _log(string $message, array $context = []): void
    {
        if ($this->getConfig()->isDebug($this->_storeId)) {
            Mage::log($message . ' ' . Mage::helper('core')->jsonEncode($context), Mage::LOG_DEBUG, 'paypal.log');
        }
    }
}
