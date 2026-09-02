<?php

/**
 * Shared markdown building blocks for the page renderers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

abstract class Maho_ContentNegotiation_Model_Renderer_AbstractRenderer implements Maho_ContentNegotiation_Model_Renderer_RendererInterface
{
    #[\Override]
    public function getCacheTags(): array
    {
        return [];
    }

    protected function __(string $text, mixed ...$args): string
    {
        return (string) Mage::helper('contentnegotiation')->__($text, ...$args);
    }

    /**
     * The head block holds the page's own meta description. getDescription() would fall back
     * to the store default, which is wrong for every page that has none.
     */
    protected function heading(string $title): string
    {
        $lines = ['# ' . $this->text($title)];
        $head = Mage::app()->getLayout()->getBlock('head');
        $description = $head instanceof Mage_Page_Block_Html_Head ? $this->text((string) $head->getData('description')) : '';
        if ($description !== '') {
            $lines[] = '> ' . $description;
        }

        return implode("\n\n", $lines);
    }

    protected function section(string $title, string $body): string
    {
        return '## ' . $title . "\n\n" . $body;
    }

    protected function toMarkdown(string $html): string
    {
        return Mage::getSingleton('contentnegotiation/converter')->toMarkdown($html);
    }

    protected function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    protected function link(string $label, string $url): string
    {
        $label = str_replace(['[', ']'], '', $this->text($label));

        return '[' . $label . '](' . $url . ')';
    }

    protected function cell(string $value): string
    {
        return str_replace('|', '\|', $this->text($value));
    }

    /**
     * @param string[] $headers
     * @param array<int, string[]> $rows
     */
    protected function table(array $headers, array $rows): string
    {
        if ($rows === []) {
            return '';
        }
        $lines = [
            '| ' . implode(' | ', $headers) . ' |',
            '|' . str_repeat('---|', count($headers)),
        ];
        foreach ($rows as $row) {
            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        return implode("\n", $lines);
    }

    protected function formatPrice(float $price): string
    {
        return $this->text((string) Mage::app()->getStore()->formatPrice($price, false));
    }

    /**
     * Converts to the current currency and applies the tax display setting, as price.phtml does.
     */
    protected function displayPrice(Mage_Catalog_Model_Product $product, float $price): float
    {
        $converted = Mage::app()->getStore()->convertPrice($price);

        return (float) Mage::helper('tax')->getPrice($product, $converted);
    }

    protected function availabilityLabel(Mage_Catalog_Model_Product $product): string
    {
        $url = Mage::helper('structureddata')->getAvailabilityUrl($product);

        return match (substr($url, strlen(Maho_StructuredData_Helper_Data::SCHEMA))) {
            'InStock' => $this->__('In stock'),
            'BackOrder' => $this->__('Backorder'),
            'LimitedAvailability' => $this->__('Limited availability'),
            default => $this->__('Out of stock'),
        };
    }

    /**
     * @param iterable<Mage_Catalog_Model_Product> $products
     */
    protected function productTable(iterable $products): string
    {
        $rows = [];
        foreach ($products as $product) {
            $rows[] = [
                $this->link((string) $product->getName(), $product->getProductUrl()),
                $this->cell((string) $product->getSku()),
                $this->formatPrice($this->displayPrice($product, (float) $product->getFinalPrice())),
                $this->availabilityLabel($product),
            ];
        }

        return $this->table([$this->__('Product'), 'SKU', $this->__('Price'), $this->__('Availability')], $rows);
    }

    /**
     * Keeps a generated link in the .md form when the request used the suffix, so an agent can follow it.
     */
    protected function pageUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $helper = Mage::helper('contentnegotiation');

        return $helper->wasSuffixStripped() ? $helper->toMarkdownUrl($url) : $url;
    }
}
