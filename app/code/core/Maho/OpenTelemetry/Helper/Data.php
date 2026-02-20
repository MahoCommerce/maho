<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * OpenTelemetry helper
 */
class Maho_OpenTelemetry_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Check if OpenTelemetry is enabled
     */
    public function isEnabled(): bool
    {
        try {
            return Mage::getStoreConfigFlag('dev/opentelemetry/enabled');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get service name
     */
    public function getServiceName(): string
    {
        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/service_name') ?: 'maho-store';
        } catch (\Throwable $e) {
            return 'maho-store';
        }
    }

    /**
     * Get OTLP endpoint URL
     */
    public function getEndpoint(): string
    {
        try {
            return (string) Mage::getStoreConfig('dev/opentelemetry/endpoint');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Get sampling rate (0.0 to 1.0)
     */
    public function getSamplingRate(): float
    {
        try {
            $value = Mage::getStoreConfig('dev/opentelemetry/sampling_rate');
            return $value !== null && $value !== '' ? (float) $value : 0.1;
        } catch (\Throwable $e) {
            return 0.1; // Default 10% sampling
        }
    }

    /**
     * Parse OTLP headers from config
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

        return $headers;
    }
}
