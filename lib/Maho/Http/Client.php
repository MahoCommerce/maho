<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Http
 */

declare(strict_types=1);

namespace Maho\Http;

use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP Client factory with automatic OpenTelemetry instrumentation
 *
 * Creates Symfony HTTP clients with optional tracing when OpenTelemetry
 * module is enabled. Falls back to standard Symfony client when telemetry
 * is disabled or not installed.
 *
 * Usage:
 * ```php
 * $client = \Maho\Http\Client::create(['timeout' => 30]);
 * $response = $client->request('GET', 'https://api.example.com/data');
 * ```
 */
class Client
{
    /**
     * Create HTTP client with optional tracing
     *
     * @param array $options Symfony HttpClient options
     */
    public static function create(array $options = []): HttpClientInterface
    {
        $client = SymfonyHttpClient::create($options);

        // Wrap with tracing decorator if tracer exists
        $tracer = \Mage::getTracer();
        if ($tracer && $tracer->isEnabled()) {
            // Check if TracedHttpClient class exists (from OpenTelemetry module)
            if (class_exists('Maho_OpenTelemetry_Model_Http_TracedClient')) {
                $tracedClient = \Mage::getModel('opentelemetry/http_tracedclient');
                if ($tracedClient) {
                    $tracedClient->setClient($client);
                    $tracedClient->setTracer($tracer);
                    return $tracedClient;
                }
            }
        }

        // Return standard client if tracing not available
        return $client;
    }
}
