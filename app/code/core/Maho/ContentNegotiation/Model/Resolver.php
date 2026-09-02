<?php

/**
 * Maps a route to the renderer that builds its markdown.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Resolver
{
    /**
     * One child per renderer, with a <route> prefix and a <model> alias. The first prefix that
     * matches wins, so a module registers a more specific route before a general one.
     */
    public const XML_PATH_RENDERERS = 'global/contentnegotiation/renderers';

    /** @var array<string, string>|null route prefix => model alias */
    private ?array $renderers = null;

    public function resolve(string $route): ?Maho_ContentNegotiation_Model_Renderer_RendererInterface
    {
        $alias = $this->getRendererAlias($route);
        if ($alias === null) {
            return null;
        }
        $renderer = Mage::getModel($alias);

        return $renderer instanceof Maho_ContentNegotiation_Model_Renderer_RendererInterface ? $renderer : null;
    }

    public function hasRenderer(string $route): bool
    {
        return $this->getRendererAlias($route) !== null;
    }

    public function getRendererAlias(string $route): ?string
    {
        foreach ($this->getRenderers() as $prefix => $alias) {
            if (str_starts_with($route, $prefix)) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * @return array<string, string> route prefix => model alias, in declaration order
     */
    public function getRenderers(): array
    {
        if ($this->renderers !== null) {
            return $this->renderers;
        }

        $this->renderers = [];
        $node = Mage::getConfig()->getNode(self::XML_PATH_RENDERERS);
        if ($node) {
            foreach ($node->children() as $child) {
                $route = trim((string) $child->route);
                $model = trim((string) $child->model);
                if ($route !== '' && $model !== '') {
                    $this->renderers[$route] = $model;
                }
            }
        }

        return $this->renderers;
    }
}
