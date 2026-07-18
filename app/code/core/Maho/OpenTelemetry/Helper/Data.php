<?php

/**
 * OpenTelemetry configuration helper resolving settings from standard OTEL_* environment variables with admin configuration as fallback.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

class Maho_OpenTelemetry_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Read an environment variable (empty string when unset)
     */
    public function getEnv(string $name): string
    {
        $value = $_SERVER[$name] ?? getenv($name);
        return is_string($value) ? trim($value) : '';
    }

    /**
     * Check if OpenTelemetry is enabled
     *
     * Precedence: OTEL_SDK_DISABLED=true wins, then the admin flag, then the
     * presence of a standard OTLP endpoint env var (12-factor deployments that
     * configure everything through the environment).
     */
    public function isEnabled(): bool
    {
        if (strtolower($this->getEnv('OTEL_SDK_DISABLED')) === 'true') {
            return false;
        }
        try {
            if (Mage::getStoreConfigFlag('dev/opentelemetry/enabled')) {
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }
        return $this->getEnv('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT') !== ''
            || $this->getEnv('OTEL_EXPORTER_OTLP_ENDPOINT') !== '';
    }

    /**
     * Get service name (OTEL_SERVICE_NAME overrides admin config)
     */
    public function getServiceName(): string
    {
        $env = $this->getEnv('OTEL_SERVICE_NAME');
        if ($env !== '') {
            return $env;
        }
        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/service_name') ?: 'maho-store';
        } catch (\Throwable $e) {
            return 'maho-store';
        }
    }

    /**
     * Get OTLP traces endpoint URL
     *
     * OTEL_EXPORTER_OTLP_TRACES_ENDPOINT is used verbatim;
     * OTEL_EXPORTER_OTLP_ENDPOINT gets /v1/traces appended per the OTLP spec.
     */
    public function getEndpoint(): string
    {
        $env = $this->getEnv('OTEL_EXPORTER_OTLP_TRACES_ENDPOINT');
        if ($env !== '') {
            return $env;
        }
        $env = $this->getEnv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if ($env !== '') {
            return rtrim($env, '/') . '/v1/traces';
        }
        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/endpoint');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Get sampling rate (0.0 to 1.0)
     *
     * Honors OTEL_TRACES_SAMPLER (always_on, always_off, traceidratio,
     * parentbased_*). OTEL_TRACES_SAMPLER_ARG is only consulted for the
     * traceidratio variants, defaulting to 1.0 per the spec when it is
     * missing or invalid; any other sampler falls back to admin config.
     */
    public function getSamplingRate(): float
    {
        $sampler = strtolower($this->getEnv('OTEL_TRACES_SAMPLER'));
        if (str_ends_with($sampler, 'always_on')) {
            return 1.0;
        }
        if (str_ends_with($sampler, 'always_off')) {
            return 0.0;
        }
        if (str_contains($sampler, 'traceidratio')) {
            $arg = $this->getEnv('OTEL_TRACES_SAMPLER_ARG');
            return $arg !== '' && is_numeric($arg) ? (float) $arg : 1.0;
        }
        try {
            $value = Mage::getStoreConfig('dev/opentelemetry/sampling_rate');
            return $value !== null && $value !== '' ? (float) $value : 0.1;
        } catch (\Throwable $e) {
            return 0.1; // Default 10% sampling
        }
    }

    /**
     * Parse OTLP headers from config and environment
     *
     * OTEL_EXPORTER_OTLP_HEADERS ("key=value,key2=value2", values may be
     * URL-encoded per the spec) overrides admin-configured headers per key.
     */
    public function getHeaders(): array
    {
        $headers = [];
        try {
            // Get authorization header
            $authHeader = Mage::getStoreConfig('dev/opentelemetry/auth_header');
            if ($authHeader) {
                $headers['Authorization'] = $authHeader;
            }

            // Get custom headers (format: "Key: Value" one per line)
            $customHeaders = Mage::getStoreConfig('dev/opentelemetry/custom_headers');
            if ($customHeaders) {
                foreach (explode("\n", $customHeaders) as $line) {
                    $line = trim($line);
                    if ($line && str_contains($line, ':')) {
                        [$key, $value] = explode(':', $line, 2);
                        $headers[trim($key)] = trim($value);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Config not available
        }

        $envHeaders = $this->getEnv('OTEL_EXPORTER_OTLP_HEADERS');
        if ($envHeaders !== '') {
            foreach (explode(',', $envHeaders) as $pair) {
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    $headers[trim($key)] = rawurldecode(trim($value));
                }
            }
        }

        return $headers;
    }

    /**
     * Whether BLOCK: profiler timers should create spans (they are the highest
     * volume span source; disable to reduce trace size on complex pages)
     */
    public function isBlockTracingEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/trace_blocks');
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Check if a request path is excluded from tracing
     *
     * Patterns come one per line from config; a pattern containing * or ? is
     * matched with fnmatch(), otherwise it's a path prefix.
     */
    public function isPathExcluded(string $path): bool
    {
        try {
            $config = (string) Mage::getStoreConfig('dev/opentelemetry/excluded_paths');
        } catch (\Throwable $e) {
            return false;
        }
        if ($config === '') {
            return false;
        }
        foreach (explode("\n", $config) as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            if (strpbrk($pattern, '*?') !== false) {
                if (fnmatch($pattern, $path)) {
                    return true;
                }
            } elseif (str_starts_with($path, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
