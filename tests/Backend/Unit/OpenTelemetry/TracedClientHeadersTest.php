<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

uses(PHPUnit\Framework\TestCase::class);

/**
 * Build real spans and propagation without application configuration or exporters.
 *
 * @return array{Maho_OpenTelemetry_Model_Tracer, TracerProvider}
 */
function headerTestTracer(bool $enabled = true): array
{
    $provider = new TracerProvider(
        spanProcessors: [],
        sampler: new AlwaysOnSampler(),
        resource: ResourceInfo::create(Attributes::create([])),
    );
    $tracer = new Maho_OpenTelemetry_Model_Tracer();
    foreach ([
        '_enabled' => $enabled,
        '_rootStarted' => true,
        '_tracer' => $provider->getTracer('header-contract'),
        '_propagator' => TraceContextPropagator::getInstance(),
    ] as $property => $value) {
        new ReflectionProperty($tracer, $property)->setValue($tracer, $value);
    }

    return [$tracer, $provider];
}

it('propagates the client span after normalizing supported header representations', function (string $representation): void {
    [$tracer, $provider] = headerTestTracer();
    $propagator = TraceContextPropagator::getInstance();
    $old = '00-11111111111111111111111111111111-2222222222222222-01';
    $parentContext = $propagator->extract(['traceparent' => $old, 'tracestate' => 'owned=current'], context: Context::getRoot());
    $root = $provider->getTracer('header-contract')->spanBuilder('caller')->setParent($parentContext)->startSpan();
    $scope = $root->activate();
    $headers = [];
    foreach (['traceparent' => $old, 'tracestate' => 'owned=stale'] as $name => $value) {
        $title = ucfirst($name);
        $headers = match ($representation) {
            'empty' => $headers,
            'lowercase map' => [...$headers, $name => $value],
            'titlecase map' => [...$headers, $title => $value],
            'raw list' => [...$headers, "$title: $value"],
            'repeated raw list' => [...$headers, "$name: $value", "$title: $value"],
            'lowercase then titlecase' => [...$headers, $name => $value, $title => $value],
            'titlecase then lowercase' => [...$headers, $title => $value, $name => $value],
            'lowercase then raw' => [...$headers, $name => $value, "$title: $value"],
            'raw then lowercase' => [...$headers, "$title: $value", $name => $value],
        };
    }
    $headers['X-Custom'] = ['one', 'two'];
    $sent = [];
    $downstream = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
        $sent = ['method' => $method, 'url' => $url, 'options' => $options];
        return new MockResponse('synthetic', ['http_code' => 200]);
    });
    $client = new Maho_OpenTelemetry_Model_Http_TracedClient()->setClient($downstream)->setTracer($tracer);

    try {
        $response = $client->request('POST', 'https://receiver.invalid/proof', [
            'headers' => $headers,
            'body' => 'synthetic-body',
            'timeout' => 7,
            'user_data' => 'synthetic-option',
        ]);
        $wrapper = new ReflectionProperty($response, '_span')->getValue($response);
        $child = $wrapper->getSdkSpan();
        $expected = $tracer->getTracePropagationHeaders('receiver.invalid', $wrapper);
        $carrier = [];
        foreach ($sent['options']['headers'] as $line) {
            [$name, $value] = explode(':', $line, 2);
            $carrier[strtolower($name)][] = ltrim($value);
        }
        $receiver = Span::fromContext($propagator->extract($carrier, context: Context::getRoot()))->getContext();

        expect($carrier['traceparent'])->toBe([$expected['traceparent']])
            ->and($carrier['tracestate'])->toBe(['owned=current'])
            ->and($receiver->getSpanId())->toBe($child->getContext()->getSpanId())
            ->and($receiver->getTraceId())->toBe($child->getContext()->getTraceId())
            ->and($receiver->getTraceState()->__toString())->toBe('owned=current')
            ->and($child->getParentContext()->getSpanId())->toBe($root->getContext()->getSpanId())
            ->and(Span::getCurrent()->getContext()->getSpanId())->toBe($root->getContext()->getSpanId())
            ->and($sent['options']['normalized_headers']['x-custom'])->toBe(['X-Custom: one', 'X-Custom: two'])
            ->and($sent['options']['body'])->toBe('synthetic-body')
            ->and($sent['options']['timeout'])->toBe(7.0)
            ->and($sent['options']['user_data'])->toBe('synthetic-option')
            ->and($sent['method'])->toBe('POST')
            ->and($sent['url'])->toBe('https://receiver.invalid/proof')
            ->and($response->getContent())->toBe('synthetic');
    } finally {
        if (isset($response)) {
            $response->getContent();
        }
        $scope->detach();
        $root->end();
        $provider->shutdown();
    }
})->with([
    'empty', 'lowercase map', 'titlecase map', 'raw list', 'repeated raw list',
    'lowercase then titlecase', 'titlecase then lowercase', 'lowercase then raw', 'raw then lowercase',
]);

it('preserves normalized caller headers when there is no propagation context', function (): void {
    [$tracer, $provider] = headerTestTracer(enabled: false);
    $sent = [];
    $downstream = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
        $sent[] = $options['normalized_headers'];
        return new MockResponse('synthetic');
    });
    $options = ['headers' => [
        'traceparent' => 'caller-first',
        'Traceparent: caller-last',
        'X-Custom' => ['one', 'two'],
    ]];
    $client = new Maho_OpenTelemetry_Model_Http_TracedClient()->setClient($downstream)->setTracer($tracer);

    try {
        $downstream->request('GET', 'https://receiver.invalid/proof', $options)->getContent();
        $client->request('GET', 'https://receiver.invalid/proof', $options)->getContent();
        expect($sent[1])->toBe($sent[0]);
    } finally {
        $provider->shutdown();
    }
});

it('overrides inherited propagation headers while preserving unrelated client defaults', function (): void {
    [$tracer, $provider] = headerTestTracer();
    $root = $provider->getTracer('header-contract')->spanBuilder('caller')->startSpan();
    $scope = $root->activate();
    $sent = [];
    $downstream = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
        $sent = $options;
        return new MockResponse('synthetic');
    });
    $client = new Maho_OpenTelemetry_Model_Http_TracedClient()->setClient($downstream)->setTracer($tracer);
    $client = $client->withOptions(['headers' => ['Traceparent: inherited', 'X-Default: kept'], 'timeout' => 9]);

    try {
        $response = $client->request('GET', 'https://receiver.invalid/proof');
        $wrapper = new ReflectionProperty($response, '_span')->getValue($response);
        $expected = $tracer->getTracePropagationHeaders('receiver.invalid', $wrapper);
        expect($sent['normalized_headers']['traceparent'])->toBe(['traceparent: ' . $expected['traceparent']])
            ->and($sent['normalized_headers']['x-default'])->toBe(['X-Default: kept'])
            ->and($sent['timeout'])->toBe(9.0);
        $response->getContent();
    } finally {
        if (isset($response)) {
            $response->getContent();
        }
        $scope->detach();
        $root->end();
        $provider->shutdown();
    }
});
