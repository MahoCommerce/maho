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
 * Resolve the module name for an observer from its model alias.
 */
function getObserverModule(string $model): ?string
{
    if (!str_contains($model, '/')) {
        return null;
    }

    $group = explode('/', $model)[0];
    $classPrefix = (string) Mage::getConfig()->getNode("global/models/{$group}/class");
    if ($classPrefix && preg_match('/^(.+)_[^_]+$/', $classPrefix, $m)) {
        return $m[1];
    }
    return null;
}

it('interleaves XML and attribute observers in module dependency order', function () {
    $observers = resolveObservers('frontend', 'customer_logout');

    $positions = [];
    $pos = 0;
    foreach (Mage::getConfig()->getNode('modules')->children() as $name => $mod) {
        $positions[(string) $name] = $pos++;
    }

    // Extract the module position for each observer in execution order
    $observerModulePositions = [];
    foreach ($observers as $name => $obs) {
        $module = getObserverModule($obs['model']);
        if ($module !== null && isset($positions[$module])) {
            $observerModulePositions[$name] = $positions[$module];
        }
    }

    // Verify the sequence is non-decreasing (sorted by module dependency)
    $values = array_values($observerModulePositions);
    for ($i = 1; $i < count($values); $i++) {
        expect($values[$i])->toBeGreaterThanOrEqual(
            $values[$i - 1],
            'Observers should be ordered by module dependency',
        );
    }

    // Verify we have observers from multiple modules
    expect(count(array_unique($observerModulePositions)))->toBeGreaterThanOrEqual(2);
});

it('places attribute observers from same module alongside XML observers', function () {
    $observers = resolveObservers('global', 'customer_save_after');

    expect($observers)->toHaveKey('Mage_Newsletter_Model_Observer::subscribeCustomer');
});

it('compiled attributes file contains alias field for all observers', function () {
    $compiled = Maho::getCompiledAttributes();

    foreach ($compiled['observers'] as $events) {
        foreach ($events as $observers) {
            foreach ($observers as $observer) {
                expect($observer)->toHaveKey('alias');
                expect($observer['alias'])->not->toBeEmpty();
            }
        }
    }
});
