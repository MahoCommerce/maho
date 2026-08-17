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

    public const CONTENT_SIGNAL_FIELD = 'Content-Signal';

    /** Each signal, its config path, and the values it accepts. */
    public const CONTENT_SIGNALS = [
        'search' => ['crawlers/robots/content_signal_search', [Mage_Sitemap_Model_Source_Signal::YES, Mage_Sitemap_Model_Source_Signal::NO]],
        'ai-input' => ['crawlers/robots/content_signal_ai_input', [Mage_Sitemap_Model_Source_Signal::YES, Mage_Sitemap_Model_Source_Signal::NO]],
        'ai-train' => ['crawlers/robots/content_signal_ai_train', [Mage_Sitemap_Model_Source_Signal::YES, Mage_Sitemap_Model_Source_Signal::NO]],
        'use' => ['crawlers/robots/content_signal_use', [
            Mage_Sitemap_Model_Source_Signal_Usage::IMMEDIATE,
            Mage_Sitemap_Model_Source_Signal_Usage::REFERENCE,
            Mage_Sitemap_Model_Source_Signal_Usage::FULL,
        ]],
    ];

    /**
     * The notice from contentsignals.org, verbatim: it is what turns the signals below it from a
     * hint into a stated condition of access, so it is not ours to reword.
     */
    public const CONTENT_SIGNAL_NOTICE = <<<'TXT'
        # As a condition of accessing this website, you agree to abide by the following
        # content signals:

        # (a)  If a Content-Signal = yes, you may collect content for the corresponding
        #      use.
        # (b)  If a Content-Signal = no, you may not collect content for the
        #      corresponding use.
        # (c)  If the website operator does not include a Content-Signal for a
        #      corresponding use, the website operator neither grants nor restricts
        #      permission via Content-Signal with respect to the corresponding use.

        # The content signals and their meanings are:

        # search:   building a search index and providing search results (e.g., returning
        #           hyperlinks and short excerpts from your website's contents). Search does not
        #           include providing AI-generated search summaries.
        # ai-input: inputting content into one or more AI models (e.g., retrieval
        #           augmented generation, grounding, or other real-time taking of content for
        #           generative AI search answers).
        # ai-train: training or fine-tuning AI models.
        # use:      how AI systems may consume the content (immediate, reference, or full).

        # ANY RESTRICTIONS EXPRESSED VIA CONTENT SIGNALS ARE EXPRESS RESERVATIONS OF
        # RIGHTS UNDER ARTICLE 4 OF THE EUROPEAN UNION DIRECTIVE 2019/790 ON COPYRIGHT
        # AND RELATED RIGHTS IN THE DIGITAL SINGLE MARKET.
        TXT;

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
            $parser->parseWildcardRules((string) Mage::getStoreConfig(self::XML_PATH_BASE_RULES, $storeId)),
        );
        $custom = $parser->parse((string) Mage::getStoreConfig(self::XML_PATH_CUSTOM, $storeId));

        $wildcard = new Mage_Sitemap_Model_Robots_Group(['*'], $baseRules);
        foreach ($this->filterAdminPath($custom->getOrphanRules()) as $rule) {
            $wildcard->addRule($rule);
        }

        // First pass: the wildcard group must be complete before any named group copies it.
        $customGroups = [];
        foreach ($custom->getGroups() as $group) {
            $rules = $this->filterAdminPath($group->getRules());
            $agents = array_values(array_filter($group->getAgents(), static fn(string $agent): bool => $agent !== '*'));

            // A hand-written wildcard group extends the generated one instead of repeating it.
            if (count($agents) !== count($group->getAgents())) {
                foreach ($rules as $rule) {
                    $wildcard->addRule($rule);
                }
            }
            if ($agents !== []) {
                $customGroups[] = [$agents, $rules];
            }
        }

        if ($wildcard->getRules() === []) {
            $wildcard->addRule('Disallow:');
        }

        $signal = $this->getContentSignal($storeId);
        if ($signal !== '' && !$this->hasContentSignal($wildcard)) {
            $wildcard->prependRules([self::CONTENT_SIGNAL_FIELD . ': ' . $signal]);
        }

        $named = [];
        foreach ($customGroups as [$agents, $rules]) {
            // RFC 9309: a named group inherits nothing from the wildcard group.
            $group = new Mage_Sitemap_Model_Robots_Group($agents, $rules);
            if (!$group->hasRule('Disallow: /')) {
                $group->prependRules($wildcard->getRules());
            }
            $named[] = $group;
        }

        $blocks = [$wildcard->toString()];
        $signalled = $this->hasContentSignal($wildcard);

        foreach ($this->getBlockedAgents($storeId) as $agent) {
            if ($custom->hasAgent($agent)) {
                continue;
            }
            $blocks[] = (new Mage_Sitemap_Model_Robots_Group([$agent], ['Disallow: /']))->toString();
        }

        foreach ($named as $group) {
            $blocks[] = $group->toString();
            $signalled = $signalled || $this->hasContentSignal($group);
        }

        if ($signalled) {
            array_unshift($blocks, self::CONTENT_SIGNAL_NOTICE);
        }

        $sitemapLines = $custom->getGlobalLines();
        if (Mage::getStoreConfigFlag(self::XML_PATH_INCLUDE_SITEMAPS, $storeId)) {
            foreach ($this->getSitemapUrls($store) as $url) {
                $line = 'Sitemap: ' . $url;
                if (!in_array($line, $sitemapLines, true)) {
                    $sitemapLines[] = $line;
                }
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
        $baseUrl = rtrim($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/') . '/';
        $urls = [];

        /** @var Mage_Sitemap_Model_Resource_Sitemap_Collection $collection */
        $collection = Mage::getResourceModel('sitemap/sitemap_collection');
        $collection->addStoreFilter([$store->getId()]);

        /** @var Mage_Sitemap_Model_Sitemap $sitemap */
        foreach ($collection as $sitemap) {
            $path = trim((string) $sitemap->getSitemapPath(), '/');
            $url = $baseUrl . ($path === '' ? '' : $path . '/') . $sitemap->getSitemapFilename();
            if (!in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * The signals as they are written on the Content-Signal line, in the order of the spec.
     * Empty when every signal is left unstated, which keeps the line and its notice out of the file.
     */
    public function getContentSignal(?int $storeId = null): string
    {
        $parts = [];
        foreach (self::CONTENT_SIGNALS as $signal => [$path, $allowed]) {
            $value = trim((string) Mage::getStoreConfig($path, $storeId));
            if (in_array($value, $allowed, true)) {
                $parts[] = $signal . '=' . $value;
            }
        }

        return implode(',', $parts);
    }

    /**
     * A hand-written signal in Custom Instructions replaces the configured one instead of
     * joining it: two Content-Signal lines in one group state two policies.
     */
    public function hasContentSignal(Mage_Sitemap_Model_Robots_Group $group): bool
    {
        return array_any($group->getRules(), static function (string $rule): bool {
            $parts = explode(':', $rule, 2);
            return count($parts) === 2 && strcasecmp(trim($parts[0]), self::CONTENT_SIGNAL_FIELD) === 0;
        });
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
            // Product tokens are matched case-insensitively (RFC 9309 section 2.2.1).
            if ($agent !== '' && !array_any($agents, fn(string $known): bool => strcasecmp($known, $agent) === 0)) {
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
