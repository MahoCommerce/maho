<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Registry
{
    /** @var array<string, mixed> Provider results by provider, method and arguments */
    private array $cache = [];
    /** @var array<string, object> */
    private array $providers = [];

    public function getProvider(string $name): object
    {
        $this->providers[$name] ??= Mage::getModel("intelligence/provider_{$name}");
        return $this->providers[$name];
    }

    /**
     * @param list<string> $args
     */
    public function get(string $provider, string $method, array $args = []): mixed
    {
        $key = $provider . '::' . $method . '::' . json_encode($args);
        $this->cache[$key] ??= $this->getProvider($provider)->$method(...$args);
        return $this->cache[$key];
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
