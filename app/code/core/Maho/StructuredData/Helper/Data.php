<?php

/**
 * Structured data configuration accessors and shared schema helpers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Helper_Data extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Maho_StructuredData';

    public const XML_PATH_ENABLED = 'catalog/structured_data/enabled';
    public const XML_PATH_PRODUCT_INCLUDE_REVIEWS = 'catalog/structured_data/product/include_reviews';
    public const XML_PATH_PRODUCT_BRAND_ATTRIBUTE = 'catalog/structured_data/product/brand_attribute';
    public const XML_PATH_PRODUCT_GTIN_ATTRIBUTE = 'catalog/structured_data/product/gtin_attribute';
    public const XML_PATH_PRODUCT_MPN_ATTRIBUTE = 'catalog/structured_data/product/mpn_attribute';
    public const XML_PATH_PRODUCT_CONDITION_ATTRIBUTE = 'catalog/structured_data/product/condition_attribute';
    public const XML_PATH_ORGANIZATION_TYPE = 'catalog/structured_data/organization/type';
    public const XML_PATH_SHIPPING_RATE = 'catalog/structured_data/shipping/rate';
    public const XML_PATH_SHIPPING_CARRIERS = 'catalog/structured_data/shipping/carriers';
    public const XML_PATH_SHIPPING_HANDLING_MIN = 'catalog/structured_data/shipping/handling_min';
    public const XML_PATH_SHIPPING_HANDLING_MAX = 'catalog/structured_data/shipping/handling_max';
    public const XML_PATH_SHIPPING_TRANSIT_MIN = 'catalog/structured_data/shipping/transit_min';
    public const XML_PATH_SHIPPING_TRANSIT_MAX = 'catalog/structured_data/shipping/transit_max';
    public const XML_PATH_RETURNS_POLICY = 'catalog/structured_data/returns/policy';
    public const XML_PATH_RETURNS_DAYS = 'catalog/structured_data/returns/days';
    public const XML_PATH_RETURNS_FEES = 'catalog/structured_data/returns/fees';
    public const XML_PATH_RETURNS_METHOD = 'catalog/structured_data/returns/method';
    public const XML_PATH_RETURNS_COUNTRIES = 'catalog/structured_data/returns/countries';
    public const XML_PATH_WEIGHT_UNIT = Mage_Core_Model_Locale::XML_PATH_WEIGHT_UNIT;

    public const SCHEMA = 'https://schema.org/';

    /** Carriers that calculate a rate from the configuration and the database, with no call to an outside service. */
    public const DEFAULT_SHIPPING_CARRIERS = ['freeshipping', 'flatrate', 'tablerate'];

    /** UN/CEFACT codes for the weight units a store can declare, as Google expects them. */
    public const WEIGHT_UNIT_CODES = [
        Mage_Core_Model_Locale::WEIGHT_POUND => 'LBR',
        Mage_Core_Model_Locale::WEIGHT_KILOGRAM => 'KGM',
        Mage_Core_Model_Locale::WEIGHT_GRAM => 'GRM',
        Mage_Core_Model_Locale::WEIGHT_OUNCE => 'ONZ',
    ];

    /** @var array<int, string> social profile config paths (general business identity, shared across features) */
    public const SOCIAL_PATHS = [
        'general/social_profiles/facebook_url',
        'general/social_profiles/twitter_url',
        'general/social_profiles/instagram_url',
        'general/social_profiles/linkedin_url',
    ];

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    public function includeReviews(int|string|null $store = null): bool
    {
        // Reviews are a soft dependency: the module declares no dependency on Mage_Review, so guard
        // here (the single chokepoint for both aggregateRating and review nodes) to avoid calling
        // Mage::getModel('review/review') — which returns false and fatals — when it is disabled.
        if (!Mage::helper('core')->isModuleEnabled('Mage_Review')) {
            return false;
        }
        return Mage::getStoreConfigFlag(self::XML_PATH_PRODUCT_INCLUDE_REVIEWS, $store);
    }

    /**
     * Wrap a schema.org graph in a JSON-LD <script> tag. Single source of truth for the markup,
     * shared by the jsonld.phtml template and the listing-page observer. Uses JSON_HEX_TAG and
     * JSON_HEX_AMP so any literal "</script>" (or other "<", ">", "&") in admin- or
     * customer-controlled string data is escaped and cannot break out of the script element.
     *
     * @param array<string, mixed> $data
     */
    public function renderJsonLdScript(array $data): string
    {
        if ($data === []) {
            return '';
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Build a summary-format ItemList graph from a list of page URLs. Each entry links to the
     * detail page that carries the full markup (Product, BlogPosting, ...) — Google's recommended
     * format for listing pages — so it is reused across product, search and blog listings.
     *
     * @param array<int, string> $urls
     * @return array<string, mixed>
     */
    public function buildItemList(array $urls): array
    {
        $itemListElement = [];
        $position = 1;
        foreach ($urls as $url) {
            $url = (string) $url;
            if ($url === '') {
                continue;
            }

            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => $url,
            ];
            $position++;
        }

        if ($itemListElement === []) {
            return [];
        }

        return [
            '@context' => self::SCHEMA,
            '@type' => 'ItemList',
            'itemListElement' => $itemListElement,
        ];
    }

    public function getOrganizationName(int|string|null $store = null): string
    {
        $name = trim((string) Mage::getStoreConfig('general/store_information/name', $store));
        if ($name !== '') {
            return $name;
        }
        return (string) Mage::app()->getStore($store)->getFrontendName();
    }

    /**
     * Resolve the organization logo from the theme logo.
     */
    public function getOrganizationLogoUrl(int|string|null $store = null): string
    {
        $logoSrc = (string) Mage::getStoreConfig('design/header/logo_src', $store);
        if ($logoSrc !== '') {
            return (string) Mage::getDesign()->getSkinUrl($logoSrc);
        }

        return '';
    }

    /**
     * Build a publisher Organization node (name + logo) shared by the Article schema.
     *
     * @return array<string, mixed>
     */
    public function getPublisherData(int|string|null $store = null): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => $this->getOrganizationName($store),
        ];

        $logo = $this->getOrganizationLogoUrl($store);
        if ($logo !== '') {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        return $publisher;
    }

    public function getBrandAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_BRAND_ATTRIBUTE, $store));
    }

    public function getGtinAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_GTIN_ATTRIBUTE, $store));
    }

    public function getMpnAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_MPN_ATTRIBUTE, $store));
    }

    /**
     * Resolve a configured attribute code to its frontend (label) value.
     */
    public function getMappedAttributeValue(Mage_Catalog_Model_Product $product, string $attributeCode): string
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
     * Absolute URLs of the base image and the gallery images, the base image first. The original
     * media URL gives search engines the full resolution, which a resized copy does not.
     *
     * @return array<int, string>
     */
    public function getImageUrls(Mage_Catalog_Model_Product $product): array
    {
        $images = [];
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

    /**
     * @param array<int, array<string, mixed>> $attributesInfo from getConfigurableAttributesAsArray()
     * @return array<string, array<string, float>>
     */
    #[\Deprecated(message: 'use Mage_Catalog_Model_Product_Type_Configurable_Price::getVariantPriceDeltas()', since: '26.11')]
    public function getVariantPriceDeltas(array $attributesInfo, float $basePrice): array
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable_Price $priceModel */
        $priceModel = Mage_Catalog_Model_Product_Type::priceFactory(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        return $priceModel->getVariantPriceDeltas($attributesInfo, $basePrice);
    }

    public function getConditionAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_CONDITION_ATTRIBUTE, $store));
    }

    /**
     * The store weight unit as a UN/CEFACT code, or '' when the unit is unknown.
     */
    public function getWeightUnitCode(int|string|null $store = null): string
    {
        return self::WEIGHT_UNIT_CODES[Mage_Core_Model_Locale::getStoreWeightUnit($store)] ?? '';
    }

    /**
     * schema.org weight node for a product, the source Merchant Center reads for product_weight
     * when it crawls the page. Empty for a weightless product or an unknown store weight unit.
     *
     * @return array<string, mixed>
     */
    public function getWeightData(Mage_Catalog_Model_Product $product): array
    {
        // Nothing ships, so any leftover weight row (a simple product later turned virtual or
        // downloadable) must not be advertised as a shipping weight.
        if ($product->getIsVirtual()) {
            return [];
        }

        $weight = round((float) $product->getWeight(), 4);
        if ($weight <= 0) {
            return [];
        }

        $unitCode = $this->getWeightUnitCode($product->getStoreId());
        if ($unitCode === '') {
            return [];
        }

        return [
            '@type' => 'QuantitativeValue',
            'value' => $weight,
            'unitCode' => $unitCode,
        ];
    }

    /**
     * Map a merchant condition value (feed vocabulary: new/refurbished/used/damaged) to a
     * schema.org OfferItemCondition URL. Unknown or empty values fall back to NewCondition.
     */
    public function mapConditionToSchemaUrl(string $value): string
    {
        $condition = match (strtolower(trim($value))) {
            'refurbished', 'refurb', 'reconditioned' => 'RefurbishedCondition',
            'used', 'pre-owned', 'preowned', 'second hand', 'secondhand' => 'UsedCondition',
            'damaged' => 'DamagedCondition',
            default => 'NewCondition',
        };

        return self::SCHEMA . $condition;
    }

    /**
     * Resolve a raw GTIN to its length-specific schema.org property (gtin8/gtin12/gtin13/gtin14).
     * Other values keep the generic gtin property. The value is emitted as the merchant entered
     * it (no checksum validation): Merchant Center validates GTINs and reports failures, so it
     * stays the single feedback loop, consistent with the FeedManager projections.
     *
     * @return array{0: string, 1: string} property name and normalized value
     */
    public function getGtinProperty(string $value): array
    {
        $normalized = str_replace([' ', '-'], '', trim($value));
        if (ctype_digit($normalized) && in_array(strlen($normalized), [8, 12, 13, 14], true)) {
            return ['gtin' . strlen($normalized), $normalized];
        }

        return ['gtin', trim($value)];
    }

    /**
     * Seller Organization node for offers. Carries the same @id as the homepage Organization
     * graph so consuming agents merge both into one entity.
     *
     * @return array<string, string>
     */
    public function getSellerData(int|string|null $store = null): array
    {
        $baseUrl = Mage::app()->getStore($store)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);

        return [
            '@type' => 'Organization',
            '@id' => $baseUrl . '#organization',
            'name' => $this->getOrganizationName($store),
        ];
    }

    /**
     * The shipping rate to advertise for each destination country, in the current display currency.
     * A configured Shipping Rate applies to each store shipping country. Otherwise each country gets
     * the lowest rate that the carriers give for one unit of the product. A country without a rate
     * is left out, so an invented rate is never advertised.
     *
     * @return array<string, float> the rate for each country code
     */
    public function getShippingRates(Mage_Catalog_Model_Product $product): array
    {
        $store = Mage::app()->getStore($product->getStoreId());

        $override = trim((string) Mage::getStoreConfig(self::XML_PATH_SHIPPING_RATE, $store));
        $rates = $override !== '' && is_numeric($override)
            ? array_fill_keys($this->_getStoreShippingCountries($store), max(0.0, (float) $override))
            : $this->_collectCarrierRates($product);

        return array_map(static fn(float $rate): float => (float) $store->convertPrice($rate), $rates);
    }

    /**
     * The carriers that give the derived rate: the selected carriers, or DEFAULT_SHIPPING_CARRIERS
     * when no carrier is selected.
     *
     * @return array<int, string>
     */
    public function getShippingCarriers(int|string|Mage_Core_Model_Store|null $store = null): array
    {
        $carriers = array_values(array_filter(
            explode(',', (string) Mage::getStoreConfig(self::XML_PATH_SHIPPING_CARRIERS, $store)),
            static fn(string $carrier): bool => $carrier !== '',
        ));
        return $carriers !== [] ? $carriers : self::DEFAULT_SHIPPING_CARRIERS;
    }

    /**
     * Ask each active carrier for the rate of one unit of the product. Ask once for each country
     * that a carrier ships to, and keep the lowest rate of each country.
     *
     * @return array<string, float> the rate in base currency for each country code
     */
    protected function _collectCarrierRates(Mage_Catalog_Model_Product $product): array
    {
        $store = Mage::app()->getStore($product->getStoreId());
        $price = (float) $product->getFinalPrice();
        $weight = (float) $product->getWeight();
        $carriers = array_filter(
            $this->getShippingCarriers($store),
            static fn(string $carrier): bool => Mage::getStoreConfigFlag("carriers/{$carrier}/active", $store),
        );
        // A grouped product or a dynamic bundle has no weight, and Table Rates would give the rate of 0 kg.
        if ($weight <= 0 && $product->isComposite()) {
            $carriers = array_intersect($carriers, ['freeshipping', 'flatrate']);
        }

        $countries = [];
        foreach ($carriers as $carrier) {
            $countries = [...$countries, ...$this->_getCarrierShippingCountries($carrier, $store)];
        }

        $request = Mage::getModel('shipping/rate_request')
            ->setLimitCarrier(array_values($carriers))
            ->setAllItems([])
            ->setStoreId((int) $store->getId())
            ->setWebsiteId((int) $store->getWebsiteId())
            ->setBaseCurrency($store->getBaseCurrency())
            ->setPackageCurrency($store->getCurrentCurrency())
            ->setPackageQty(1.0)
            ->setPackageWeight($weight)
            ->setFreeMethodWeight($weight)
            ->setPackageValue($price)
            ->setPackageValueWithDiscount($price)
            ->setPackagePhysicalValue($price)
            ->setBaseSubtotalInclTax($price);
        $shipping = Mage::getModel('shipping/shipping');

        $rates = [];
        foreach (array_unique($countries) as $country) {
            $shipping->resetResult();
            $shipping->collectRates((clone $request)->setDestCountryId($country));
            $cheapest = $shipping->getResult()->getCheapestRate();
            if ($cheapest !== null) {
                $rates[$country] = max(0.0, (float) $cheapest->getPrice());
            }
        }

        return $rates;
    }

    /**
     * The countries that a carrier ships to: its "Ship to Applicable Countries" list when it has
     * one, else the store shipping countries.
     *
     * @return array<int, string>
     */
    protected function _getCarrierShippingCountries(string $carrier, Mage_Core_Model_Store $store): array
    {
        if (Mage::getStoreConfigFlag("carriers/{$carrier}/sallowspecific", $store)) {
            return $this->_parseCountryList((string) Mage::getStoreConfig("carriers/{$carrier}/specificcountry", $store));
        }
        return $this->_getStoreShippingCountries($store);
    }

    /**
     * The store shipping country list (general/country/allow_shipping), else the shipping origin.
     * The general allowed-countries list is not used: it is a list of allowed addresses that
     * defaults to the whole world, not a promise to ship there.
     *
     * @return array<int, string>
     */
    protected function _getStoreShippingCountries(Mage_Core_Model_Store $store): array
    {
        $countries = $this->_parseCountryList((string) Mage::getStoreConfig('general/country/allow_shipping', $store));
        if ($countries !== []) {
            return $countries;
        }

        $origin = trim((string) Mage::getStoreConfig('shipping/origin/country_id', $store));
        return $origin !== '' ? [$origin] : [];
    }

    /**
     * Configured handling/transit day ranges. Each entry is [min, max] or null when not fully set.
     *
     * @return array{handling: array{0: int, 1: int}|null, transit: array{0: int, 1: int}|null}
     */
    public function getDeliveryTimeConfig(int|string|null $store = null): array
    {
        return [
            'handling' => $this->_parseDayRange(self::XML_PATH_SHIPPING_HANDLING_MIN, self::XML_PATH_SHIPPING_HANDLING_MAX, $store),
            'transit' => $this->_parseDayRange(self::XML_PATH_SHIPPING_TRANSIT_MIN, self::XML_PATH_SHIPPING_TRANSIT_MAX, $store),
        ];
    }

    /**
     * Resolved MerchantReturnPolicy inputs, or [] when no policy should be emitted. In auto mode
     * the EU withdrawal settings (Maho_Revocation) drive the policy; without them a stock install
     * advertises nothing rather than a policy that does not exist.
     *
     * @return array<string, mixed>
     */
    public function getReturnPolicyData(int|string|null $store = null): array
    {
        $policy = (string) Mage::getStoreConfig(self::XML_PATH_RETURNS_POLICY, $store);
        $days = 0;

        if ($policy === 'auto') {
            if (!Mage::helper('core')->isModuleEnabled('Maho_Revocation')) {
                return [];
            }
            /** @var Maho_Revocation_Helper_Data $revocation */
            $revocation = Mage::helper('revocation');
            if (!$revocation->isEnabled($store)) {
                return [];
            }
            $days = $revocation->getCoolingOffDays($store);
            if ($days <= 0) {
                return [];
            }
            $policy = 'finite';
        } elseif ($policy === 'finite') {
            $days = max(1, (int) Mage::getStoreConfig(self::XML_PATH_RETURNS_DAYS, $store));
        } elseif ($policy !== 'unlimited' && $policy !== 'not_permitted') {
            return [];
        }

        $data = [
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => self::SCHEMA . match ($policy) {
                'finite' => 'MerchantReturnFiniteReturnWindow',
                'unlimited' => 'MerchantReturnUnlimitedWindow',
                'not_permitted' => 'MerchantReturnNotPermitted',
            },
        ];

        // applicableCountry is the buyer's country, so prefer the ship-to list over the
        // merchant's own location.
        $countries = $this->_parseCountryList((string) Mage::getStoreConfig(self::XML_PATH_RETURNS_COUNTRIES, $store));
        if ($countries === []) {
            $countries = $this->_parseCountryList((string) Mage::getStoreConfig('general/country/allow_shipping', $store));
        }
        if ($countries === []) {
            $country = trim((string) Mage::getStoreConfig('general/store_information/merchant_country', $store));
            if ($country === '') {
                $country = trim((string) Mage::getStoreConfig('shipping/origin/country_id', $store));
            }
            $countries = $country !== '' ? [$country] : [];
        }
        if ($countries !== []) {
            $data['applicableCountry'] = count($countries) === 1 ? $countries[0] : $countries;
        }

        if ($policy === 'not_permitted') {
            return $data;
        }

        if ($policy === 'finite') {
            $data['merchantReturnDays'] = $days;
        }

        $data['returnMethod'] = self::SCHEMA . match ((string) Mage::getStoreConfig(self::XML_PATH_RETURNS_METHOD, $store)) {
            'in_store' => 'ReturnInStore',
            'at_kiosk' => 'ReturnAtKiosk',
            default => 'ReturnByMail',
        };
        $data['returnFees'] = self::SCHEMA . match ((string) Mage::getStoreConfig(self::XML_PATH_RETURNS_FEES, $store)) {
            'free_return' => 'FreeReturn',
            default => 'ReturnFeesCustomerResponsibility',
        };

        return $data;
    }

    /**
     * Fallback priceValidUntil (store-local today + 30 days) for offers without a special-price
     * end date: an "at least until" claim any stable-priced catalog satisfies, and fresh enough
     * for cached pages.
     */
    public function getFallbackPriceValidUntil(int|string|null $store = null): string
    {
        return Mage::app()->getLocale()
            ->utcToStore(Mage::app()->getStore($store))
            ->modify('+30 days')
            ->format(Mage_Core_Model_Locale::DATE_FORMAT);
    }

    /**
     * Whether catalog prices are displayed including tax (tax/display/type incl. or both).
     */
    public function displayPriceIncludesTax(int|string|null $store = null): bool
    {
        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        return (int) $taxHelper->getPriceDisplayType($store) !== Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX;
    }

    /**
     * @return array<int, string>
     */
    protected function _parseCountryList(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn(string $country): bool => $country !== '',
        ));
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    protected function _parseDayRange(string $minPath, string $maxPath, int|string|null $store): ?array
    {
        $min = trim((string) Mage::getStoreConfig($minPath, $store));
        $max = trim((string) Mage::getStoreConfig($maxPath, $store));
        if (!is_numeric($min) || !is_numeric($max)) {
            return null;
        }

        $minDays = max(0, (int) $min);
        return [$minDays, max($minDays, (int) $max)];
    }

    /**
     * Map a product's stock state to a schema.org ItemAvailability URL.
     */
    public function getAvailabilityUrl(Mage_Catalog_Model_Product $product): string
    {
        if (!$product->isSaleable()) {
            return self::SCHEMA . 'OutOfStock';
        }

        $stockItem = $product->getStockItem();
        if (!$stockItem) {
            // Saleable with no managed stock (e.g. virtual/downloadable): treat as in stock.
            return self::SCHEMA . 'InStock';
        }

        if (!$stockItem->getIsInStock()) {
            return self::SCHEMA . 'OutOfStock';
        }

        // Stock not managed by quantity: rely on the in-stock flag only.
        if (!$stockItem->getManageStock()) {
            return self::SCHEMA . 'InStock';
        }

        // Composite products (configurable/grouped/bundle) track stock on their children; the parent
        // stock row's qty is forced to 0, so the qty-based checks below would wrongly report a
        // saleable, in-stock parent as out of stock. isSaleable() + the in-stock flag above already
        // reflect child availability for these types.
        if ($product->isComposite()) {
            return self::SCHEMA . 'InStock';
        }

        $qty = (float) $stockItem->getQty();

        if ($qty <= 0) {
            if ((int) $stockItem->getBackorders() > 0) {
                return self::SCHEMA . 'BackOrder';
            }
            return self::SCHEMA . 'OutOfStock';
        }

        // Reuse the inventory low-stock threshold (cataloginventory/item_options/notify_stock_qty),
        // which the stock item resolves per-product with fallback to the global default.
        $threshold = (float) $stockItem->getNotifyStockQty();
        if ($threshold > 0 && $qty <= $threshold) {
            return self::SCHEMA . 'LimitedAvailability';
        }

        return self::SCHEMA . 'InStock';
    }

    /**
     * Display price (current currency) for a saleable amount, following the catalog tax display
     * mode so the emitted price matches the price shown on the page.
     */
    public function getDisplayPrice(Mage_Catalog_Model_Product $product, float $price): float
    {
        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        $displayPrice = (float) $taxHelper->getPrice($product, $price, $this->displayPriceIncludesTax($product->getStoreId()));

        return (float) Mage::app()->getStore($product->getStoreId())->convertPrice($displayPrice);
    }

    /**
     * Format a price as a fixed-2-decimal string, the form Google expects.
     */
    public function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    public function getCurrencyCode(int|string|null $store = null): string
    {
        return Mage::app()->getStore($store)->getCurrentCurrencyCode();
    }

    /**
     * Convert a stored UTC datetime (e.g. created_at, updated_at) to an ISO-8601 string in the
     * store timezone. Returns '' for empty, zero ("0000-...") or unparseable input.
     */
    public function formatUtcDateTime(string $value, int|string|null $store = null): string
    {
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }

        try {
            return Mage::app()->getLocale()
                ->utcToStore(Mage::app()->getStore($store), $value)
                ->format('c');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Reduce an HTML fragment to single-line plain text: strip tags, decode entities (so
     * "Tom &amp; Jerry" becomes "Tom & Jerry"), then collapse runs of whitespace.
     */
    public function toPlainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Configured social profile URLs (sameAs).
     *
     * @return array<int, string>
     */
    public function getSocialProfiles(int|string|null $store = null): array
    {
        $profiles = [];
        foreach (self::SOCIAL_PATHS as $path) {
            $url = trim((string) Mage::getStoreConfig($path, $store));
            // Only emit valid http(s) URLs; reject javascript: and other unsafe schemes.
            if ($url !== '' && Mage::helper('core')->isValidUrl($url)) {
                $profiles[] = $url;
            }
        }
        return $profiles;
    }
}
