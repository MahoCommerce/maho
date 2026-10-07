<?php

/**
 * Gives Mage::getService('group/name') the type of the service class that the alias resolves to.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\PHPStan;

use Maho\PHPStanPlugin\Config\MageCoreConfig;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Delete this class when mahocommerce/maho-phpstan-plugin maps Mage::getService() as it maps Mage::getModel().
 */
final class ServiceTypeExtension implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private readonly MageCoreConfig $mageCoreConfig,
    ) {}

    #[\Override]
    public function getClass(): string
    {
        return \Mage::class;
    }

    #[\Override]
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'getService';
    }

    #[\Override]
    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
    {
        $args = $methodCall->getArgs();
        if ($args === []) {
            return null;
        }
        $types = [];
        foreach ($scope->getType($args[0]->value)->getConstantStrings() as $alias) {
            $className = $this->mageCoreConfig->getConfig()->getServiceClassName($alias->getValue());
            if (!class_exists($className)) {
                return null;
            }
            $types[] = new ObjectType($className);
        }
        return $types === [] ? null : TypeCombinator::union(...$types);
    }
}
