<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Runtime view of the compiled `#[Maho\Config\MessageHandler]` registry.
 *
 * Reads the `messageHandlers` key of `vendor/composer/maho_attributes.php`,
 * filters out handlers of disabled modules, and doubles as the message-class
 * allowlist the serializer enforces before unserializing a stored body.
 */
final class HandlerRegistry
{
    /** @var array<string, list<array{module: string, class: string, alias: string, method: string, priority: int}>>|null */
    private static ?array $handlers = null;

    private static bool $recompileAttempted = false;

    public static function handlersLocator(): HandlersLocator
    {
        $map = [];
        foreach (self::handlers() as $messageClass => $entries) {
            foreach ($entries as $entry) {
                $map[$messageClass][] = new HandlerDescriptor(
                    static fn(object $message): mixed => \Mage::getSingleton($entry['alias'])->{$entry['method']}($message),
                    ['alias' => $entry['alias'] . '::' . $entry['method']],
                );
            }
        }

        return new HandlersLocator($map);
    }

    /**
     * @return list<class-string>
     */
    public static function allowedMessageClasses(): array
    {
        return array_keys(self::handlers());
    }

    /**
     * @return array<string, list<array{module: string, class: string, alias: string, method: string, priority: int}>>
     */
    private static function handlers(): array
    {
        if (self::$handlers !== null) {
            return self::$handlers;
        }

        $compiled = \Maho::getCompiledAttributes();
        if (!isset($compiled['messageHandlers']) && !self::$recompileAttempted) {
            // Compiled file predates the messageHandlers key (plugin not yet updated
            // or stale dump): recompile once per process, like ApiPermissionRegistry.
            self::$recompileAttempted = true;
            \Maho::recompilePhpAttributes();
            $compiled = \Maho::getCompiledAttributes();
        }

        $handlers = [];
        foreach ($compiled['messageHandlers'] ?? [] as $messageClass => $entries) {
            foreach ($entries as $entry) {
                if (!\Mage::helper('core')->isModuleEnabled($entry['module'])) {
                    continue;
                }
                $handlers[$messageClass][] = $entry;
            }
        }

        return self::$handlers = $handlers;
    }

    public static function reset(): void
    {
        self::$handlers = null;
        self::$recompileAttempted = false;
    }
}
