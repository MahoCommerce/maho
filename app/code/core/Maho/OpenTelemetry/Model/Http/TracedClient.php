<?php

/**
 * HTTP client decorator that wraps Symfony HttpClient to create spans for outgoing requests and inject W3C Trace Context headers for distributed tracing.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ChunkInterface;
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
        // Detached: the span outlives this call, so it must not adopt what the
        // caller does between issuing the request and reading the response
        $span = $this->_tracer->startDetachedSpan($method, $attributes, 'client');

        try {
            // Inject W3C Trace Context headers for distributed tracing
            $propagationHeaders = $this->_tracer->getTracePropagationHeaders($urlParts['host'] ?? '', $span);
            if (!empty($propagationHeaders)) {
                $options['headers'] = array_merge(
                    $options['headers'] ?? [],
                    $propagationHeaders,
                );
            }

            // The response closes the span once the body is consumed. Reading the status
            // here would stop the clock at the headers and serialize concurrent requests.
            return new Maho_OpenTelemetry_Model_Http_TracedResponse(
                $this->_client->request($method, $url, $options),
                $span,
            );
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute('error.type', $e::class);
            $span->setStatus('error', $e::class);
            $span->end();
            throw $e;
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

        if ($responses instanceof ResponseInterface) {
            $responses = [$responses];
        }

        // The wrapped client only recognizes its own responses, so hand it the
        // originals and map the chunks back to the decorators on the way out.
        // $unwrapped keeps every inner response alive, so its object id stays its own.
        $unwrapped = [];
        $decorators = [];
        foreach ($responses as $response) {
            if ($response instanceof Maho_OpenTelemetry_Model_Http_TracedResponse) {
                $inner = $response->getWrappedResponse();
                $decorators[spl_object_id($inner)] = $response;
                $unwrapped[] = $inner;
            } else {
                $unwrapped[] = $response;
            }
        }

        return new ResponseStream(
            self::_mapChunks($this->_client->stream($unwrapped, $timeout), $decorators),
        );
    }

    /**
     * Re-key the wrapped client's chunks onto the decorators, closing each span
     * when its response completes or fails
     *
     * @param array<int, Maho_OpenTelemetry_Model_Http_TracedResponse> $decorators By inner response object id
     * @return \Generator<ResponseInterface, ChunkInterface, mixed, void>
     */
    private static function _mapChunks(ResponseStreamInterface $stream, array $decorators): \Generator
    {
        foreach ($stream as $response => $chunk) {
            $decorator = $decorators[spl_object_id($response)] ?? null;

            try {
                $isLast = $chunk->isLast();
            } catch (\Throwable $e) {
                // An error chunk throws on every accessor
                $decorator?->closeSpan($e);
                throw $e;
            }

            if ($isLast) {
                $decorator?->closeSpan();
            }

            yield ($decorator ?? $response) => $chunk;
        }
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
