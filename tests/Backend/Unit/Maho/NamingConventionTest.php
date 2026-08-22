<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Schema\Schema;
use Maho\Db\Schema\Collector;
use Tests\MahoBackendTestCase;

/**
 * Convention tripwire for the vendor prefix. A core table, store-config
 * section, cron id or observer id takes the name of its module or its domain
 * (blog_post_entity, feedmanager_feed, paypal_webhook_event), never the vendor
 * name. Maho_Ai, Maho_ApiPlatform and Maho_Queue each shipped a maho_-prefixed
 * table before this guard existed.
 *
 * Only core modules are checked, so a third-party module on a customer install
 * cannot fail `composer test`. A former name recorded through
 * Maho\Db\Schema\Renamer keeps its old prefix forever and is never read here.
 */

uses(MahoBackendTestCase::class);

const BANNED_IDENTIFIER_PREFIXES = ['maho_', 'mage_'];

function bannedPrefix(string $identifier): ?string
{
    foreach (BANNED_IDENTIFIER_PREFIXES as $prefix) {
        if (str_starts_with(strtolower($identifier), $prefix)) {
            return $prefix;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function coreModuleConfigFiles(string $fileName): array
{
    return glob(Mage::getBaseDir() . "/app/code/core/*/*/etc/$fileName") ?: [];
}

it('declares no core table with a vendor prefix', function () {
    // Not Collector::collect(): that folds in the configured table prefix,
    // which a store may legitimately set to maho_. Running the closures one at
    // a time attributes each new table to the module that created it; a graft
    // onto another module's table adds no name.
    $schema = new Schema();
    $seen = [];
    $offenders = [];

    foreach (Collector::sourceFiles() as $module => $file) {
        (require $file)($schema);

        foreach ($schema->getTables() as $table) {
            $name = $table->getObjectName()->getUnqualifiedName()->getValue();
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $prefix = bannedPrefix($name);
            if ($prefix !== null && str_contains($file, '/app/code/core/')) {
                $offenders[] = sprintf('%s declares table "%s" ("%s" prefix)', $module, $name, $prefix);
            }
        }
    }

    expect($seen)->not->toBeEmpty();
    expect($offenders)->toBe([], implode("\n", $offenders));
});

it('declares no core resource entity table with a vendor prefix', function () {
    $offenders = [];

    foreach (coreModuleConfigFiles('config.xml') as $file) {
        $xml = simplexml_load_file($file);
        if ($xml === false || !isset($xml->global->models)) {
            continue;
        }

        foreach ($xml->global->models->children() as $group) {
            foreach ($group->entities->children() as $entity => $node) {
                $name = trim((string) $node->table);
                if (bannedPrefix($name) !== null) {
                    $offenders[] = sprintf('%s: entity "%s" uses table "%s"', basename(dirname($file, 2)), $entity, $name);
                }
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});

it('declares no core store-config section with a vendor prefix', function () {
    $offenders = [];

    foreach (coreModuleConfigFiles('system.xml') as $file) {
        $xml = simplexml_load_file($file);
        if ($xml === false || !isset($xml->sections)) {
            continue;
        }

        foreach ($xml->sections->children() as $section) {
            if (bannedPrefix($section->getName()) !== null) {
                $offenders[] = sprintf('%s: section "%s"', basename(dirname($file, 2)), $section->getName());
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});

it('declares no core cron job or observer id with a vendor prefix', function () {
    $compiled = Mage::getBaseDir() . '/vendor/composer/maho_attributes.php';
    expect($compiled)->toBeFile();

    $isCore = static fn(string $module): bool => str_starts_with($module, 'Mage_') || str_starts_with($module, 'Maho_');
    $attributes = require $compiled;
    $offenders = [];

    foreach ($attributes['crontab'] ?? [] as $id => $cron) {
        if (bannedPrefix((string) $id) !== null && $isCore((string) ($cron['module'] ?? ''))) {
            $offenders[] = sprintf('cron job "%s" (%s)', $id, $cron['module'] ?? '?');
        }
    }

    foreach ($attributes['observers'] ?? [] as $area => $events) {
        foreach ($events as $event => $observers) {
            foreach ($observers as $observer) {
                $name = (string) ($observer['name'] ?? '');
                // An observer without an explicit id gets "alias::method", and the alias falls
                // back to the class name when the class has none. That prefix comes from the
                // class, not from a chosen identifier, so only explicit ids are checked.
                $derived = ($observer['alias'] ?? '') . '::' . ($observer['method'] ?? '');
                if ($name === $derived) {
                    continue;
                }

                if (bannedPrefix($name) !== null && $isCore((string) ($observer['module'] ?? ''))) {
                    $offenders[] = sprintf('observer "%s" on %s/%s (%s)', $name, $area, $event, $observer['module'] ?? '?');
                }
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", $offenders));
});
