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

    /** Configurable attribute codes that map directly to schema.org variant properties. */
    protected const VARIANT_PROPERTIES = ['color', 'size', 'material', 'pattern'];

    /** Cap the number of hasVariant nodes emitted, mirroring REVIEWS_LIMIT, to bound page weight. */
    protected const VARIANTS_LIMIT = 100;

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

        // Google's supported variant markup for configurables is ProductGroup + hasVariant;
        // when no usable variant exists, fall through to the flat Product graph.
        if ($product->getTypeId() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $group = $this->_getProductGroupData($product);
            if ($group !== []) {
                return $group;
            }
        }

        $data = $this->_getCommonData($product, 'Product');
        $this->_addIdentifierData($data, $product);

        $offers = $this->_getOffers($product);
        if ($offers !== []) {
            $data['offers'] = $offers;
        }

        $this->_addReviewData($data, $product);

        return $data;
    }

    /**
     * Fields shared by the Product and ProductGroup graphs.
     *
     * @return array<string, mixed>
     */
    protected function _getCommonData(Mage_Catalog_Model_Product $product, string $type): array
    {
        $data = [
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
            '@type' => $type,
            'name' => $product->getName(),
        ];

        $description = $this->_getDescription($product);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $images = $this->_getImages($product);
        if ($images !== []) {
            $data['image'] = $images;
        }

        $url = $product->getProductUrl();
        if ($url) {
            $data['@id'] = $url . '#product';
            $data['url'] = $url;
        }

        $brand = $this->_getBrand($product);
        if ($brand !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        return $data;
    }

    /**
     * sku, gtin and mpn for a Product, ProductGroup or variant node.
     *
     * @param array<string, mixed> $data
     */
    protected function _addIdentifierData(array &$data, Mage_Catalog_Model_Product $product): void
    {
        $helper = Mage::helper('structureddata');
        $store = $product->getStoreId();

        $sku = (string) $product->getSku();
        if ($sku !== '') {
            $data['sku'] = $sku;
        }

        $gtin = $this->_getMappedAttribute($product, $helper->getGtinAttribute($store));
        if ($gtin !== '') {
            [$gtinProperty, $gtinValue] = $helper->getGtinProperty($gtin);
            $data[$gtinProperty] = $gtinValue;
        }

        $mpn = $this->_getMappedAttribute($product, $helper->getMpnAttribute($store));
        if ($mpn !== '') {
            $data['mpn'] = $mpn;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function _addReviewData(array &$data, Mage_Catalog_Model_Product $product): void
    {
        if (!Mage::helper('structureddata')->includeReviews()) {
            return;
        }

        $rating = $this->_getAggregateRating($product);
        if ($rating !== []) {
            $data['aggregateRating'] = $rating;
        }

        $reviews = $this->_getReviews($product);
        if ($reviews !== []) {
            $data['review'] = $reviews;
        }
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
     * ProductGroup + hasVariant graph for a configurable product. Empty when no usable
     * variant exists.
     *
     * @return array<string, mixed>
     */
    protected function _getProductGroupData(Mage_Catalog_Model_Product $product): array
    {
        $helper = Mage::helper('structureddata');

        $attributeCodes = [];
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $attributesInfo = $typeInstance->getConfigurableAttributesAsArray($product);
        foreach ($attributesInfo as $attribute) {
            $attributeCodes[] = (string) $attribute['attribute_code'];
        }

        $currency = $helper->getCurrencyCode($product->getStoreId());
        $basePrice = (float) $product->getFinalPrice();
        $priceDeltas = $this->_getVariantPriceDeltas($attributesInfo, $basePrice);

        $sharedOffer = $this->_getSharedOfferFields($product, $currency);
        // The children's own special prices never reach the buyer either, so the validity of the
        // advertised price is the parent's: its special price is what $basePrice already reflects.
        $validUntil = $this->_getPriceValidUntil($product);
        $priceValidUntil = $validUntil !== ''
            ? $validUntil
            : $helper->getFallbackPriceValidUntil($product->getStoreId());

        $variants = [];
        foreach ($this->_getVariantProducts($product, $attributeCodes) as $child) {
            // Checkout charges the parent's price plus the selected options' price deltas; the
            // child's own price attribute never reaches the buyer, so it must not be advertised.
            $price = $basePrice;
            foreach ($attributeCodes as $code) {
                $price += $priceDeltas[$code][(string) $child->getData($code)] ?? 0.0;
            }

            $variant = $this->_getVariantData($child, $product, $attributeCodes, $sharedOffer, $price, $currency, $priceValidUntil);
            if ($variant !== []) {
                $variants[] = $variant;
                if (count($variants) >= self::VARIANTS_LIMIT) {
                    break;
                }
            }
        }
        if ($variants === []) {
            return [];
        }

        $data = $this->_getCommonData($product, 'ProductGroup');
        $this->_addIdentifierData($data, $product);

        $sku = (string) $product->getSku();
        if ($sku !== '') {
            $data['productGroupID'] = $sku;
        }

        $data['variesBy'] = array_map(
            static fn(string $code): string => in_array($code, self::VARIANT_PROPERTIES, true)
                ? Maho_StructuredData_Helper_Data::SCHEMA . $code
                : $code,
            $attributeCodes,
        );
        $data['hasVariant'] = $variants;

        $this->_addReviewData($data, $product);

        return $data;
    }

    /**
     * Enabled child products with the attributes the variant nodes need. The stock
     * getUsedProducts() collection selects only the configured child attributes, so a
     * dedicated collection is loaded here.
     *
     * @param array<int, string> $attributeCodes
     * @return array<int, Mage_Catalog_Model_Product>
     */
    protected function _getVariantProducts(Mage_Catalog_Model_Product $product, array $attributeCodes): array
    {
        $helper = Mage::helper('structureddata');
        $store = $product->getStoreId();

        $attributes = array_unique(array_merge(
            ['name', 'price'],
            array_filter([
                $helper->getGtinAttribute($store),
                $helper->getMpnAttribute($store),
                $helper->getConditionAttribute($store),
            ]),
            $attributeCodes,
        ));

        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        // The store filter is what limits the collection to the current website; without it,
        // super-link rows of children unassigned from this website would still come back.
        $typeInstance->setStoreFilter($product->getStore(), $product);
        $collection = $typeInstance->getUsedProductCollection($product)
            ->addAttributeToSelect($attributes)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addFilterByRequiredOptions()
            ->setPageSize(self::VARIANTS_LIMIT);

        return array_values(iterator_to_array($collection));
    }

    /**
     * Per-attribute option price deltas ([attribute_code][value_index] => delta) mirroring
     * Mage_Catalog_Model_Product_Type_Configurable_Price::_calcSelectionPrice().
     *
     * @param array<int, array<string, mixed>> $attributesInfo
     * @return array<string, array<string, float>>
     */
    protected function _getVariantPriceDeltas(array $attributesInfo, float $basePrice): array
    {
        $deltas = [];
        foreach ($attributesInfo as $attribute) {
            $code = (string) $attribute['attribute_code'];
            foreach ($attribute['values'] as $value) {
                $pricingValue = (float) ($value['pricing_value'] ?? 0);
                if ($pricingValue == 0.0) {
                    continue;
                }
                $deltas[$code][(string) $value['value_index']] = !empty($value['is_percent'])
                    ? $basePrice * $pricingValue / 100
                    : $pricingValue;
            }
        }
        return $deltas;
    }

    /**
     * @param array<int, string> $attributeCodes
     * @param array<string, mixed> $sharedOffer
     * @return array<string, mixed>
     */
    protected function _getVariantData(
        Mage_Catalog_Model_Product $child,
        Mage_Catalog_Model_Product $parent,
        array $attributeCodes,
        array $sharedOffer,
        float $price,
        string $currency,
        string $priceValidUntil,
    ): array {
        $helper = Mage::helper('structureddata');
        $child->setStoreId($parent->getStoreId());

        if ($price <= 0) {
            return [];
        }
        // Pin the buyer price so getFinalPrice()-based helpers (free-shipping threshold) agree
        // with the advertised price instead of reading the child's unused own price attribute.
        $child->setFinalPrice($price);

        $variant = ['@type' => 'Product'];

        $values = [];
        $additional = [];
        foreach ($attributeCodes as $code) {
            $value = $child->getAttributeText($code);
            $value = trim(is_array($value) ? implode(', ', $value) : (string) $value);
            if ($value === '') {
                continue;
            }
            $values[] = $value;
            if (in_array($code, self::VARIANT_PROPERTIES, true)) {
                $variant[$code] = $value;
            } else {
                $additional[] = ['@type' => 'PropertyValue', 'name' => $code, 'value' => $value];
            }
        }

        $name = trim((string) $child->getName());
        if ($name === '') {
            $name = trim((string) $parent->getName() . ($values !== [] ? ' (' . implode(', ', $values) . ')' : ''));
        }
        $variant['name'] = $name;

        $this->_addIdentifierData($variant, $child);

        if ($additional !== []) {
            $variant['additionalProperty'] = $additional;
        }

        $offer = [
            '@type' => 'Offer',
            'price' => $helper->formatPrice($helper->getDisplayPrice($child, $price)),
            'availability' => $helper->getAvailabilityUrl($child),
            'itemCondition' => $this->_getItemCondition($child),
            'priceValidUntil' => $priceValidUntil,
        ] + $sharedOffer;

        // Per child, not shared: the free-shipping threshold compares against the price of the
        // variant actually bought, so a cheap and an expensive variant can carry different rates.
        $shippingDetails = $this->_getShippingDetails($child, $currency);
        if ($shippingDetails !== []) {
            $offer['shippingDetails'] = $shippingDetails;
        }

        $variant['offers'] = $offer;

        return $variant;
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

        $prices = $this->_collectPrices($product);
        if ($prices === []) {
            return [];
        }

        $base = $this->_getSharedOfferFields($product, $currency);
        $base['availability'] = $helper->getAvailabilityUrl($product);
        $base['itemCondition'] = $this->_getItemCondition($product);

        $validUntil = $this->_getPriceValidUntil($product);
        $base['priceValidUntil'] = $validUntil !== ''
            ? $validUntil
            : $helper->getFallbackPriceValidUntil($product->getStoreId());

        $shippingDetails = $this->_getShippingDetails($product, $currency);
        if ($shippingDetails !== []) {
            $base['shippingDetails'] = $shippingDetails;
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
     * Offer fields shared by the flat offers node and every variant offer, so a new
     * offer-level property reaches both graphs from one place.
     *
     * @return array<string, mixed>
     */
    protected function _getSharedOfferFields(Mage_Catalog_Model_Product $product, string $currency): array
    {
        $helper = Mage::helper('structureddata');

        $fields = [
            'priceCurrency' => $currency,
            'seller' => $helper->getSellerData($product->getStoreId()),
        ];

        $url = $product->getProductUrl();
        if ($url) {
            $fields['url'] = $url;
        }

        $returnPolicy = $helper->getReturnPolicyData($product->getStoreId());
        if ($returnPolicy !== []) {
            $fields['hasMerchantReturnPolicy'] = $returnPolicy;
        }

        return $fields;
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
            $withTax = $helper->displayPriceIncludesTax($product->getStoreId());
            $min = (float) $store->convertPrice((float) $priceModel->getTotalPrices($product, 'min', $withTax));
            $max = (float) $store->convertPrice((float) $priceModel->getTotalPrices($product, 'max', $withTax));
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
        // Only an active special price gives the current price a real end date. An expired
        // special interval, or a to-date left behind without a special price, must not lend
        // its date to the regular price. A special that starts in the future instead caps the
        // regular price's validity at the day before it begins.
        if (!$product->getSpecialPrice()) {
            return '';
        }

        $specialFrom = (string) $product->getSpecialFromDate();
        $specialTo = (string) $product->getSpecialToDate();
        if ($specialTo === '' || str_starts_with($specialTo, '0000')) {
            $specialTo = '';
        }

        // special_from/to_date are store-local, date-only columns (like blog publish_date): emit
        // verbatim with no timezone conversion. Core's special-price logic uses the same
        // store-timezone interval check, so the emitted date matches when getFinalPrice() changes.
        try {
            if (Mage::app()->getLocale()->isStoreDateInInterval(
                $product->getStore(),
                $specialFrom !== '' ? $specialFrom : null,
                $specialTo !== '' ? $specialTo : null,
            )) {
                return $specialTo !== '' ? substr($specialTo, 0, 10) : '';
            }

            if ($specialFrom !== '' && !str_starts_with($specialFrom, '0000')) {
                $fromDay = substr($specialFrom, 0, 10);
                $today = Mage::app()->getLocale()->utcToStore($product->getStore())
                    ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                if ($fromDay > $today) {
                    return (new DateTimeImmutable($fromDay))->modify('-1 day')
                        ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        return '';
    }

    protected function _getItemCondition(Mage_Catalog_Model_Product $product): string
    {
        $helper = Mage::helper('structureddata');
        $value = $this->_getMappedAttribute($product, $helper->getConditionAttribute($product->getStoreId()));
        return $helper->mapConditionToSchemaUrl($value);
    }

    /**
     * Build the OfferShippingDetails node. Empty for virtual products and when no rate is
     * derivable or configured, so an invented rate is never advertised.
     *
     * @return array<string, mixed>
     */
    protected function _getShippingDetails(Mage_Catalog_Model_Product $product, string $currency): array
    {
        if ($product->getIsVirtual()) {
            return [];
        }

        $helper = Mage::helper('structureddata');
        $rate = $helper->getShippingRate($product);
        if ($rate === null) {
            return [];
        }

        // Google requires shippingDestination inside OfferShippingDetails, so without a
        // resolvable country list the node is worth less than no node.
        $countries = $helper->getShippingCountries($product);
        if ($countries === []) {
            return [];
        }

        $details = [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => $helper->formatPrice($rate),
                'currency' => $currency,
            ],
            'shippingDestination' => [
                '@type' => 'DefinedRegion',
                'addressCountry' => count($countries) === 1 ? $countries[0] : $countries,
            ],
        ];

        $deliveryTime = [];
        foreach ($helper->getDeliveryTimeConfig($product->getStoreId()) as $key => $range) {
            if ($range !== null) {
                $deliveryTime[$key === 'handling' ? 'handlingTime' : 'transitTime'] = [
                    '@type' => 'QuantitativeValue',
                    'minValue' => $range[0],
                    'maxValue' => $range[1],
                    'unitCode' => 'DAY',
                ];
            }
        }
        if ($deliveryTime !== []) {
            $details['deliveryTime'] = ['@type' => 'ShippingDeliveryTime'] + $deliveryTime;
        }

        return $details;
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
