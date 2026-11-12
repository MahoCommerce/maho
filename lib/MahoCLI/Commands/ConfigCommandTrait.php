<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;

trait ConfigCommandTrait
{
    /**
     * Return the name and the code of the website or the store view of a scope, for a table cell.
     */
    private function getScopeName(string $scope, int $scopeId): string
    {
        if ($scopeId === 0) {
            return 'Default Config';
        }
        $model = match ($scope) {
            'websites' => Mage::getModel('core/website')->load($scopeId),
            'stores' => Mage::getModel('core/store')->load($scopeId),
            default => null,
        };
        return $model?->getId() ? "{$model->getName()} ({$model->getCode()})" : '<comment>[Unknown]</comment>';
    }
}
