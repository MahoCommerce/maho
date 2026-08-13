<?php

/**
 * A single robots.txt group: one or more user-agent lines followed by their rules.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Robots_Group
{
    /** @var list<string> */
    protected array $agents = [];

    /** @var list<string> */
    protected array $rules = [];

    /**
     * @param list<string> $agents
     * @param list<string> $rules
     */
    public function __construct(array $agents = [], array $rules = [])
    {
        foreach ($agents as $agent) {
            $this->addAgent($agent);
        }
        foreach ($rules as $rule) {
            $this->addRule($rule);
        }
    }

    /**
     * @return list<string>
     */
    public function getAgents(): array
    {
        return $this->agents;
    }

    /**
     * @return list<string>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function addAgent(string $agent): self
    {
        $agent = trim($agent);
        if ($agent !== '' && !$this->hasAgent($agent)) {
            $this->agents[] = $agent;
        }
        return $this;
    }

    /**
     * Product tokens are matched case-insensitively (RFC 9309 section 2.2.1).
     */
    public function hasAgent(string $agent): bool
    {
        return array_any($this->agents, fn($known) => strcasecmp($known, trim($agent)) === 0);
    }

    public function addRule(string $rule): self
    {
        $rule = trim($rule);
        if ($rule !== '' && !$this->hasRule($rule)) {
            $this->rules[] = $rule;
        }
        return $this;
    }

    /**
     * @param list<string> $rules
     */
    public function prependRules(array $rules): self
    {
        $missing = [];
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule !== '' && !$this->hasRule($rule) && !in_array($rule, $missing, true)) {
                $missing[] = $rule;
            }
        }
        $this->rules = [...$missing, ...$this->rules];
        return $this;
    }

    /**
     * Field names are case-insensitive, values are not.
     */
    public function hasRule(string $rule): bool
    {
        $needle = self::normalizeRule($rule);
        return array_any($this->rules, fn($known) => self::normalizeRule($known) === $needle);
    }

    public function toString(): string
    {
        $lines = [];
        foreach ($this->agents as $agent) {
            $lines[] = 'User-agent: ' . $agent;
        }
        foreach ($this->rules as $rule) {
            $lines[] = $rule;
        }
        return implode("\n", $lines);
    }

    protected static function normalizeRule(string $rule): string
    {
        $parts = explode(':', trim($rule), 2);
        if (count($parts) !== 2) {
            return strtolower(trim($rule));
        }
        return strtolower(trim($parts[0])) . ':' . trim($parts[1]);
    }
}
