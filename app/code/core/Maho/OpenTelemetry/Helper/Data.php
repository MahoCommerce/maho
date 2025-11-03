<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * OpenTelemetry helper
 */
class Maho_OpenTelemetry_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Check if OpenTelemetry is enabled
     *
     * @param mixed $store
     */
    public function isEnabled($store = null): bool
    {
        // Environment variable takes precedence
        $envDisabled = getenv('OTEL_SDK_DISABLED');
        if ($envDisabled !== false) {
            // ENV var is set, use it (false string means enabled, true string means disabled)
            return $envDisabled !== 'true';
        }

        // Fall back to config
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/enabled', $store);
        } catch (\Throwable $e) {
            // If store config fails (e.g., no store initialized), return false
            return false;
        }
    }

    /**
     * Get service name
     *
     * @param mixed $store
     */
    public function getServiceName($store = null): string
    {
        // Environment variable takes precedence
        $envServiceName = getenv('OTEL_SERVICE_NAME');
        if ($envServiceName !== false && $envServiceName !== '') {
            return $envServiceName;
        }

        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/service_name', $store) ?: 'maho-store';
        } catch (\Throwable $e) {
            return 'maho-store';
        }
    }

    /**
     * Get OTLP endpoint URL
     *
     * @param mixed $store
     */
    public function getEndpoint($store = null): string
    {
        // Environment variable takes precedence
        $envEndpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT');
        if ($envEndpoint !== false && $envEndpoint !== '') {
            return $envEndpoint;
        }

        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/endpoint', $store);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Get sampling rate (0.0 to 1.0)
     *
     * @param mixed $store
     */
    public function getSamplingRate($store = null): float
    {
        // Environment variable takes precedence
        $envSamplingRate = getenv('OTEL_TRACES_SAMPLER_ARG');
        if ($envSamplingRate !== false && $envSamplingRate !== '') {
            return (float) $envSamplingRate;
        }

        return (float) Mage::getStoreConfig('dev/opentelemetry/sampling_rate', $store) ?: 0.1;
    }

    /**
     * Check if log export is enabled
     *
     * @param mixed $store
     */
    public function isLogExportEnabled($store = null): bool
    {
        return Mage::getStoreConfigFlag('dev/opentelemetry/export_logs', $store);
    }

    /**
     * Parse OTLP headers from environment or config
     */
    public function getHeaders(): array
    {
        // Support standard OTEL_EXPORTER_OTLP_HEADERS env var
        // Format: "key1=value1,key2=value2"
        $envHeaders = getenv('OTEL_EXPORTER_OTLP_HEADERS');
        if ($envHeaders !== false && $envHeaders !== '') {
            $parsed = [];
            foreach (explode(',', $envHeaders) as $pair) {
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    $parsed[trim($key)] = trim($value);
                }
            }
            return $parsed;
        }

        // Fallback to XML config
        $config = Mage::getConfig();
        if ($config && $xmlHeaders = $config->getNode('global/opentelemetry/headers')) {
            $parsed = [];
            foreach ($xmlHeaders->children() as $key => $value) {
                $parsed[$key] = (string) $value;
            }
            return $parsed;
        }

        return [];
    }
}
