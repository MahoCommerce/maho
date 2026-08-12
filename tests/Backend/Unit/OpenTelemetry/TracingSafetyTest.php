<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Safety invariants that hold with tracing disabled (the default): the
 * null-object span never throws, tracer accessors degrade to no-ops, the
 * profiler and commerce observers run cleanly, and the DB adapter never
 * leaks bind values into the span SQL text.
 */

it('returns no tracer when the module is disabled', function () {
    expect(Mage::getTracer())->toBeNull()
        ->and(Mage::startSpan('test'))->toBeNull();
});

it('null span is safe for every operation', function () {
    $span = new Maho_OpenTelemetry_Model_Span();

    $span->setAttribute('key', 'value')
        ->setAttributes(['a' => 1])
        ->updateName('renamed')
        ->addEvent('event', ['x' => 1])
        ->recordException(new Exception('boom'))
        ->setStatus('error', 'desc');
    $span->end();
    $span->end(); // double end must be a no-op

    expect($span->getTraceId())->toBe('')
        ->and($span->getSpanId())->toBe('')
        ->and($span->isRecording())->toBeFalse()
        ->and($span->getSdkSpan())->toBeNull();
});

it('uninitialized tracer degrades to no-ops', function () {
    $tracer = new Maho_OpenTelemetry_Model_Tracer();

    expect($tracer->isEnabled())->toBeFalse()
        ->and($tracer->getActiveSpan())->toBeNull()
        ->and($tracer->getRootSpan())->toBeNull()
        ->and($tracer->getTracePropagationHeaders())->toBe([])
        ->and($tracer->getLoggerProvider())->toBeNull()
        ->and($tracer->startSpan('x')->isRecording())->toBeFalse()
        ->and($tracer->startRootSpan('x')->isRecording())->toBeFalse();

    // All of these must be silent no-ops
    $tracer->recordException(new Exception('boom'));
    $tracer->recordRequestDuration(0.5, ['http.request.method' => 'GET']);
    $tracer->addCounter('maho.orders');
    $tracer->flush();
});

it('profiler timers run cleanly without a tracer', function () {
    \Maho\Profiler::start('BLOCK:test.block');
    \Maho\Profiler::start('BLOCK:test.block'); // re-entrant same name
    \Maho\Profiler::stop('BLOCK:test.block');
    \Maho\Profiler::stop('BLOCK:test.block');
    \Maho\Profiler::start('cron.job.execute', ['cron.job_code' => 'test']);
    \Maho\Profiler::stop('cron.job.execute');

    expect(true)->toBeTrue(); // reaching here without exceptions is the assertion
});

it('commerce observers are no-ops without a tracer', function () {
    $observer = new Maho_OpenTelemetry_Model_Observer();

    $order = Mage::getModel('sales/order');
    $event = new \Maho\Event(['order' => $order, 'order_ids' => [1]]);
    $wrapper = new \Maho\Event\Observer(['event' => $event]);

    $observer->addOrderPlacedEvent($wrapper);
    $observer->addCartAddEvent($wrapper);
    $observer->addCheckoutSuccessEvent($wrapper);
    $observer->addCustomerLoginEvent($wrapper);

    expect(true)->toBeTrue();
});

it('builds no query span while tracing is off', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');

    $method = new ReflectionMethod($adapter, '_startQuerySpan');
    $span = $method->invoke($adapter, 'SELECT * FROM admin_user WHERE password = ?', ['s3cret']);
    expect($span)->toBeNull();

    $op = new ReflectionMethod($adapter, '_getOperationType');
    expect($op->invoke($adapter, 'SELECT * FROM foo'))->toBe('SELECT')
        ->and($op->invoke($adapter, '  update foo set x=1'))->toBe('UPDATE');

    $table = new ReflectionMethod($adapter, '_getTargetTable');
    expect($table->invoke($adapter, 'SELECT * FROM `catalog_product` WHERE 1'))->toBe('catalog_product')
        ->and($table->invoke($adapter, 'INSERT INTO sales_order VALUES (1)'))->toBe('sales_order')
        ->and($table->invoke($adapter, 'UPDATE "core_config_data" SET value = 1'))->toBe('core_config_data')
        ->and($table->invoke($adapter, 'SELECT * FROM "public"."core_config_data"'))->toBe('core_config_data')
        ->and($table->invoke($adapter, 'SHOW TABLES'))->toBe('');
});

it('creates no span before a root span exists', function () {
    $tracer = new Maho_OpenTelemetry_Model_Tracer();

    expect($tracer->isRecording())->toBeFalse()
        ->and($tracer->startSpan('orphan')->isRecording())->toBeFalse();
});

it('profiler passes lazy attributes only when a span is created', function () {
    $called = false;
    \Maho\Profiler::start('BLOCK:lazy', function () use (&$called): array {
        $called = true;
        return [];
    });
    \Maho\Profiler::stop('BLOCK:lazy');

    expect($called)->toBeFalse();
});
