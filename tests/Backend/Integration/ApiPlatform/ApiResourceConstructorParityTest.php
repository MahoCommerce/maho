<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

// Maho\Config\ApiResource subclasses ApiPlatform\Metadata\ApiResource and
// re-declares every parent constructor parameter so attribute callers can pass
// any of them through. PHP attributes can't inherit constructors, so this
// mirror is manual — and silently goes out of sync if API Platform adds a new
// parameter we don't pick up. This test fails loudly when that happens, telling
// the upgrader exactly which parameter to add.
it('mirrors every ApiPlatform\\Metadata\\ApiResource constructor parameter', function (): void {
    $parent = new ReflectionMethod(ApiPlatform\Metadata\ApiResource::class, '__construct');
    $child  = new ReflectionMethod(Maho\Config\ApiResource::class, '__construct');

    $parentParams = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $parent->getParameters());
    $childParams  = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $child->getParameters());

    $missing = array_diff($parentParams, $childParams);

    expect($missing)->toBe(
        [],
        sprintf(
            "Maho\\Config\\ApiResource is missing parameter(s) added to ApiPlatform\\Metadata\\ApiResource: %s.\n"
            . 'Add them to lib/Maho/Config/ApiResource.php constructor in the same position as the parent, '
            . 'so attribute usage continues to forward correctly.',
            implode(', ', $missing),
        ),
    );
});

// Defence in depth: if a parent parameter and a maho parameter ever collide on
// name (e.g. someone adds `mahoLabel` to ApiPlatform), the forward call would
// double-bind. Catch that early.
it('uses unique parameter names across parent and maho fields', function (): void {
    $child = new ReflectionMethod(Maho\Config\ApiResource::class, '__construct');
    $names = array_map(static fn(ReflectionParameter $p): string => $p->getName(), $child->getParameters());

    expect(array_unique($names))->toHaveCount(count($names), 'Duplicate parameter names in Maho\\Config\\ApiResource constructor');
});
