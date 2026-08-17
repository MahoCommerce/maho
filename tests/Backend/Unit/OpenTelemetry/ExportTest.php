<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

uses(Tests\MahoBackendTestCase::class);

/**
 * The export path with the SDK actually installed: spans built by the real
 * tracer, serialized by the OTLP exporter, captured off the transport. Asserts
 * on the payload that would leave the server rather than on our own wrappers.
 */

/**
 * Stands in for the OTLP HTTP transport, keeping every payload the exporter sends.
 * Declaring JSON makes the exporter serialize OTLP/JSON, which the assertions read
 * directly instead of decoding protobuf.
 */
final class MahoTestOtlpTransport implements TransportInterface
{
    /** @var list<string> */
    public array $payloads = [];

    #[\Override]
    public function contentType(): string
    {
        return 'application/json';
    }

    #[\Override]
    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $this->payloads[] = $payload;
        return new CompletedFuture(null);
    }

    #[\Override]
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    #[\Override]
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }
}

final class MahoTestTracer extends Maho_OpenTelemetry_Model_Tracer
{
    public MahoTestOtlpTransport $transport;

    public function __construct()
    {
        $this->transport = new MahoTestOtlpTransport();
    }

    #[\Override]
    protected function _createTransport(string $endpoint, string $signal): TransportInterface
    {
        return $this->transport;
    }
}

afterEach(function () {
    foreach (array_keys($_SERVER) as $key) {
        if (str_starts_with((string) $key, 'OTEL_')) {
            unset($_SERVER[$key]);
        }
    }
    Mage::app()->getStore()->setConfig('dev/opentelemetry/sampling_rate', '0.1');
});

/**
 * A tracer wired to the SDK and sampling everything, so assertions never race
 * the sampler
 */
function otelExportTracer(): MahoTestTracer
{
    $_SERVER['OTEL_EXPORTER_OTLP_ENDPOINT'] = 'http://collector.invalid:4318';
    Mage::app()->getStore()->setConfig('dev/opentelemetry/sampling_rate', '1');

    $tracer = new MahoTestTracer();
    expect($tracer->initialize())->not->toBeFalse();

    return $tracer;
}

/**
 * Every span in an OTLP/JSON payload, flattened
 *
 * @return list<array<string, mixed>>
 */
function otelSpansIn(string $payload): array
{
    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

    $spans = [];
    foreach ($decoded['resourceSpans'] ?? [] as $resourceSpans) {
        foreach ($resourceSpans['scopeSpans'] ?? [] as $scopeSpans) {
            foreach ($scopeSpans['spans'] ?? [] as $span) {
                $spans[] = $span;
            }
        }
    }

    return $spans;
}

/**
 * @param list<array<string, mixed>> $spans
 * @return array<string, mixed>
 */
function otelSpanNamed(array $spans, string $name): array
{
    foreach ($spans as $span) {
        if (($span['name'] ?? null) === $name) {
            return $span;
        }
    }

    throw new RuntimeException("No exported span named \"$name\" (got: "
        . implode(', ', array_column($spans, 'name')) . ')');
}

/**
 * OTLP attributes as a plain map. Scalars arrive wrapped in a one-key type
 * envelope, and integers as strings, so callers cast what they compare.
 *
 * @param array<string, mixed> $span
 * @return array<string, mixed>
 */
function otelAttributesOf(array $span): array
{
    $attributes = [];
    foreach ($span['attributes'] ?? [] as $attribute) {
        $attributes[$attribute['key']] = array_values($attribute['value'])[0] ?? null;
    }

    return $attributes;
}

it('holds spans until flush, then serializes the hierarchy into one OTLP payload', function () {
    $tracer = otelExportTracer();

    $root = $tracer->startRootSpan('POST', ['http.request.method' => 'POST'], 'server');
    $query = $tracer->startSpan('SELECT catalog_product_entity', ['db.system.name' => 'mysql'], 'client');
    $query->end();
    $root->end();

    // Deferred by the batch processor: nothing may reach the collector mid-request
    expect($tracer->transport->payloads)->toBeEmpty();

    $tracer->flush();

    expect($tracer->transport->payloads)->toHaveCount(1);
    $spans = otelSpansIn($tracer->transport->payloads[0]);
    expect($spans)->toHaveCount(2);

    $rootSpan = otelSpanNamed($spans, 'POST');
    $querySpan = otelSpanNamed($spans, 'SELECT catalog_product_entity');

    expect($rootSpan['parentSpanId'] ?? '')->toBe('')
        ->and($querySpan['traceId'])->toBe($rootSpan['traceId'])
        ->and($querySpan['parentSpanId'])->toBe($rootSpan['spanId'])
        ->and(otelAttributesOf($querySpan)['db.system.name'])->toBe('mysql');
});

