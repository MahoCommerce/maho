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
    /** @var array<string, string> route prefix => model alias */
    public const RENDERERS = [
        'catalog/product/view' => 'contentnegotiation/renderer_product',
        'catalog/category/view' => 'contentnegotiation/renderer_category',
        'cms/page/view' => 'contentnegotiation/renderer_page',
        'cms/index/index' => 'contentnegotiation/renderer_page',
        'blog/index/view' => 'contentnegotiation/renderer_blogPost',
        'blog/index/index' => 'contentnegotiation/renderer_blogList',
        'blog/index/category' => 'contentnegotiation/renderer_blogList',
    ];

    public function resolve(string $route): ?Maho_ContentNegotiation_Model_Renderer_RendererInterface
    {
        foreach (self::RENDERERS as $prefix => $alias) {
            if (!str_starts_with($route, $prefix)) {
                continue;
            }
            $renderer = Mage::getModel($alias);

            return $renderer instanceof Maho_ContentNegotiation_Model_Renderer_RendererInterface ? $renderer : null;
        }

        return null;
    }
}
