<?php

/**
 * Finds compiled attribute registrations that point at code that no longer exists.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Maho;

class StaleRegistrationScanner
{
    /**
     * Observers, message handlers, and routes in vendor/composer/maho_attributes.php
     * whose class or method is gone. The registry is a build artifact, so every finding
     * means the same thing: it was not recompiled. Cron jobs are checked separately,
     * because a job code also carries database configuration.
     *
     * @return list<array{kind: string, name: string, target: string, reason: string}>
     */
    public static function scan(): array
    {
        $attributes = Maho::getCompiledAttributes();

        return [
            ...self::scanObservers($attributes['observers'] ?? []),
            ...self::scanMessageHandlers($attributes['messageHandlers'] ?? []),
            ...self::scanRoutes($attributes['routes'] ?? []),
        ];
    }

    /**
     * @param array<string, array<string, list<array<string, mixed>>>> $observers
     * @return list<array{kind: string, name: string, target: string, reason: string}>
     */
    private static function scanObservers(array $observers): array
    {
        $findings = [];
        foreach ($observers as $area => $events) {
            foreach ($events as $event => $registrations) {
                foreach ($registrations as $observer) {
                    $class = (string) ($observer['class'] ?? '');
                    $method = (string) ($observer['method'] ?? '');
                    $reason = CallbackResolver::findMissingClassCallback($class, $method);
                    if ($reason !== null) {
                        $findings[] = [
                            'kind' => 'observer',
                            'name' => sprintf('%s/%s/%s', $area, $event, $observer['name'] ?? '?'),
                            'target' => $class . '::' . $method,
                            'reason' => $reason,
                        ];
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $handlers
     * @return list<array{kind: string, name: string, target: string, reason: string}>
     */
    private static function scanMessageHandlers(array $handlers): array
    {
        $findings = [];
        foreach ($handlers as $message => $registrations) {
            $message = (string) $message;
            if (!class_exists($message)) {
                $findings[] = [
                    'kind' => 'message handler',
                    'name' => $message,
                    'target' => $message,
                    'reason' => sprintf('message class %s does not exist', $message),
                ];
                continue;
            }
            foreach ($registrations as $handler) {
                $class = (string) ($handler['class'] ?? '');
                $method = (string) ($handler['method'] ?? '');
                $reason = CallbackResolver::findMissingClassCallback($class, $method);
                if ($reason !== null) {
                    $findings[] = [
                        'kind' => 'message handler',
                        'name' => $message,
                        'target' => $class . '::' . $method,
                        'reason' => $reason,
                    ];
                }
            }
        }

        return $findings;
    }

    /**
     * @param array<string, array<string, mixed>> $routes
     * @return list<array{kind: string, name: string, target: string, reason: string}>
     */
    private static function scanRoutes(array $routes): array
    {
        $findings = [];
        foreach ($routes as $name => $route) {
            $class = (string) ($route['class'] ?? '');
            $action = (string) ($route['action'] ?? '');
            $reason = CallbackResolver::findMissingClassCallback($class, $action);
            if ($reason !== null) {
                $findings[] = [
                    'kind' => 'route',
                    'name' => (string) $name,
                    'target' => $class . '::' . $action,
                    'reason' => $reason,
                ];
            }
        }

        return $findings;
    }
}
