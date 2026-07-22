<?php

/**
 * HTTP client decorator that wraps Symfony HttpClient to create spans for outgoing requests and inject W3C Trace Context headers for distributed tracing.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class Maho_OpenTelemetry_Model_Http_TracedClient implements HttpClientInterface
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

        // Span name per HTTP client semconv is the bare method. url.full is recomposed
        // without query string, fragment or userinfo so tokens/secrets in query params
        // or embedded credentials (https://user:pass@host) never reach a span
        $urlParts = parse_url($url);
        if (!empty($urlParts['host'])) {
            $urlFull = ($urlParts['scheme'] ?? 'http') . '://' . $urlParts['host']
                . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '')
                . ($urlParts['path'] ?? '/');
        } else {
            $urlFull = strtok($url, '?') ?: $url;
        }
        $attributes = [
            'http.request.method' => $method,
            'url.full' => $urlFull,
        ];
        if (!empty($urlParts['host'])) {
            $attributes['server.address'] = $urlParts['host'];
        }
        if (!empty($urlParts['port'])) {
            $attributes['server.port'] = $urlParts['port'];
        }
        $span = $this->_tracer->startSpan($method, $attributes, 'client');

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
            $statusCode = $response->getStatusCode();
            $span->setAttribute('http.response.status_code', $statusCode);
            $span->setStatus($statusCode >= 500 ? 'error' : 'ok');

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute('error.type', $e::class);
            $span->setStatus('error', $e::class);
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
