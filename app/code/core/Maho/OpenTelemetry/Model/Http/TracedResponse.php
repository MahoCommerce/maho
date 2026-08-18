<?php

/**
 * Symfony HTTP response decorator that keeps the client span open until the body is consumed.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\Response\StreamableInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Symfony responses are lazy: the body is transferred during getContent()/toArray()/
 * stream(), which is also where a transport failure surfaces. Ending the span at
 * request() would time the connection alone and miss those failures.
 */
class Maho_OpenTelemetry_Model_Http_TracedResponse implements ResponseInterface, StreamableInterface
{
    private bool $_closed = false;

    public function __construct(
        private readonly ResponseInterface $_response,
        private readonly Maho_OpenTelemetry_Model_Span $_span,
    ) {}

    /**
     * The decorated response, for handing back to the client's stream()
     */
    public function getWrappedResponse(): ResponseInterface
    {
        return $this->_response;
    }

    /**
     * End the span, recording the outcome. Idempotent: body consumption,
     * cancellation, stream completion and destruction all call it.
     */
    public function closeSpan(?\Throwable $error = null): void
    {
        if ($this->_closed) {
            return;
        }
        $this->_closed = true;

        // Non-blocking, unlike getStatusCode(): 0 until the headers arrived
        $statusCode = (int) ($this->_response->getInfo('http_code') ?: 0);
        if ($statusCode > 0) {
            $this->_span->setAttribute('http.response.status_code', $statusCode);
        }

        if ($error !== null) {
            $this->_span->recordException($error);
            $this->_span->setAttribute('error.type', $error::class);
            $this->_span->setStatus('error', $error::class);
        } elseif ($statusCode >= 500) {
            $this->_span->setStatus('error', 'HTTP ' . $statusCode);
        } elseif ($statusCode > 0) {
            $this->_span->setStatus('ok');
        }
        // Abandoned before its headers arrived: status stays unset

        $this->_span->end();
    }

    #[\Override]
    public function getStatusCode(): int
    {
        return $this->_response->getStatusCode();
    }

    #[\Override]
    public function getHeaders(bool $throw = true): array
    {
        return $this->_response->getHeaders($throw);
    }

    #[\Override]
    public function getContent(bool $throw = true): string
    {
        try {
            $content = $this->_response->getContent($throw);
        } catch (\Throwable $e) {
            $this->closeSpan($e);
            throw $e;
        }

        $this->closeSpan();
        return $content;
    }

    #[\Override]
    public function toArray(bool $throw = true): array
    {
        try {
            $data = $this->_response->toArray($throw);
        } catch (\Throwable $e) {
            $this->closeSpan($e);
            throw $e;
        }

        $this->closeSpan();
        return $data;
    }

    #[\Override]
    public function cancel(): void
    {
        $this->_response->cancel();
        $this->closeSpan();
    }

    #[\Override]
    public function getInfo(?string $type = null): mixed
    {
        return $this->_response->getInfo($type);
    }

    /**
     * @return resource
     */
    #[\Override]
    public function toStream(bool $throw = true)
    {
        if (!$this->_response instanceof StreamableInterface) {
            throw new \LogicException(sprintf('%s cannot be converted to a stream.', get_debug_type($this->_response)));
        }

        return $this->_response->toStream($throw);
    }

    public function __destruct()
    {
        $this->closeSpan();
    }
}
