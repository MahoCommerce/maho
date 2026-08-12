<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Psr\Container\ContainerInterface;

/**
 * Minimal PSR-11 container over a fixed service map, for Messenger's
 * senders-by-transport-name and retry-strategies-by-transport-name locators.
 */
final readonly class ServiceLocator implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {}

    #[\Override]
    public function get(string $id): object
    {
        return $this->services[$id]
            ?? throw new ServiceNotFoundException(sprintf('Service "%s" not found', $id));
    }

    #[\Override]
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
