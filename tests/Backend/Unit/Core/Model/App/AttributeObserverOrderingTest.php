<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

/**
 * Build the observer list for a given area/event without actually dispatching.
 */
function resolveObservers(string $area, string $eventName): array
{
    $app = Mage::app();
    $app->addEventArea($area);

    $ref = new ReflectionProperty($app, '_events');
    $events = $ref->getValue($app);
    unset($events[$area][$eventName]);
    $ref->setValue($app, $events);

    $app->dispatchEvent($eventName, []);

    $events = $ref->getValue($app);
    return $events[$area][$eventName]['observers'] ?? [];
}

/**
 * Build module position map from config.
 */
function getModulePositions(): array
{
    $positions = [];
    $pos = 0;
    foreach (Mage::getConfig()->getNode('modules')->children() as $name => $mod) {
        $positions[(string) $name] = $pos++;
    }
    return $positions;
}

/**
 * Resolve the module name for an observer from its model field.
 */
function getObserverModule(string $model): ?string
{
    // Attribute observer: FQCN like Mage_Wishlist_Model_Observer
    if (!str_contains($model, '/')) {
        if (preg_match('/^(Mage_[A-Za-z]+)_/', $model, $m)) {
            return $m[1];
        }
        return null;
    }

    // XML observer: alias like wishlist/observer
    $group = explode('/', $model)[0];
    $classPrefix = (string) Mage::getConfig()->getNode("global/models/{$group}/class");
    if ($classPrefix && preg_match('/^(.+)_[^_]+$/', $classPrefix, $m)) {
        return $m[1];
    }
    return null;
}

it('interleaves XML and attribute observers in module dependency order', function () {
    // The 'customer_logout' event has observers from both XML and attribute sources.
    // Verify they are ordered by module dependency, not by source.

    $observers = resolveObservers('frontend', 'customer_logout');
    $modulePositions = getModulePositions();

    // Extract the module position for each observer in execution order
    $observerModulePositions = [];
    foreach ($observers as $name => $obs) {
        $module = getObserverModule($obs['model']);
        if ($module !== null && isset($modulePositions[$module])) {
            $observerModulePositions[$name] = $modulePositions[$module];
        }
    }

    // Verify the sequence is non-decreasing (sorted by module dependency)
    $positions = array_values($observerModulePositions);
    for ($i = 1; $i < count($positions); $i++) {
        expect($positions[$i])->toBeGreaterThanOrEqual(
            $positions[$i - 1],
            'Observers should be ordered by module dependency',
        );
    }

    // Verify we have at least one attribute-based and one XML-based observer
    $hasAttribute = false;
    $hasXml = false;
    foreach ($observers as $name => $obs) {
        if (str_contains($obs['model'], '/')) {
            $hasXml = true;
        } else {
            $hasAttribute = true;
        }
    }
    expect($hasAttribute)->toBeTrue('Should have at least one attribute observer');
    expect($hasXml)->toBeTrue('Should have at least one XML observer');
});

it('places attribute observers from same module alongside XML observers', function () {
    $observers = resolveObservers('global', 'customer_save_after');

    expect($observers)->toHaveKey('Mage_Newsletter_Model_Observer::subscribeCustomer');
});

it('compiled attributes file contains module field for all observers', function () {
    $compiled = Maho::getCompiledAttributes();

    foreach ($compiled['observers'] as $events) {
        foreach ($events as $observers) {
            foreach ($observers as $observer) {
                expect($observer)->toHaveKey('module');
                expect($observer['module'])->not->toBeEmpty();
            }
        }
    }
});
