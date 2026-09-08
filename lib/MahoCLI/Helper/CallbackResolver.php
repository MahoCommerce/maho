<?php

/**
 * Explains why a configured callback cannot be called, for the checks that verify one.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;

class CallbackResolver
{
    /**
     * Why `alias::method` cannot be called, or null when it can.
     */
    public static function findMissingCallback(string $alias, string $method): ?string
    {
        if ($alias === '' || $method === '') {
            return 'declares an incomplete callback';
        }

        $reason = self::findMissingModel($alias);
        if ($reason !== null) {
            return $reason;
        }

        return self::findMissingClassCallback(Mage::getConfig()->getModelClassName($alias), $method);
    }

    /**
     * Why the model alias cannot be built, or null when it can. getModelClassName()
     * invents a class name for an unknown alias, so class_exists() is the real test.
     */
    public static function findMissingModel(string $alias): ?string
    {
        if ($alias === '') {
            return 'declares no model, so no installed module owns it';
        }

        $class = Mage::getConfig()->getModelClassName($alias);
        if ($class === '' || !class_exists($class)) {
            return sprintf('model "%s" resolves to %s, which does not exist', $alias, $class === '' ? '(nothing)' : $class);
        }

        return null;
    }

    /**
     * Why `class::method` cannot be called, or null when it can.
     */
    public static function findMissingClassCallback(string $class, string $method): ?string
    {
        if ($class === '' || $method === '') {
            return 'declares an incomplete callback';
        }
        if (!class_exists($class)) {
            return sprintf('class %s does not exist', $class);
        }

        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            return sprintf('class %s cannot be instantiated', $class);
        }
        if (!$reflection->hasMethod($method)) {
            return sprintf('%s::%s() does not exist', $class, $method);
        }

        return null;
    }
}
