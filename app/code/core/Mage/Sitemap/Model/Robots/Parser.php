<?php

/**
 * Lexer for robots.txt content, following the grammar of RFC 9309.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Robots_Parser
{
    public const AGENT_FIELD = 'user-agent';

    /** Fields that apply to the whole file instead of to the group they sit in. */
    public const NON_GROUP_FIELDS = ['sitemap', 'host'];

    protected const CANONICAL_FIELDS = [
        'user-agent' => 'User-agent',
        'disallow' => 'Disallow',
        'allow' => 'Allow',
        'sitemap' => 'Sitemap',
        'crawl-delay' => 'Crawl-delay',
        'host' => 'Host',
        'content-signal' => 'Content-Signal',
    ];

    public function parse(string $text): Mage_Sitemap_Model_Robots_Document
    {
        $document = new Mage_Sitemap_Model_Robots_Document();
        $collectingAgents = false;

        foreach (preg_split('/\R/', str_replace("\0", '', $text)) ?: [] as $rawLine) {
            $line = trim($this->stripComment($rawLine));
            if ($line === '') {
                continue;
            }

            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $field = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($field === '') {
                continue;
            }

            if ($field === self::AGENT_FIELD) {
                if ($value === '') {
                    continue;
                }
                // Consecutive user-agent lines belong to the same group.
                $group = $collectingAgents ? $document->getLastGroup() : null;
                if ($group === null) {
                    $group = new Mage_Sitemap_Model_Robots_Group();
                    $document->addGroup($group);
                }
                $group->addAgent($value);
                $collectingAgents = true;
                continue;
            }

            $collectingAgents = false;
            $normalized = $this->canonicalField($field) . ': ' . $value;

            if (in_array($field, self::NON_GROUP_FIELDS, true)) {
                $document->addGlobalLine($normalized);
                continue;
            }

            $group = $document->getLastGroup();
            if ($group === null) {
                $document->addOrphanRule($normalized);
                continue;
            }
            $group->addRule($normalized);
        }

        return $document;
    }

    /**
     * Rules that apply to every crawler. A named group pasted into a rules-only field keeps
     * its rules to itself instead of being applied to all crawlers.
     *
     * @return list<string>
     */
    public function parseWildcardRules(string $text): array
    {
        return $this->parse($text)->getWildcardRules();
    }

    public function canonicalField(string $field): string
    {
        return self::CANONICAL_FIELDS[strtolower(trim($field))] ?? trim($field);
    }

    protected function stripComment(string $line): string
    {
        $position = strpos($line, '#');
        return $position === false ? $line : substr($line, 0, $position);
    }
}
