<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * HTTP Client decorator that adds OpenTelemetry tracing
 *
 * Wraps Symfony HttpClient to automatically create spans for HTTP requests
 * and inject W3C Trace Context headers for distributed tracing.
 */
class Maho_OpenTelemetry_Model_Http_TracedClient extends Mage_Core_Model_Abstract implements HttpClientInterface
{
    /**
     * Wrapped HTTP client
     */
    private ?HttpClientInterface $_client = null;

    /**
     * Tracer instance
     */
    private ?Maho_OpenTelemetry_Model_Tracer $_tracer = null;

    /**
     * Set the wrapped HTTP client
     *
     * @return $this
     */
    public function setClient(HttpClientInterface $client): self
    {
        $this->_client = $client;
        return $this;
    }

    /**
     * Set the tracer instance
     *
     * @return $this
     */
    public function setTracer(Maho_OpenTelemetry_Model_Tracer $tracer): self
    {
        $this->_tracer = $tracer;
        return $this;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->_client || !$this->_tracer) {
            throw new \RuntimeException('TracedHttpClient not properly initialized');
        }

        $span = $this->_tracer->startSpan('http.client.request', [
            'http.method' => $method,
            'http.url' => $url,
            'http.request.method' => $method,
        ]);

        try {
            // Inject W3C Trace Context headers for distributed tracing
            $propagationHeaders = $this->_tracer->getTracePropagationHeaders();
            if (!empty($propagationHeaders)) {
                $options['headers'] = array_merge(
                    $options['headers'] ?? [],
                    $propagationHeaders,
                );
            }

            $response = $this->_client->request($method, $url, $options);

            // Add response data
            $span->setAttributes([
                'http.status_code' => $response->getStatusCode(),
            ]);
            $span->setStatus('ok');

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        if (!$this->_client) {
            throw new \RuntimeException('TracedHttpClient not properly initialized');
        }

        return $this->_client->stream($responses, $timeout);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function withOptions(array $options): static
    {
        if (!$this->_client) {
            throw new \RuntimeException('TracedHttpClient not properly initialized');
        }

        $clone = clone $this;
        $clone->_client = $this->_client->withOptions($options);
        return $clone;
    }
}
