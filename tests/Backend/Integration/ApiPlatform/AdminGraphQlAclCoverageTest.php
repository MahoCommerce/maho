<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Controller\AdminGraphQlController;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Authorization-coverage guard for /api/admin/graphql.
 *
 * This endpoint is a separate surface from the REST and storefront-GraphQL one.
 * It does not go through API Platform's state providers, so nothing there
 * evaluates an operation `security:` expression or a property one: the handlers
 * hand-serialize with $dto->toArray(). What gates it instead is
 * AdminAcl::checkResource(), once per handler method, against the same
 * ADMIN_RESOURCE constant AdminAclListener uses on the REST surface.
 *
 * That makes the per-handler call the only gate, and forgetting one on a new
 * operation is invisible in review. These tests walk the controller's own
 * dispatch table, so a handler method reachable from a GraphQL operation name
 * has to carry the check.
 */

/**
 * Handler methods the controller can dispatch a GraphQL operation to, read out
 * of resolveOperation()'s match table.
 *
 * @return list<string>
 */
function adminGraphQlDispatchedMethods(): array
{
    $method = new ReflectionMethod(AdminGraphQlController::class, 'resolveOperation');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine(),
        $method->getEndLine() - $method->getStartLine(),
    ));

    preg_match_all('/\$this->(\w+)->(\w+)\(/', $source, $matches, PREG_SET_ORDER);

    $methods = [];
    foreach ($matches as [, $handlerProperty, $handlerMethod]) {
        $type = (new ReflectionProperty(AdminGraphQlController::class, $handlerProperty))->getType();
        $methods[] = ((string) $type) . '::' . $handlerMethod;
    }

    return array_values(array_unique($methods));
}

/** Source of one method, so the assertions can look for the gate inside it. */
function adminGraphQlMethodSource(string $class, string $method): string
{
    $reflection = new ReflectionMethod($class, $method);

    return implode('', array_slice(
        file($reflection->getFileName()),
        $reflection->getStartLine(),
        $reflection->getEndLine() - $reflection->getStartLine(),
    ));
}

it('dispatches to a non-trivial number of handler methods', function (): void {
    // Guards against the regex silently matching nothing after a refactor,
    // which would make the coverage assertion below vacuously pass.
    expect(count(adminGraphQlDispatchedMethods()))->toBeGreaterThan(20);
});

it('checks the admin ACL in every handler method the admin GraphQL endpoint dispatches to', function (): void {
    $ungated = [];

    foreach (adminGraphQlDispatchedMethods() as $target) {
        [$class, $method] = explode('::', $target, 2);
        if (!str_contains(adminGraphQlMethodSource($class, $method), 'AdminAcl::checkResource(')) {
            $ungated[] = (new ReflectionClass($class))->getShortName() . "::{$method}()";
        }
    }

    expect($ungated)->toBe(
        [],
        'Admin GraphQL operations reachable without an ADMIN_RESOURCE check: ' . implode(', ', $ungated),
    );
});

it('names a resource class that declares ADMIN_RESOURCE in every check', function (): void {
    $undeclared = [];

    foreach (adminGraphQlDispatchedMethods() as $target) {
        [$class, $method] = explode('::', $target, 2);
        $source = adminGraphQlMethodSource($class, $method);

        preg_match_all('/AdminAcl::checkResource\(\s*([\\\\\w]+)::class\s*\)/', $source, $matches);
        foreach ($matches[1] as $shortRef) {
            // The handlers import their resource classes, so resolve the
            // reference through the handler's own use statements.
            $resourceClass = adminGraphQlResolveClassRef($class, $shortRef);
            if ($resourceClass === null || !defined($resourceClass . '::ADMIN_RESOURCE')) {
                $undeclared[] = (new ReflectionClass($class))->getShortName() . "::{$method}() -> {$shortRef}";
            }
        }
    }

    expect($undeclared)->toBe(
        [],
        'AdminAcl::checkResource() called with a class that declares no ADMIN_RESOURCE, '
        . 'which default-denies the operation outright: ' . implode(', ', $undeclared),
    );
});

/** Resolve a `Foo::class` reference written inside $context's file to its FQCN. */
function adminGraphQlResolveClassRef(string $context, string $reference): ?string
{
    $reference = ltrim($reference, '\\');
    if (class_exists($reference)) {
        return $reference;
    }

    $source = (string) file_get_contents((new ReflectionClass($context))->getFileName());
    preg_match_all('/^use\s+([\\\\\w]+);/m', $source, $matches, PREG_SET_ORDER);

    foreach ($matches as [, $imported]) {
        if (str_ends_with($imported, '\\' . $reference) && class_exists($imported)) {
            return $imported;
        }
    }

    return null;
}