it('continues the dispatching trace on the queue consumer span', function () {
    $tracer = otelExportTracer();

    $request = $tracer->startRootSpan('POST', [], 'server');
    $carrier = $tracer->getTracePropagationHeaders();
    expect($carrier)->toHaveKey('traceparent')
        ->and($carrier['traceparent'])->toContain($request->getTraceId())
        ->and($carrier['traceparent'])->toContain($request->getSpanId());
    $request->end();

    // What TraceMessageListener hands back from the stamp QueueManager wrote
    $consumer = $tracer->startRootSpan('process My_Module_Model_Message', [], 'consumer', $carrier);
    expect($consumer->getTraceId())->toBe($request->getTraceId());
    $consumer->end();

    $tracer->flush();
    $spans = otelSpansIn($tracer->transport->payloads[0]);

    expect(otelSpanNamed($spans, 'process My_Module_Model_Message')['parentSpanId'])
        ->toBe($request->getSpanId());
});

it('spans the outgoing HTTP request until its body is consumed', function () {
    $tracer = otelExportTracer();
    $root = $tracer->startRootSpan('POST', [], 'server');

    $client = new Maho_OpenTelemetry_Model_Http_TracedClient();
    $client->setClient(new MockHttpClient(new MockResponse('{"ok":true}', ['http_code' => 201])));
    $client->setTracer($tracer);

    $response = $client->request('GET', 'https://api.example.com/orders?token=secret#frag');

    // Work done while the response is in flight belongs to the caller, so it must
    // stay a sibling of the HTTP span rather than be adopted by it
    $tracer->startSpan('SELECT sales_order', [], 'client')->end();

    expect($response->toArray())->toBe(['ok' => true]);

    $root->end();
    $tracer->flush();
    $spans = otelSpansIn($tracer->transport->payloads[0]);

    $rootSpan = otelSpanNamed($spans, 'POST');
    $httpSpan = otelSpanNamed($spans, 'GET');
    $attributes = otelAttributesOf($httpSpan);

    expect($httpSpan['parentSpanId'])->toBe($rootSpan['spanId'])
        ->and(otelSpanNamed($spans, 'SELECT sales_order')['parentSpanId'])->toBe($rootSpan['spanId'])
        ->and($attributes['url.full'])->toBe('https://api.example.com/orders')
        ->and((int) $attributes['http.response.status_code'])->toBe(201)
        ->and($httpSpan['status']['code'] ?? null)->toBe(StatusCode::STATUS_CODE_OK);
});

it('records a failure raised while the response body is read', function () {
    $tracer = otelExportTracer();
    $root = $tracer->startRootSpan('POST', [], 'server');

    $client = new Maho_OpenTelemetry_Model_Http_TracedClient();
    $client->setClient(new MockHttpClient(new MockResponse('gone', ['http_code' => 404])));
    $client->setTracer($tracer);

    // Symfony defers 4xx/5xx to the moment the body is read, which used to be
    // after the span had already been closed as a success
    $response = $client->request('GET', 'https://api.example.com/missing');
    expect(fn() => $response->getContent())->toThrow(ClientException::class);

    $root->end();
    $tracer->flush();

    $httpSpan = otelSpanNamed(otelSpansIn($tracer->transport->payloads[0]), 'GET');

    expect($httpSpan['status']['code'] ?? null)->toBe(StatusCode::STATUS_CODE_ERROR)
        ->and(otelAttributesOf($httpSpan)['error.type'])->toBe(ClientException::class)
        ->and(array_column($httpSpan['events'] ?? [], 'name'))->toContain('exception');
});

it('exports the span of a response nobody consumed', function () {
    $tracer = otelExportTracer();
    $root = $tracer->startRootSpan('POST', [], 'server');

    $client = new Maho_OpenTelemetry_Model_Http_TracedClient();
    $client->setClient(new MockHttpClient(new MockResponse('never read')));
    $client->setTracer($tracer);
    $client->request('GET', 'https://api.example.com/fire-and-forget');

    $root->end();
    $tracer->flush();

    expect(array_column(otelSpansIn($tracer->transport->payloads[0]), 'name'))->toContain('GET');
});

it('propagates trace context to the outgoing request headers', function () {
    $tracer = otelExportTracer();
    $root = $tracer->startRootSpan('POST', [], 'server');

    $sent = [];
    $client = new Maho_OpenTelemetry_Model_Http_TracedClient();
    $client->setClient(new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
        $sent = $options['headers'] ?? [];
        return new MockResponse('{}');
    }));
    $client->setTracer($tracer);

    $client->request('GET', 'https://api.example.com/orders')->getContent();

    $traceparent = array_values(array_filter(
        $sent,
        static fn(string $header): bool => str_starts_with(strtolower($header), 'traceparent:'),
    ));

    $root->end();
    $tracer->flush();
    $httpSpan = otelSpanNamed(otelSpansIn($tracer->transport->payloads[0]), 'GET');

    // The receiver parents to the client span, not to the request that issued it
    expect($traceparent)->toHaveCount(1)
        ->and($traceparent[0])->toBe("traceparent: 00-{$httpSpan['traceId']}-{$httpSpan['spanId']}-01");
});
