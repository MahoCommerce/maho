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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
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
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * OTLP wire protocol for one signal: http/protobuf (default), http/json,
     * http/ndjson or grpc. Environment only, as in every OpenTelemetry SDK.
     */
    public function getProtocol(string $signal): string
    {
        $env = $this->getEnv('OTEL_EXPORTER_OTLP_' . strtoupper($signal) . '_PROTOCOL')
            ?: $this->getEnv('OTEL_EXPORTER_OTLP_PROTOCOL');

        return $env !== '' ? strtolower($env) : 'http/protobuf';
    }

    /**
     * Admin sampling rate (0.0 to 1.0). OTEL_TRACES_SAMPLER takes precedence
     * and is resolved by the SDK in Tracer::_createSampler().
     */
    public function getSamplingRate(): float
    {
        try {
            $value = Mage::getStoreConfig('dev/opentelemetry/sampling_rate');
            return $value !== null && $value !== '' ? (float) $value : 0.1;
        } catch (\Throwable) {
            return 0.1; // Default 10% sampling
        }
    }

    /**
     * Admin-configured OTLP headers. OTEL_EXPORTER_OTLP_HEADERS is merged over
     * these in Tracer::_resolveHeaders().
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
        } catch (\Throwable) {
            // Config not available
        }

        return $headers;
    }

    /**
     * Whether to trust incoming W3C traceparent/tracestate headers and continue
     * the caller's trace instead of starting a new one. Default off: honoring
     * attacker-supplied trace ids can pollute sampling decisions, so this should
     * only be enabled behind a trusted proxy/gateway.
     */
    public function isTrustIncomingTracesEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/trust_incoming_traces');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether to emit a Server-Timing response header carrying the traceparent,
     * so browser RUM tooling can correlate page loads with backend traces
     */
    public function isServerTimingEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/server_timing');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether log records should also be exported to the OTLP endpoint
     * (OTEL_LOGS_EXPORTER=otlp|none overrides the admin flag)
     */
    public function isLogExportEnabled(): bool
    {
        $env = strtolower($this->getEnv('OTEL_LOGS_EXPORTER'));
        if ($env === 'otlp') {
            return true;
        }
        if ($env === 'none') {
            return false;
        }
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/export_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether metrics should be exported to the OTLP endpoint
     * (OTEL_METRICS_EXPORTER=otlp|none overrides the admin flag)
     */
    public function isMetricsExportEnabled(): bool
    {
        $env = strtolower($this->getEnv('OTEL_METRICS_EXPORTER'));
        if ($env === 'otlp') {
            return true;
        }
        if ($env === 'none') {
            return false;
        }
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/export_metrics');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get OTLP logs endpoint URL, derived from the traces endpoint unless the
     * standard signal-specific env vars say otherwise
     */
    public function getLogsEndpoint(): string
    {
        return $this->_getSignalEndpoint('OTEL_EXPORTER_OTLP_LOGS_ENDPOINT', 'logs');
    }

    /**
     * Get OTLP metrics endpoint URL, derived from the traces endpoint unless the
     * standard signal-specific env vars say otherwise
     */
    public function getMetricsEndpoint(): string
    {
        return $this->_getSignalEndpoint('OTEL_EXPORTER_OTLP_METRICS_ENDPOINT', 'metrics');
    }

    /**
     * Resolve a per-signal OTLP endpoint: signal env var verbatim, base env var
     * with the signal path appended, or the traces endpoint with its signal
     * segment swapped
     */
    private function _getSignalEndpoint(string $envVar, string $signal): string
    {
        $env = $this->getEnv($envVar);
        if ($env !== '') {
            return $env;
        }
        $env = $this->getEnv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if ($env !== '') {
            return rtrim($env, '/') . '/v1/' . $signal;
        }
        $traces = $this->getEndpoint();
        if ($traces === '') {
            return '';
        }
        if (str_contains($traces, '/v1/traces')) {
            return str_replace('/v1/traces', '/v1/' . $signal, $traces);
        }
        return rtrim($traces, '/') . '/v1/' . $signal;
    }

    /**
     * Whether BLOCK: profiler timers should create spans (they are the highest
     * volume span source; disable to reduce trace size on complex pages)
     */
    public function isBlockTracingEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/trace_blocks');
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Whether cache. profiler timers should create spans. Off by default:
     * cache reads are the highest volume operation of all, and the cache key
     * is high cardinality.
     */
    public function isCacheTracingEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/trace_cache');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Whether query spans carry the statement as executed. Maho inlines values
     * with quoteInto(), so the statement exports them too.
     */
    public function isQueryTextEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/query_text');
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Deployment environment, exported as deployment.environment.name.
     * Environment-driven installs use OTEL_RESOURCE_ATTRIBUTES instead.
     */
    public function getDeploymentEnvironment(): string
    {
        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/environment');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Hosts allowed to receive the W3C baggage header, lowercased, one per line
     *
     * @return list<string>
     */
    public function getBaggageHosts(): array
    {
        try {
            $config = (string) Mage::getStoreConfig('dev/opentelemetry/baggage_hosts');
        } catch (\Throwable) {
            return [];
        }
        $hosts = [];
        foreach (explode("\n", $config) as $host) {
            $host = strtolower(trim($host));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }
        return $hosts;
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
        } catch (\Throwable) {
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
