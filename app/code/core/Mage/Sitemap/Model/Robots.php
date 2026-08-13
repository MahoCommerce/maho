<?php

/**
 * Builds the robots.txt served at the store root.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Robots
{
    public const XML_PATH_ENABLED = 'crawlers/robots/enabled';
    public const XML_PATH_BASE_RULES = 'crawlers/robots/base_rules';
    public const XML_PATH_BLOCKED_AGENTS = 'crawlers/robots/blocked_agents';
    public const XML_PATH_INCLUDE_SITEMAPS = 'crawlers/robots/include_sitemaps';
    public const XML_PATH_CUSTOM = 'crawlers/robots/custom';

    public const FALLBACK = "User-agent: *\nDisallow:\n";

    public function isEnabled(?Mage_Core_Model_Store $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store?->getId());
    }

    public function generate(?Mage_Core_Model_Store $store = null): string
    {
        $store ??= Mage::app()->getStore();
        $parser = $this->getParser();
        $storeId = $store->getId();

        $baseRules = $this->filterAdminPath(
            $parser->parseRules((string) Mage::getStoreConfig(self::XML_PATH_BASE_RULES, $storeId)),
        );
        $custom = $parser->parse((string) Mage::getStoreConfig(self::XML_PATH_CUSTOM, $storeId));

        $wildcard = new Mage_Sitemap_Model_Robots_Group(['*'], $baseRules);
        foreach ($this->filterAdminPath($custom->getOrphanRules()) as $rule) {
            $wildcard->addRule($rule);
        }

        $named = [];
        foreach ($custom->getGroups() as $group) {
            $rules = $this->filterAdminPath($group->getRules());
            $agents = array_values(array_filter($group->getAgents(), static fn(string $agent): bool => $agent !== '*'));

            // A hand-written wildcard group extends the generated one instead of repeating it.
            if (count($agents) !== count($group->getAgents())) {
                foreach ($rules as $rule) {
                    $wildcard->addRule($rule);
                }
            }
            if ($agents === []) {
                continue;
            }

            // RFC 9309: a named group inherits nothing from the wildcard group.
            $group = new Mage_Sitemap_Model_Robots_Group($agents, $rules);
            if (!$group->hasRule('Disallow: /')) {
                $group->prependRules($baseRules);
            }
            if ($group->getRules() === []) {
                $group->addRule('Disallow:');
            }
            $named[] = $group;
        }

        if ($wildcard->getRules() === []) {
            $wildcard->addRule('Disallow:');
        }

        $blocks = [$wildcard->toString()];

        foreach ($this->getBlockedAgents($storeId) as $agent) {
            if ($custom->hasAgent($agent)) {
                continue;
            }
            $blocks[] = (new Mage_Sitemap_Model_Robots_Group([$agent], ['Disallow: /']))->toString();
        }

        foreach ($named as $group) {
            $blocks[] = $group->toString();
        }

        $sitemapLines = $custom->getGlobalLines();
        foreach ($this->getSitemapUrls($store) as $url) {
            $line = 'Sitemap: ' . $url;
            if (!in_array($line, $sitemapLines, true)) {
                $sitemapLines[] = $line;
            }
        }
        if ($sitemapLines !== []) {
            $blocks[] = implode("\n", $sitemapLines);
        }

        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * @return list<string>
     */
    public function getSitemapUrls(Mage_Core_Model_Store $store): array
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_INCLUDE_SITEMAPS, $store->getId())) {
            return [];
        }

        $baseUrl = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $urls = [];

        /** @var Mage_Sitemap_Model_Resource_Sitemap_Collection $collection */
        $collection = Mage::getResourceModel('sitemap/sitemap_collection');
        $collection->addStoreFilter([$store->getId()]);

        /** @var Mage_Sitemap_Model_Sitemap $sitemap */
        foreach ($collection as $sitemap) {
            $file = ltrim((string) $sitemap->getSitemapPath(), '/') . $sitemap->getSitemapFilename();
            $url = $baseUrl . $file;
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    public function getBlockedAgents(?int $storeId = null): array
    {
        $configured = (string) Mage::getStoreConfig(self::XML_PATH_BLOCKED_AGENTS, $storeId);
        $agents = [];
        foreach (explode(',', $configured) as $agent) {
            $agent = trim($agent);
            if ($agent !== '' && !in_array($agent, $agents, true)) {
                $agents[] = $agent;
            }
        }
        return $agents;
    }

    /**
     * Drop rules naming the admin path, which would publish the location of the backend.
     *
     * @param list<string> $rules
     * @return list<string>
     */
    public function filterAdminPath(array $rules): array
    {
        $frontName = \Maho\Routing\RouteCollectionBuilder::getAdminFrontName();
        if ($frontName === '') {
            return array_values($rules);
        }

        return array_values(array_filter($rules, function (string $rule) use ($frontName): bool {
            $parts = explode(':', $rule, 2);
            if (count($parts) !== 2 || !in_array(strtolower(trim($parts[0])), ['allow', 'disallow'], true)) {
                return true;
            }
            $path = trim($parts[1]);
            return array_all(explode('/', $path), fn($segment) => strcasecmp(trim($segment, '*$'), $frontName) !== 0);
        }));
    }

    public function getParser(): Mage_Sitemap_Model_Robots_Parser
    {
        /** @var Mage_Sitemap_Model_Robots_Parser $parser */
        $parser = Mage::getSingleton('sitemap/robots_parser');
        return $parser;
    }
}
