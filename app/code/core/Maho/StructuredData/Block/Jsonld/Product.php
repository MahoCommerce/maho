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
    /** Cap the number of individual Review nodes emitted, newest first, to bound page weight. */
    protected const REVIEWS_LIMIT = 10;

    protected string $_eventObject = 'product';

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = Mage::registry('current_product');
        return $product instanceof Mage_Catalog_Model_Product ? $product : null;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function _getEventData(): array
    {
        return ['product' => $this->getProduct()];
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
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
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

            $reviews = $this->_getReviews($product);
            if ($reviews !== []) {
                $data['review'] = $reviews;
            }
        }

        return $data;
    }

    protected function _getDescription(Mage_Catalog_Model_Product $product): string
    {
        $description = (string) ($product->getMetaDescription()
            ?: $product->getShortDescription()
            ?: $product->getDescription());

        return Mage::helper('structureddata')->toPlainText($description);
    }

    /**
     * Absolute URLs for the main image plus gallery images.
     *
     * @return array<int, string>
     */
    protected function _getImages(Mage_Catalog_Model_Product $product): array
    {
        $images = [];

        // Use the canonical original media URL (the same form gallery images use below) rather than
        // the resize helper, which returns a signed core/index/resize endpoint URL. This keeps the
        // emitted image stable/crawlable and lets the gallery dedup catch the base image.
        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $images[] = (string) $product->getMediaConfig()->getMediaUrl($product->getImage());
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

        // Collapse to a single Offer when every candidate price is the same (e.g. a configurable
        // whose variants carry no price differential). Emitting AggregateOffer with
        // lowPrice === highPrice is flagged by validators and misrepresents the offer set.
        $uniquePrices = array_values(array_unique($prices));
        if (count($uniquePrices) === 1) {
            return ['@type' => 'Offer', 'price' => $helper->formatPrice($uniquePrices[0])] + $base;
        }

        // offerCount is the total number of offers (variants), not the number of distinct price
        // points — schema.org/AggregateOffer.offerCount is the count of offers in the set, and
        // Google's Rich Results Test flags a mismatch against the actual variant count.
        return [
            '@type' => 'AggregateOffer',
            'lowPrice' => $helper->formatPrice(min($uniquePrices)),
            'highPrice' => $helper->formatPrice(max($uniquePrices)),
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
        $specialTo = (string) $product->getSpecialToDate();
        if ($specialTo === '' || str_starts_with($specialTo, '0000')) {
            return '';
        }

        // special_to_date is a store-local, date-only column (like blog publish_date): emit it
        // verbatim with no timezone conversion. Running it through utcToStore() would shift it by
        // the store offset and could roll it to the wrong calendar day. Core's special-price
        // interval check (isStoreDateInInterval) compares the raw date in the store timezone too.
        $validUntil = substr($specialTo, 0, 10);

        try {
            // An expired special no longer affects getFinalPrice(), so don't advertise a past date.
            // Core keeps the special valid through the whole to-date day, so drop only when strictly before today.
            $today = Mage::app()->getLocale()->utcToStore($product->getStore())
                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
            if ($validUntil < $today) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        return $validUntil;
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

    /**
     * Build individual Review nodes from the most recent approved reviews.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function _getReviews(Mage_Catalog_Model_Product $product): array
    {
        $helper = Mage::helper('structureddata');

        $collection = $this->_getReviewsCollection($product);

        $reviews = [];
        foreach ($collection as $review) {
            if (count($reviews) >= self::REVIEWS_LIMIT) {
                break;
            }

            $author = trim((string) $review->getNickname());
            $body = $helper->toPlainText((string) $review->getDetail());
            if ($author === '' || $body === '') {
                continue;
            }

            $node = [
                '@type' => 'Review',
                'author' => ['@type' => 'Person', 'name' => $author],
                'reviewBody' => $body,
            ];

            $title = trim((string) $review->getTitle());
            if ($title !== '') {
                $node['name'] = $title;
            }

            // created_at is a genuine UTC datetime, so utcToStore() conversion is correct here.
            $datePublished = $helper->formatUtcDateTime((string) $review->getCreatedAt());
            if ($datePublished !== '') {
                $node['datePublished'] = $datePublished;
            }

            $rating = $this->_getReviewRating($review);
            if ($rating !== []) {
                $node['reviewRating'] = $rating;
            }

            $reviews[] = $node;
        }

        return $reviews;
    }

    /**
     * Resolve the approved-reviews collection for the product. Always uses an independent,
     * page-size-bounded query: reusing the product page's own `product.reviews` collection would
     * mean loading every approved review (that block sets no page size) and running addRateVotes()
     * as an N+1 over all of them, just to emit at most REVIEWS_LIMIT nodes — and would mutate the
     * shared instance the visible review list paginates.
     *
     * @return iterable<Mage_Review_Model_Review>
     */
    protected function _getReviewsCollection(Mage_Catalog_Model_Product $product): iterable
    {
        $collection = Mage::getModel('review/review')->getCollection()
            ->addStoreFilter((int) $product->getStoreId())
            ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED)
            ->addEntityFilter('product', $product->getId())
            ->setDateOrder()
            ->setPageSize(self::REVIEWS_LIMIT);

        $collection->load()->addRateVotes();

        return $collection;
    }

    /**
     * Average a single review's rating votes (each a 0-100 percent) into a 0-5 Rating node.
     *
     * @return array<string, mixed>
     */
    protected function _getReviewRating(Mage_Review_Model_Review $review): array
    {
        $votes = $review->getRatingVotes();
        if (!$votes || count($votes) === 0) {
            return [];
        }

        $sum = 0;
        $count = 0;
        foreach ($votes as $vote) {
            $percent = (int) $vote->getPercent();
            if ($percent > 0) {
                $sum += $percent;
                $count++;
            }
        }

        if ($count === 0) {
            return [];
        }

        return [
            '@type' => 'Rating',
            'ratingValue' => number_format($sum / $count / 20, 1, '.', ''),
            'bestRating' => '5',
            'worstRating' => '1',
        ];
    }
}
