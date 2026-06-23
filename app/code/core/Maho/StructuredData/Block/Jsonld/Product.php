<?php

/**
 * Product JSON-LD structured data (schema.org/Product).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_Jsonld_Product extends Maho_StructuredData_Block_Jsonld_Abstract
{
    #[\Override]
    protected function isTypeEnabled(): bool
    {
        $helper = Mage::helper('structureddata');
        return $helper->isProductEnabled();
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = Mage::registry('current_product');
        return $product instanceof Mage_Catalog_Model_Product ? $product : null;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $product = $this->getProduct();
        if (!$product) {
            return [];
        }

        $helper = Mage::helper('structureddata');

        $data = [
            '@context' => 'https://schema.org/',
            '@type' => 'Product',
            'name' => $product->getName(),
        ];

        $description = $this->_getDescription($product);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $sku = (string) $product->getSku();
        if ($sku !== '') {
            $data['sku'] = $sku;
        }

        $images = $this->_getImages($product);
        if ($images !== []) {
            $data['image'] = $images;
        }

        $url = $product->getProductUrl();
        if ($url) {
            $data['url'] = $url;
        }

        $brand = $this->_getBrand($product);
        if ($brand !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        $gtin = $this->_getMappedAttribute($product, $helper->getGtinAttribute());
        if ($gtin !== '') {
            $data['gtin'] = $gtin;
        }

        $mpn = $this->_getMappedAttribute($product, $helper->getMpnAttribute());
        if ($mpn !== '') {
            $data['mpn'] = $mpn;
        }

        $offers = $this->_getOffers($product);
        if ($offers !== []) {
            $data['offers'] = $offers;
        }

        if ($helper->includeReviews()) {
            $rating = $this->_getAggregateRating($product);
            if ($rating !== []) {
                $data['aggregateRating'] = $rating;
            }
        }

        // Allow other modules to enrich or alter the product structured data.
        $transport = new Maho\DataObject(['structured_data' => $data]);
        Mage::dispatchEvent('maho_structureddata_product_data', [
            'product' => $product,
            'transport' => $transport,
        ]);

        return $transport->getStructuredData();
    }

    protected function _getDescription(Mage_Catalog_Model_Product $product): string
    {
        $description = (string) ($product->getMetaDescription()
            ?: $product->getShortDescription()
            ?: $product->getDescription());

        $description = trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');

        return $description;
    }

    /**
     * Absolute URLs for the main image plus gallery images.
     *
     * @return array<int, string>
     */
    protected function _getImages(Mage_Catalog_Model_Product $product): array
    {
        $images = [];

        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $images[] = (string) Mage::helper('catalog/image')->init($product, 'image');
        }

        $gallery = $product->getMediaGalleryImages();
        if ($gallery && $gallery->getSize()) {
            foreach ($gallery as $image) {
                $url = (string) $image->getUrl();
                if ($url !== '' && !in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }

        return $images;
    }

    protected function _getBrand(Mage_Catalog_Model_Product $product): string
    {
        $helper = Mage::helper('structureddata');
        return $this->_getMappedAttribute($product, $helper->getBrandAttribute());
    }

    /**
     * Resolve a configured attribute code to its frontend (label) value.
     */
    protected function _getMappedAttribute(Mage_Catalog_Model_Product $product, string $attributeCode): string
    {
        if ($attributeCode === '') {
            return '';
        }

        $attribute = $product->getResource()->getAttribute($attributeCode);
        if (!$attribute) {
            return '';
        }

        if ($attribute->usesSource()) {
            $value = $product->getAttributeText($attributeCode);
            $value = is_array($value) ? implode(', ', $value) : (string) $value;
        } else {
            $value = (string) $product->getData($attributeCode);
        }

        return trim($value);
    }

    /**
     * Build the offers node, choosing Offer vs AggregateOffer by product type.
     *
     * @return array<string, mixed>
     */
    protected function _getOffers(Mage_Catalog_Model_Product $product): array
    {
        $helper = Mage::helper('structureddata');
        $currency = $helper->getCurrencyCode($product->getStoreId());
        $availability = $helper->getAvailabilityUrl($product);
        $url = $product->getProductUrl();

        $prices = $this->_collectPrices($product);
        if ($prices === []) {
            return [];
        }

        $base = [
            'priceCurrency' => $currency,
            'availability' => $availability,
            'itemCondition' => Maho_StructuredData_Helper_Data::SCHEMA . 'NewCondition',
        ];
        if ($url) {
            $base['url'] = $url;
        }

        $validUntil = $this->_getPriceValidUntil($product);
        if ($validUntil !== '') {
            $base['priceValidUntil'] = $validUntil;
        }

        if (count($prices) === 1) {
            return ['@type' => 'Offer', 'price' => $helper->formatPrice($prices[0])] + $base;
        }

        return [
            '@type' => 'AggregateOffer',
            'lowPrice' => $helper->formatPrice(min($prices)),
            'highPrice' => $helper->formatPrice(max($prices)),
            'offerCount' => count($prices),
        ] + $base;
    }

    /**
     * Collect candidate display prices (current currency, incl. tax) for the product type.
     *
     * @return array<int, float>
     */
    protected function _collectPrices(Mage_Catalog_Model_Product $product): array
    {
        $helper = Mage::helper('structureddata');
        $type = $product->getTypeId();

        if ($type === Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            /** @var Mage_Bundle_Model_Product_Price $priceModel */
            $priceModel = $product->getPriceModel();
            $store = Mage::app()->getStore($product->getStoreId());
            $min = (float) $store->convertPrice((float) $priceModel->getTotalPrices($product, 'min', true));
            $max = (float) $store->convertPrice((float) $priceModel->getTotalPrices($product, 'max', true));
            $prices = array_filter([$min, $max], static fn($p) => $p > 0);
            return $min !== $max ? array_values($prices) : ($min > 0 ? [$min] : []);
        }

        $typeInstance = $product->getTypeInstance(true);
        $children = [];
        if ($typeInstance instanceof Mage_Catalog_Model_Product_Type_Configurable) {
            $children = $typeInstance->getUsedProducts(null, $product);
        } elseif ($typeInstance instanceof Mage_Catalog_Model_Product_Type_Grouped) {
            $children = $typeInstance->getAssociatedProducts($product);
        }

        if ($children) {
            $prices = [];
            foreach ($children as $child) {
                $childPrice = (float) $child->getFinalPrice();
                if ($childPrice > 0) {
                    $prices[] = $helper->getDisplayPrice($child, $childPrice);
                }
            }
            if ($prices !== []) {
                return $prices;
            }
            // Fall through to the parent price if children carried no usable price.
        }

        $finalPrice = (float) $product->getFinalPrice();
        if ($finalPrice <= 0) {
            return [];
        }

        return [$helper->getDisplayPrice($product, $finalPrice)];
    }

    protected function _getPriceValidUntil(Mage_Catalog_Model_Product $product): string
    {
        $specialTo = $product->getSpecialToDate();
        if (!$specialTo) {
            return '';
        }

        try {
            $validUntil = Mage::app()->getLocale()->utcToStore($product->getStore(), (string) $specialTo);

            // An expired special no longer affects getFinalPrice(), so emitting a past
            // priceValidUntil would advertise an already-expired offer to search engines.
            $today = Mage::app()->getLocale()->utcToStore($product->getStore())->setTime(0, 0, 0);
            if ($validUntil < $today) {
                return '';
            }

            return $validUntil->format(Mage_Core_Model_Locale::DATE_FORMAT);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getAggregateRating(Mage_Catalog_Model_Product $product): array
    {
        if (!$product->getRatingSummary()) {
            Mage::getModel('review/review')->getEntitySummary($product, (int) $product->getStoreId());
        }

        $summary = $product->getRatingSummary();
        if (!$summary) {
            return [];
        }

        $reviewCount = (int) $summary->getReviewsCount();
        $percent = (int) $summary->getRatingSummary();
        if ($reviewCount <= 0 || $percent <= 0) {
            return [];
        }

        return [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format($percent / 20, 1, '.', ''),
            'reviewCount' => $reviewCount,
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }
}
