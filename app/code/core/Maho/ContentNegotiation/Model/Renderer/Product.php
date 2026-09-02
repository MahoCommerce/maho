<?php

/**
 * Builds the markdown for a product page from the product model.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

class Maho_ContentNegotiation_Model_Renderer_Product extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    public const VARIANTS_LIMIT = 200;
    public const IMAGES_LIMIT = 10;

    #[\Override]
    public function render(): ?string
    {
        $product = $this->getProduct();
        if ($product === null) {
            return null;
        }

        $sections = [$this->heading((string) $product->getName()), $this->facts($product)];
        foreach ([
            $this->__('Description') => $this->description($product),
            $this->__('Specifications') => $this->specifications($product),
            $this->__('Options') => $this->options($product),
        ] as $title => $body) {
            if ($body !== '') {
                $sections[] = $this->section($title, $body);
            }
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        return $this->getProduct()?->getCacheTags() ?: [];
    }

    private function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = Mage::registry('current_product');

        return $product instanceof Mage_Catalog_Model_Product && $product->getId() ? $product : null;
    }

    private function facts(Mage_Catalog_Model_Product $product): string
    {
        $helper = Mage::helper('structureddata');
        $facts = [
            'SKU' => (string) $product->getSku(),
            $this->__('Price') => $this->price($product),
            $this->__('Availability') => $this->availabilityLabel($product),
            $this->__('Brand') => $helper->getMappedAttributeValue($product, $helper->getBrandAttribute()),
            'GTIN' => $helper->getMappedAttributeValue($product, $helper->getGtinAttribute()),
            'MPN' => $helper->getMappedAttributeValue($product, $helper->getMpnAttribute()),
            'URL' => $product->getProductUrl(),
            $this->__('Images') => implode(', ', $this->imageUrls($product)),
        ];

        $lines = [];
        foreach ($facts as $label => $value) {
            if ($value !== '') {
                $lines[] = '- ' . $label . ': ' . $this->text($value);
            }
        }

        return implode("\n", $lines);
    }

    private function price(Mage_Catalog_Model_Product $product): string
    {
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            return '';
        }

        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            /** @var Mage_Bundle_Model_Product_Price $priceModel */
            $priceModel = $product->getPriceModel();
            [$min, $max] = $priceModel->getTotalPrices($product, null, null, false);
            $store = Mage::app()->getStore();

            return $this->__(
                'from %s to %s',
                $this->formatPrice((float) $store->convertPrice($min)),
                $this->formatPrice((float) $store->convertPrice($max)),
            );
        }

        $final = $this->displayPrice($product, (float) $product->getFinalPrice());
        $regular = $this->displayPrice($product, (float) $product->getPrice());
        $price = $this->formatPrice($final);
        if ($regular > $final) {
            $price .= ' (' . $this->__('regular price %s', $this->formatPrice($regular)) . ')';
        }

        return $price;
    }

    /**
     * @return string[]
     */
    private function imageUrls(Mage_Catalog_Model_Product $product): array
    {
        $urls = [];
        foreach ($product->getMediaGalleryImages() as $image) {
            $urls[] = (string) $image->getUrl();
            if (count($urls) >= self::IMAGES_LIMIT) {
                break;
            }
        }
        $image = (string) $product->getImage();
        if ($urls === [] && $image !== '' && $image !== 'no_selection') {
            $urls[] = $product->getMediaConfig()->getMediaUrl($image);
        }

        return $urls;
    }

    private function description(Mage_Catalog_Model_Product $product): string
    {
        $output = Mage::helper('catalog/output');
        $parts = [];
        foreach (['short_description', 'description'] as $code) {
            $html = (string) $output->productAttribute($product, (string) $product->getData($code), $code);
            $markdown = $this->toMarkdown($html);
            if ($markdown !== '') {
                $parts[] = $markdown;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Same attribute selection as the "Additional Information" tab, without its "N/A" placeholders.
     */
    private function specifications(Mage_Catalog_Model_Product $product): string
    {
        $output = Mage::helper('catalog/output');
        $rows = [];
        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute->getIsVisibleOnFront()) {
                continue;
            }
            $code = (string) $attribute->getAttributeCode();
            $value = $attribute->getFrontend()->getValue($product);
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if ($attribute->getFrontendInput() === 'price') {
                $value = $this->formatPrice((float) Mage::app()->getStore()->convertPrice((float) $value));
            } else {
                $value = $this->text((string) $output->productAttribute($product, $value, $code));
            }
            if ($value !== '') {
                $rows[] = [$this->cell((string) $attribute->getStoreLabel()), $this->cell($value)];
            }
        }

        return $this->table([$this->__('Attribute'), $this->__('Value')], $rows);
    }

    private function options(Mage_Catalog_Model_Product $product): string
    {
        $type = $product->getTypeInstance(true);

        return match ($product->getTypeId()) {
            Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE => $type instanceof Mage_Catalog_Model_Product_Type_Configurable
                ? $this->variantTable($product, $type)
                : '',
            Mage_Catalog_Model_Product_Type::TYPE_GROUPED => $type instanceof Mage_Catalog_Model_Product_Type_Grouped
                ? $this->productTable($type->getAssociatedProducts($product))
                : '',
            default => '',
        };
    }

    /**
     * The buyer pays the parent final price plus the option deltas, not the child's own price.
     */
    private function variantTable(Mage_Catalog_Model_Product $product, Mage_Catalog_Model_Product_Type_Configurable $type): string
    {
        $attributes = $type->getConfigurableAttributesAsArray($product);
        $parentPrice = (float) $product->getFinalPrice();
        $headers = [];
        foreach ($attributes as $attribute) {
            $headers[] = $this->cell((string) ($attribute['store_label'] ?: $attribute['frontend_label'] ?: $attribute['attribute_code']));
        }
        $headers = [...$headers, 'SKU', $this->__('Price'), $this->__('Availability')];

        $rows = [];
        foreach ($type->getUsedProducts(null, $product) as $child) {
            if ((int) $child->getStatus() !== Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                continue;
            }
            $price = $parentPrice;
            $cells = [];
            foreach ($attributes as $attribute) {
                $code = (string) $attribute['attribute_code'];
                $cells[] = $this->cell((string) $child->getAttributeText($code));
                foreach ($attribute['values'] ?? [] as $value) {
                    if ((string) $value['value_index'] !== (string) $child->getData($code)) {
                        continue;
                    }
                    $delta = (float) ($value['pricing_value'] ?? 0);
                    $price += !empty($value['is_percent']) ? $parentPrice * $delta / 100 : $delta;
                }
            }
            $rows[] = [
                ...$cells,
                $this->cell((string) $child->getSku()),
                $this->formatPrice($this->displayPrice($product, $price)),
                $this->availabilityLabel($child),
            ];
            if (count($rows) >= self::VARIANTS_LIMIT) {
                break;
            }
        }

        return $this->table($headers, $rows);
    }
}
