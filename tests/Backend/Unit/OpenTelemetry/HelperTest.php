<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Configuration resolution: OTEL_* environment variables must override admin
 * config with the precedence documented in the helper, and path exclusion
 * must support both prefix and wildcard patterns.
 */

afterEach(function () {
    foreach (array_keys($_SERVER) as $key) {
        if (str_starts_with((string) $key, 'OTEL_')) {
            unset($_SERVER[$key]);
        }
    }
    $store = Mage::app()->getStore();
    $store->setConfig('dev/opentelemetry/sampling_rate', '0.1');
    $store->setConfig('dev/opentelemetry/custom_headers', '');
    $store->setConfig('dev/opentelemetry/excluded_paths', '');
});

function otelHelper(): Maho_OpenTelemetry_Helper_Data
{
    return Mage::helper('opentelemetry');
}

it('is disabled by default', function () {
    expect(otelHelper()->isEnabled())->toBeFalse();
});

it('is enabled by the standard endpoint env var and killed by OTEL_SDK_DISABLED', function () {
    $_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://localhost:4318';
    expect(otelHelper()->isEnabled())->toBeTrue();

    $_SERVER['OTEL_SDK_DISABLED'] = 'true';
    expect(otelHelper()->isEnabled())->toBeFalse();
});

it('prefers OTEL_SERVICE_NAME over admin config', function () {
    expect(otelHelper()->getServiceName())->toBe('maho-store');

    $_SERVER['OTEL_SERVICE_NAME'] = 'my-shop';
    expect(otelHelper()->getServiceName())->toBe('my-shop');
});

it('derives per-signal endpoints from the base env var', function () {
    $_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://collector:4318/';
    expect(otelHelper()->getEndpoint())->toBe('http://collector:4318/v1/traces')
        ->and(otelHelper()->getLogsEndpoint())->toBe('http://collector:4318/v1/logs')
        ->and(otelHelper()->getMetricsEndpoint())->toBe('http://collector:4318/v1/metrics');
});

it('uses signal-specific endpoint env vars verbatim', function () {
    $_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://collector:4318';
    $_SERVER['OTEL_EXPORTER_OTLP_TRACES_ENDPOINT'] = 'https://elsewhere.example/custom';
    expect(otelHelper()->getEndpoint())->toBe('https://elsewhere.example/custom')
        ->and(otelHelper()->getLogsEndpoint())->toBe('http://collector:4318/v1/logs');
});

it('takes the sampling rate from admin config and leaves the sampler env vars to the SDK', function () {
    // OTEL_TRACES_SAMPLER* is resolved by the SDK in Tracer::_createSampler(), not here
    $_SERVER['OTEL_TRACES_SAMPLER'] = 'traceidratio';
    $_SERVER['OTEL_TRACES_SAMPLER_ARG'] = '1';
    expect(otelHelper()->getSamplingRate())->toBe(0.1);

    Mage::app()->getStore()->setConfig('dev/opentelemetry/sampling_rate', '0.25');
    expect(otelHelper()->getSamplingRate())->toBe(0.25);
});

it('parses admin custom headers one per line', function () {
    // OTEL_EXPORTER_OTLP_HEADERS is merged over these in Tracer::_resolveHeaders()
    Mage::app()->getStore()->setConfig(
        'dev/opentelemetry/custom_headers',
        "x-api-key: abc def\n\nno colon here\n x-tenant : shop1 ",
    );

    expect(otelHelper()->getHeaders())->toBe([
        'x-api-key' => 'abc def',
        'x-tenant' => 'shop1',
    ]);
});

it('excludes paths by prefix and by wildcard', function () {
    $helper = otelHelper();
    Mage::app()->getStore()->setConfig('dev/opentelemetry/excluded_paths', "/health\n/media/*\n/api/?/ping");

    expect($helper->isPathExcluded('/health'))->toBeTrue()
        ->and($helper->isPathExcluded('/healthz'))->toBeTrue() // prefix match
        ->and($helper->isPathExcluded('/media/catalog/x.jpg'))->toBeTrue()
        ->and($helper->isPathExcluded('/api/1/ping'))->toBeTrue()
        ->and($helper->isPathExcluded('/checkout/cart'))->toBeFalse()
        ->and($helper->isPathExcluded(''))->toBeFalse();
});

it('gates log and metric export behind env overrides', function () {
    expect(otelHelper()->isLogExportEnabled())->toBeFalse()
        ->and(otelHelper()->isMetricsExportEnabled())->toBeFalse();

    $_SERVER['OTEL_LOGS_EXPORTER'] = 'otlp';
    $_SERVER['OTEL_METRICS_EXPORTER'] = 'otlp';
    expect(otelHelper()->isLogExportEnabled())->toBeTrue()
        ->and(otelHelper()->isMetricsExportEnabled())->toBeTrue();

    $_SERVER['OTEL_LOGS_EXPORTER'] = 'none';
    $_SERVER['OTEL_METRICS_EXPORTER'] = 'none';
    expect(otelHelper()->isLogExportEnabled())->toBeFalse()
        ->and(otelHelper()->isMetricsExportEnabled())->toBeFalse();
});
