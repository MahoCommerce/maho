<?php

/**
 * Parsed robots.txt content.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Robots_Document
{
    /** @var list<Mage_Sitemap_Model_Robots_Group> */
    protected array $groups = [];

    /** @var list<string> */
    protected array $globalLines = [];

    /** @var list<string> */
    protected array $orphanRules = [];

    /**
     * @return list<Mage_Sitemap_Model_Robots_Group>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function addGroup(Mage_Sitemap_Model_Robots_Group $group): self
    {
        $this->groups[] = $group;
        return $this;
    }

    public function getLastGroup(): ?Mage_Sitemap_Model_Robots_Group
    {
        return $this->groups === [] ? null : $this->groups[array_key_last($this->groups)];
    }

    /**
     * Non-group fields such as Sitemap, which apply to the whole file.
     *
     * @return list<string>
     */
    public function getGlobalLines(): array
    {
        return $this->globalLines;
    }

    public function addGlobalLine(string $line): self
    {
        $line = trim($line);
        if ($line !== '' && !in_array($line, $this->globalLines, true)) {
            $this->globalLines[] = $line;
        }
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getOrphanRules(): array
    {
        return $this->orphanRules;
    }

    public function addOrphanRule(string $rule): self
    {
        $rule = trim($rule);
        if ($rule !== '' && !in_array($rule, $this->orphanRules, true)) {
            $this->orphanRules[] = $rule;
        }
        return $this;
    }

    /**
     * Rules that apply to every crawler: those written outside any group, plus those of a
     * wildcard group. Rules of a named group are left out, because giving them to every
     * crawler would invert what the merchant wrote.
     *
     * @return list<string>
     */
    public function getWildcardRules(): array
    {
        $rules = $this->orphanRules;
        foreach ($this->groups as $group) {
            if (!$group->hasAgent('*')) {
                continue;
            }
            foreach ($group->getRules() as $rule) {
                if (!in_array($rule, $rules, true)) {
                    $rules[] = $rule;
                }
            }
        }
        return $rules;
    }

    public function hasAgent(string $agent): bool
    {
        return array_any($this->groups, fn($group) => $group->hasAgent($agent));
    }
}
