<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Registry
{
    private array $cache = [];
    private array $providers = [];

    public function getProvider(string $name): object
    {
        if (!isset($this->providers[$name])) {
            $this->providers[$name] = Mage::getModel("intelligence/provider_{$name}");
        }
        return $this->providers[$name];
    }

    public function get(string $provider, string $method, array $args = []): mixed
    {
        $key = $provider . '::' . $method . '::' . json_encode($args);
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $this->getProvider($provider)->$method(...$args);
        }
        return $this->cache[$key];
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
