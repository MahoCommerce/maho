<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * An event area can exist without a matching top-level config node. The "crontab" area is the
 * one in core: every cron observer is a PHP attribute, so no module declares <crontab> any more.
 * getEventConfig() must report "no XML events" for such an area instead of reading a property
 * on false. See https://github.com/MahoCommerce/maho/issues/1402
 */

/** Collect every PHP diagnostic that a callback raises. */
function configEventAreaDiagnostics(callable $callback): array
{
    $messages = [];
    set_error_handler(function (int $errno, string $errstr) use (&$messages): bool {
        $messages[] = $errstr;
        return true;
    });
    try {
        $callback();
    } finally {
        restore_error_handler();
    }
    return $messages;
}

it('raises no warning for the crontab area, which has no config node', function () {
    $config = Mage::getConfig();

    $messages = configEventAreaDiagnostics(function () use ($config) {
        $config->getEventConfig('crontab', 'model_save_after');
    });

    expect($messages)->toBe([]);
});

it('returns null for an area that has no config node, and caches the miss', function () {
    $config = Mage::getConfig();

    $messages = configEventAreaDiagnostics(function () use ($config) {
        expect($config->getEventConfig('maho_area_without_node', 'first_event'))->toBeNull();
        expect($config->getEventConfig('maho_area_without_node', 'second_event'))->toBeNull();
        expect($config->getEventConfig('maho_area_without_node', 'first_event'))->toBeNull();
    });

    expect($messages)->toBe([]);
});

it('dispatches an event in the crontab area without a warning', function () {
    Mage::app()->addEventArea('crontab');

    $messages = configEventAreaDiagnostics(function () {
        Mage::dispatchEvent('maho_test_event_1402', []);
    });

    expect($messages)->toBe([]);
});

it('still finds the attribute observers of the crontab area', function () {
    expect(Maho::getCompiledAttributes()['observers']['crontab']['always'] ?? [])->not->toBeEmpty();
    expect(Maho::getCompiledAttributes()['observers']['crontab']['default'] ?? [])->not->toBeEmpty();
});

it('still reads the XML events of an area that declares them', function () {
    $config = Mage::getConfig();
    $config->setNode('maho_test_area_1402/events/maho_test_event_1402/observers/probe/class', 'Maho_Probe');
    $config->setNode('maho_test_area_1402/events/maho_test_event_1402/observers/probe/method', 'probe');

    $eventConfig = $config->getEventConfig('maho_test_area_1402', 'maho_test_event_1402');

    expect($eventConfig)->not->toBeNull();
    expect((string) $eventConfig->observers->probe->class)->toBe('Maho_Probe');
});
