<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\ApiResource;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Server-independent naming guards for API Platform resources. Both failure
 * modes below silently break the *entire* GraphQL schema build ("Internal
 * server error") and each cost real debugging time to trace:
 *
 *   1. REST/GraphQL op-name collision — ResourceMetadataCollection::getOperation()
 *      walks REST operations first and matches by name, so a REST op and a GraphQL
 *      op sharing a `name` makes the GraphQL type-builder grab the REST operation
 *      (see `me`/`me_rest`, `status`/`status_rest`). Names must be unique across
 *      the two op sets on a resource.
 *
 *   2. Stutter field name — API Platform composes every *custom* GraphQL field as
 *      `lcfirst(opName + ShortName)`, so an op whose name already contains the
 *      resource noun yields a doubled field (`orderOrder`, `productsProducts`).
 *      Name custom ops *without* the noun the shortName supplies.
 *
 * These run at attribute level — no running HTTP server required.
 */

beforeEach(function (): void {
    // Mage\<Module>\Api\ / Maho\<Module>\Api\ classes are autoloaded by the API
    // kernel boot(), which doesn't run in a plain backend test. Map both
    // namespaces to app/code/core/ so the resource attributes resolve here.
    static $registered = false;
    if (!$registered) {
        spl_autoload_register(function (string $class): void {
            if (!str_starts_with($class, 'Mage\\') && !str_starts_with($class, 'Maho\\')) {
                return;
            }
            $file = Mage::getBaseDir() . '/app/code/core/' . str_replace('\\', '/', $class) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
        $registered = true;
    }
});

/**
 * Every resource class carrying an #[ApiResource] under a module Api directory.
 *
 * @return array<class-string>
 */
function apiResourceClasses(): array
{
    $classes = [];
    foreach (glob(Mage::getBaseDir() . '/app/code/core/*/*/Api/*.php') ?: [] as $file) {
        $rel = substr($file, strlen(Mage::getBaseDir() . '/app/code/core/'), -4);
        $class = str_replace('/', '\\', $rel);
        if (!class_exists($class)) {
            continue;
        }
        if ((new ReflectionClass($class))->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF)) {
            $classes[] = $class;
        }
    }
    return $classes;
}

/**
 * @return array{shortName: string, rest: array<string>, graphql: array<string>}
 */
function operationNamesOf(string $resourceClass): array
{
    $shortName = '';
    $rest = [];
    $graphql = [];
    foreach ((new ReflectionClass($resourceClass))->getAttributes(ApiResource::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
        /** @var ApiResource $resource */
        $resource = $attribute->newInstance();
        $shortName = $resource->getShortName() ?: $shortName;
        foreach ($resource->getOperations() ?? [] as $op) {
            if ($op->getName()) {
                $rest[] = $op->getName();
            }
        }
        foreach ($resource->getGraphQlOperations() ?? [] as $op) {
            if ($op->getName()) {
                $graphql[] = $op->getName();
            }
        }
    }
    return ['shortName' => $shortName, 'rest' => $rest, 'graphql' => $graphql];
}

it('has no resource with a REST op name colliding with a GraphQL op name', function (): void {
    $collisions = [];
    foreach (apiResourceClasses() as $class) {
        ['rest' => $rest, 'graphql' => $graphql] = operationNamesOf($class);
        $shared = array_intersect($rest, $graphql);
        if ($shared) {
            $collisions[$class] = array_values($shared);
        }
    }

    expect($collisions)->toBe(
        [],
        'REST and GraphQL operations share a name on these resources — the GraphQL '
        . 'schema build will grab the REST op and fail. Suffix the REST op (e.g. '
        . "'me' → 'me_rest'):\n" . json_encode($collisions, JSON_PRETTY_PRINT),
    );
});

it('has no stuttering GraphQL field names', function (): void {
    $stutters = [];
    foreach (apiResourceClasses() as $class) {
        ['shortName' => $shortName, 'graphql' => $graphql] = operationNamesOf($class);
        if ($shortName === '') {
            continue;
        }
        foreach ($graphql as $name) {
            // The reserved ops legitimately map to the bare shortName/plural.
            if ($name === 'item_query' || $name === 'collection_query') {
                continue;
            }
            // Field = lcfirst(name + ShortName); a stutter is an op name that
            // already contains the resource noun, doubling it in the field.
            if (str_contains(strtolower($name), strtolower($shortName))) {
                $field = lcfirst($name . $shortName);
                $stutters[] = "{$class}: op '{$name}' → field '{$field}'";
            }
        }
    }

    expect($stutters)->toBe(
        [],
        'These GraphQL ops double the resource noun. Name them without it '
        . "(the shortName is appended automatically):\n" . implode("\n", $stutters),
    );
});
