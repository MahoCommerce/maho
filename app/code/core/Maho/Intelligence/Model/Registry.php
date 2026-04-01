<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
