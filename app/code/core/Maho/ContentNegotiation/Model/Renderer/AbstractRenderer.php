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

    protected function heading(string $title, string $description = ''): string
    {
        $lines = ['# ' . $this->text($title)];
        $description = $this->text($description);
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
        return Mage::helper('structureddata')->toPlainText($html);
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
     * Same tax and currency treatment as the JSON-LD offer on the page.
     */
    protected function displayPrice(Mage_Catalog_Model_Product $product, float $price): float
    {
        return Mage::helper('structureddata')->getDisplayPrice($product, $price);
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
}
