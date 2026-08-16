<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Integration coverage for the Maho_StructuredData JSON-LD blocks (issue #483).
 *
 * Exercises the real config helper, layout/block factory and product type models so the
 * generated schema.org graph is validated end-to-end rather than against mocked data.
 */

/**
 * Decode the JSON-LD payload from a rendered <script type="application/ld+json"> block.
 *
 * @return array<string, mixed>|null
 */
function decodeJsonLd(string $html): ?array
{
    if (trim($html) === '') {
        return null;
    }
    $json = preg_replace('/^.*?>|<\/script>\s*$/s', '', trim($html));
    return json_decode((string) $json, true);
}

/**
 * Load one enabled product of the given type from the sample-data catalog, scoped to the
 * current store, or null when the catalog has none.
 */
function sdLoadProduct(string $typeId): ?Mage_Catalog_Model_Product
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('type_id', $typeId)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize(1)
        ->getFirstItem();

    if (!$product->getId()) {
        return null;
    }
    $product->setStoreId(Mage::app()->getStore()->getId());
    return $product;
}

/**
 * Render the product JSON-LD block for a registered product and decode the graph.
 *
 * @return array<string, mixed>|null
 */
function sdRenderProductJsonLd(Mage_Catalog_Model_Product $product): ?array
{
    Mage::unregister('current_product');
    Mage::register('current_product', $product);
    try {
        return decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml(),
        );
    } finally {
        Mage::unregister('current_product');
    }
}

/**
 * Pin the carrier and structured-data shipping config to known values so the derived
 * shippingDetails node is deterministic regardless of the installed sample config.
 */
function sdConfigureShipping(array $values = []): void
{
    $store = Mage::app()->getStore();
    $defaults = [
        'carriers/flatrate/active' => '1',
        'carriers/flatrate/price' => '5.00',
        'carriers/flatrate/handling_fee' => '',
        'carriers/flatrate/sallowspecific' => '0',
        'carriers/flatrate/specificcountry' => '',
        'carriers/freeshipping/active' => '0',
        'carriers/freeshipping/free_shipping_subtotal' => '',
        'carriers/freeshipping/sallowspecific' => '0',
        'shipping/origin/country_id' => 'US',
        'general/country/allow_shipping' => 'US',
        Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_RATE => '',
        Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_HANDLING_MIN => '',
        Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_HANDLING_MAX => '',
        Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_TRANSIT_MIN => '',
        Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_TRANSIT_MAX => '',
    ];
    foreach ([...$defaults, ...$values] as $path => $value) {
        $store->setConfig($path, $value);
    }
}

beforeEach(function () {
    $this->helper = Mage::helper('structureddata');
});

describe('config helper', function () {
    test('availability maps to full schema.org URLs', function () {
        $schema = Maho_StructuredData_Helper_Data::SCHEMA;

        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());

        // Sample-data simple products ship enabled and in stock.
        expect($this->helper->getAvailabilityUrl($product))->toBe($schema . 'InStock');

        // Disabling the product makes it not saleable, so it reports out of stock.
        $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
        expect($this->helper->getAvailabilityUrl($product))->toBe($schema . 'OutOfStock');
    });

    test('price formatting always yields two decimals', function () {
        expect($this->helper->formatPrice(19.5))->toBe('19.50');
        expect($this->helper->formatPrice(190.0))->toBe('190.00');
    });

    test('social profiles only include configured non-empty URLs', function () {
        Mage::app()->getStore()->setConfig('general/social_profiles/facebook_url', 'https://facebook.com/demo');
        Mage::app()->getStore()->setConfig('general/social_profiles/twitter_url', '');
        expect($this->helper->getSocialProfiles())->toBe(['https://facebook.com/demo']);
    });
});

describe('Organization block', function () {
    test('renders an Organization graph with name and url', function () {
        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_organization')->toHtml(),
        );

        expect($data)->not->toBeNull();
        expect($data['@type'])->toBe('OnlineStore');
        expect($data['@context'])->toBe('https://schema.org/');
        expect($data['name'])->not->toBeEmpty();
        expect($data['url'])->toStartWith('http');
    });

    test('outputs nothing when disabled', function () {
        Mage::app()->getStore()->setConfig('catalog/structured_data/enabled', '0');
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_organization')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('Website block', function () {
    test('renders a WebSite graph with a SearchAction', function () {
        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_website')->toHtml(),
        );

        expect($data['@type'])->toBe('WebSite');
        expect($data['potentialAction']['@type'])->toBe('SearchAction');
        expect($data['potentialAction']['query-input'])->toBe('required name=search_term_string');
        expect($data['potentialAction']['target']['urlTemplate'])->toContain('{search_term_string}');
    });
});

describe('Product block', function () {
    beforeEach(function () {
        Mage::unregister('current_product');
    });

    afterEach(function () {
        Mage::unregister('current_product');
    });

    test('renders a valid Product graph for a simple product', function () {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());
        Mage::register('current_product', $product);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml(),
        );

        expect($data['@type'])->toBe('Product');
        expect($data['name'])->not->toBeEmpty();
        // Google requires name + at least one of offers/review/aggregateRating.
        expect($data)->toHaveKey('offers');
        expect($data['offers']['@type'])->toBe('Offer');
        expect($data['offers']['price'])->toMatch('/^\d+\.\d{2}$/');
        expect($data['offers']['priceCurrency'])->not->toBeEmpty();
        expect($data['offers']['availability'])->toStartWith('https://schema.org/');
    });

    test('uses AggregateOffer for grouped products', function () {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'grouped')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No grouped product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());
        Mage::register('current_product', $product);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml(),
        );

        expect($data['offers']['@type'])->toBe('AggregateOffer');
        expect((float) $data['offers']['lowPrice'])->toBeLessThanOrEqual((float) $data['offers']['highPrice']);
        expect($data['offers']['offerCount'])->toBeGreaterThan(1);
    });

    test('renders nothing without a current product', function () {
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('Article (blog) block', function () {
    beforeEach(function () {
        Mage::unregister('current_blog_post');
    });

    afterEach(function () {
        Mage::unregister('current_blog_post');
    });

    test('renders a BlogPosting graph for a blog post', function () {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $this->markTestSkipped('Maho_Blog module is not enabled.');
        }

        $post = Mage::getModel('blog/post')->getCollection()->setPageSize(1)->getFirstItem();
        if (!$post->getId()) {
            $this->markTestSkipped('No blog post in catalog.');
        }
        Mage::register('current_blog_post', $post);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_article')->toHtml(),
        );

        expect($data['@type'])->toBe('BlogPosting');
        expect($data['headline'])->not->toBeEmpty();
        expect($data['url'])->toStartWith('http');
        expect($data['mainEntityOfPage']['@id'])->toBe($data['url']);
        expect($data['publisher']['@type'])->toBe('Organization');
        expect($data['author'])->toHaveKey('name');
        expect($data['inLanguage'])->toMatch('/^[a-z]{2,3}(-[A-Za-z0-9]+)*$/');
    });

    test('renders nothing without a current blog post', function () {
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_article')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('condition and gtin mapping', function () {
    test('condition values map to schema.org URLs with NewCondition as fallback', function () {
        $schema = Maho_StructuredData_Helper_Data::SCHEMA;

        expect($this->helper->mapConditionToSchemaUrl('new'))->toBe($schema . 'NewCondition');
        expect($this->helper->mapConditionToSchemaUrl('Refurbished'))->toBe($schema . 'RefurbishedCondition');
        expect($this->helper->mapConditionToSchemaUrl('used'))->toBe($schema . 'UsedCondition');
        expect($this->helper->mapConditionToSchemaUrl('pre-owned'))->toBe($schema . 'UsedCondition');
        expect($this->helper->mapConditionToSchemaUrl('damaged'))->toBe($schema . 'DamagedCondition');
        expect($this->helper->mapConditionToSchemaUrl('mint'))->toBe($schema . 'NewCondition');
        expect($this->helper->mapConditionToSchemaUrl(''))->toBe($schema . 'NewCondition');
    });

    test('gtin values resolve to the length-specific property', function () {
        expect($this->helper->getGtinProperty('4006381333931'))->toBe(['gtin13', '4006381333931']);
        expect($this->helper->getGtinProperty('4006381333931 '))->toBe(['gtin13', '4006381333931']);
        expect($this->helper->getGtinProperty('4-006381-333931'))->toBe(['gtin13', '4006381333931']);
        expect($this->helper->getGtinProperty('96385074'))->toBe(['gtin8', '96385074']);
        expect($this->helper->getGtinProperty('036000291452'))->toBe(['gtin12', '036000291452']);
        expect($this->helper->getGtinProperty('00012345678905'))->toBe(['gtin14', '00012345678905']);
        // Non-standard lengths and non-digit values keep the generic property; the value is
        // emitted as entered, and Merchant Center stays the validator.
        expect($this->helper->getGtinProperty('097522980X'))->toBe(['gtin', '097522980X']);
        expect($this->helper->getGtinProperty('12345'))->toBe(['gtin', '12345']);
    });

    test('a mapped condition attribute drives itemCondition', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        Mage::app()->getStore()->setConfig(
            Maho_StructuredData_Helper_Data::XML_PATH_PRODUCT_CONDITION_ATTRIBUTE,
            'mpn',
        );
        $product->setData('mpn', 'used');

        $data = sdRenderProductJsonLd($product);

        expect($data['offers']['itemCondition'])->toBe('https://schema.org/UsedCondition');
    });
});

describe('offer completeness', function () {
    test('a simple product offer carries seller, condition, validity and shipping by default', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        Mage::app()->getStore()->setConfig(
            Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY,
            'disabled',
        );

        $data = sdRenderProductJsonLd($product);
        $offer = $data['offers'];

        expect($offer['itemCondition'])->toBe('https://schema.org/NewCondition');
        expect($offer['seller']['@type'])->toBe('Organization');
        expect($offer['seller']['@id'])->toEndWith('#organization');
        expect($offer['seller']['name'])->not->toBeEmpty();
        expect($offer['priceValidUntil'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
        expect($offer['shippingDetails']['@type'])->toBe('OfferShippingDetails');
        expect($offer['shippingDetails']['shippingRate']['value'])->toBe('5.00');
        expect($offer['shippingDetails']['shippingRate']['currency'])->toBe($offer['priceCurrency']);
        expect($offer['shippingDetails']['shippingDestination']['addressCountry'])->toBe('US');
        expect($offer['shippingDetails'])->not->toHaveKey('deliveryTime');
        expect($offer)->not->toHaveKey('hasMerchantReturnPolicy');
        expect($data['@id'])->toBe($data['url'] . '#product');
    });

    test('the carrier ship-to restriction drives the destination countries', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            'carriers/flatrate/sallowspecific' => '1',
            'carriers/flatrate/specificcountry' => 'DE,AT',
            Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_HANDLING_MIN => '0',
            Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_HANDLING_MAX => '1',
            Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_TRANSIT_MIN => '2',
            Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_TRANSIT_MAX => '5',
        ]);

        $details = sdRenderProductJsonLd($product)['offers']['shippingDetails'];

        expect($details['shippingRate']['value'])->toBe('5.00');
        expect($details['shippingDestination']['addressCountry'])->toBe(['DE', 'AT']);
        expect($details['deliveryTime']['handlingTime'])->toBe([
            '@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY',
        ]);
        expect($details['deliveryTime']['transitTime']['minValue'])->toBe(2);
        expect($details['deliveryTime']['transitTime']['maxValue'])->toBe(5);
    });

    test('the rate override ignores the carrier restriction and uses the store shipping countries', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            Maho_StructuredData_Helper_Data::XML_PATH_SHIPPING_RATE => '9.99',
            'carriers/flatrate/sallowspecific' => '1',
            'carriers/flatrate/specificcountry' => 'DE',
        ]);

        $details = sdRenderProductJsonLd($product)['offers']['shippingDetails'];

        expect($details['shippingRate']['value'])->toBe('9.99');
        expect($details['shippingDestination']['addressCountry'])->toBe('US');
    });

    test('the shipping country list falls back to the shipping origin', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            'general/country/allow_shipping' => '',
            'shipping/origin/country_id' => 'IT',
        ]);

        expect(sdRenderProductJsonLd($product)['offers']['shippingDetails']['shippingDestination']['addressCountry'])
            ->toBe('IT');
    });

    test('no shippingDetails is emitted when no destination is resolvable', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            'general/country/allow_shipping' => '',
            'shipping/origin/country_id' => '',
        ]);

        expect(sdRenderProductJsonLd($product)['offers'])->not->toHaveKey('shippingDetails');
    });

    test('free shipping above the threshold yields a zero rate', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            'carriers/freeshipping/active' => '1',
            'carriers/freeshipping/free_shipping_subtotal' => '0.01',
        ]);

        expect(sdRenderProductJsonLd($product)['offers']['shippingDetails']['shippingRate']['value'])->toBe('0.00');
    });

    test('no shippingDetails is emitted when no rate is derivable', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping([
            'carriers/flatrate/active' => '0',
        ]);

        expect(sdRenderProductJsonLd($product)['offers'])->not->toHaveKey('shippingDetails');
    });

    test('an active special price emits its end date verbatim as priceValidUntil', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        $future = Mage::app()->getLocale()->utcToStore()->modify('+5 days')
            ->format(Mage_Core_Model_Locale::DATE_FORMAT);
        $product->setSpecialPrice(1.00);
        $product->setSpecialFromDate(null);
        $product->setSpecialToDate($future . ' 00:00:00');

        expect(sdRenderProductJsonLd($product)['offers']['priceValidUntil'])->toBe($future);
    });

    test('a special end date without a special price is ignored', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        $future = Mage::app()->getLocale()->utcToStore()->modify('+5 days')
            ->format(Mage_Core_Model_Locale::DATE_FORMAT);
        $product->setSpecialPrice(null);
        $product->setSpecialToDate($future . ' 00:00:00');

        $expected = Mage::app()->getLocale()->utcToStore()->modify('+30 days')
            ->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect(sdRenderProductJsonLd($product)['offers']['priceValidUntil'])->toBe($expected);
    });

    test('a special price that starts in the future caps validity at the day before it begins', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        $locale = Mage::app()->getLocale();
        $product->setSpecialPrice(1.00);
        $product->setSpecialFromDate($locale->utcToStore()->modify('+10 days')->format(Mage_Core_Model_Locale::DATE_FORMAT));
        $product->setSpecialToDate($locale->utcToStore()->modify('+20 days')->format(Mage_Core_Model_Locale::DATE_FORMAT));

        $expected = $locale->utcToStore()->modify('+9 days')->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect(sdRenderProductJsonLd($product)['offers']['priceValidUntil'])->toBe($expected);
    });

    test('a missing special_to_date falls back to today plus 30 days', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        $product->setSpecialToDate(null);

        $expected = Mage::app()->getLocale()->utcToStore()->modify('+30 days')
            ->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect(sdRenderProductJsonLd($product)['offers']['priceValidUntil'])->toBe($expected);
    });

    test('an AggregateOffer carries the shared offer fields too', function () {
        $product = sdLoadProduct('grouped');
        if (!$product) {
            $this->markTestSkipped('No grouped product in catalog.');
        }
        sdConfigureShipping();

        $offer = sdRenderProductJsonLd($product)['offers'];
        if ($offer['@type'] === 'Offer') {
            $this->markTestSkipped('Catalog product collapsed to a single price point.');
        }

        expect($offer['@type'])->toBe('AggregateOffer');
        expect($offer['seller']['name'])->not->toBeEmpty();
        expect($offer['shippingDetails']['shippingRate']['value'])->toBe('5.00');
        expect($offer['priceValidUntil'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    test('required and recommended fields render for every product type in the catalog', function () {
        sdConfigureShipping();
        $covered = 0;

        foreach (['simple', 'configurable', 'grouped', 'bundle', 'virtual', 'downloadable'] as $typeId) {
            $product = sdLoadProduct($typeId);
            if (!$product) {
                continue;
            }
            $covered++;

            $data = sdRenderProductJsonLd($product);
            expect($data)->not->toBeNull("no JSON-LD for {$typeId}");
            expect($data['name'])->not->toBeEmpty();

            if (($data['@type'] ?? '') === 'ProductGroup') {
                expect($data['hasVariant'])->not->toBeEmpty();
                $offer = $data['hasVariant'][0]['offers'];
                expect($offer['price'])->toMatch('/^\d+\.\d{2}$/');
            } else {
                expect($data)->toHaveKey('offers');
                $offer = $data['offers'];
                expect($offer[$offer['@type'] === 'Offer' ? 'price' : 'lowPrice'])->toMatch('/^\d+\.\d{2}$/');
            }

            expect($offer['priceCurrency'])->not->toBeEmpty();
            expect($offer['availability'])->toStartWith('https://schema.org/');
            expect($offer['itemCondition'])->toStartWith('https://schema.org/');
            expect($offer['priceValidUntil'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');

            if ($product->getIsVirtual()) {
                expect($offer)->not->toHaveKey('shippingDetails');
            } else {
                expect($offer['shippingDetails']['shippingRate']['value'])->toBe('5.00');
            }
        }

        expect($covered)->toBeGreaterThan(0);
    });

    test('a configurable product renders a ProductGroup with variant offers', function () {
        $product = sdLoadProduct('configurable');
        if (!$product) {
            $this->markTestSkipped('No configurable product in catalog.');
        }
        sdConfigureShipping();

        $data = sdRenderProductJsonLd($product);
        if ($data['@type'] !== 'ProductGroup') {
            $this->markTestSkipped('Catalog configurable has no usable variants.');
        }

        expect($data['productGroupID'])->not->toBeEmpty();
        expect($data['variesBy'])->not->toBeEmpty();
        expect($data['hasVariant'])->not->toBeEmpty();
        expect($data['@id'])->toBe($data['url'] . '#product');

        $variant = $data['hasVariant'][0];
        expect($variant['@type'])->toBe('Product');
        expect($variant['name'])->not->toBeEmpty();
        expect($variant['sku'])->not->toBeEmpty();
        expect($variant['offers']['@type'])->toBe('Offer');
        expect($variant['offers']['price'])->toMatch('/^\d+\.\d{2}$/');
        expect($variant['offers']['availability'])->toStartWith('https://schema.org/');
        expect($variant['offers']['seller']['@id'])->toEndWith('#organization');
        expect($variant['offers']['shippingDetails']['shippingRate']['value'])->toBe('5.00');

        // Every configurable axis appears either as a schema property or as variesBy input.
        foreach ($data['variesBy'] as $axis) {
            expect($axis)->not->toBeEmpty();
        }
    });
});

describe('return policy', function () {
    test('auto mode emits nothing when the withdrawal channel is off', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'auto');
        $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_ENABLED, '0');

        expect($this->helper->getReturnPolicyData())->toBe([]);
    });

    test('a finite policy renders the full node', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'finite');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_DAYS, '30');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_FEES, 'free_return');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_COUNTRIES, 'DE');

        expect($this->helper->getReturnPolicyData())->toBe([
            '@type' => 'MerchantReturnPolicy',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'applicableCountry' => 'DE',
            'merchantReturnDays' => 30,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
        ]);
    });

    test('not_permitted omits days, method and fees', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'not_permitted');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_COUNTRIES, 'US');

        $data = $this->helper->getReturnPolicyData();

        expect($data['returnPolicyCategory'])->toBe('https://schema.org/MerchantReturnNotPermitted');
        expect($data)->not->toHaveKey('merchantReturnDays');
        expect($data)->not->toHaveKey('returnMethod');
        expect($data)->not->toHaveKey('returnFees');
    });

    test('auto mode follows the Right of Withdrawal settings', function () {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Revocation')) {
            $this->markTestSkipped('Maho_Revocation module is not enabled.');
        }
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'auto');
        $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_ENABLED, '1');
        $store->setConfig(Maho_Revocation_Helper_Data::XML_PATH_COOLING_OFF_DAYS, '14');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_COUNTRIES, 'DE');

        $data = $this->helper->getReturnPolicyData();

        expect($data['returnPolicyCategory'])->toBe('https://schema.org/MerchantReturnFiniteReturnWindow');
        expect($data['merchantReturnDays'])->toBe(14);
        expect($data['returnFees'])->toBe('https://schema.org/ReturnFeesCustomerResponsibility');
    });

    test('disabled emits nothing even with data configured', function () {
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'disabled');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_DAYS, '30');

        expect($this->helper->getReturnPolicyData())->toBe([]);
    });

    test('a configured policy reaches the offer node', function () {
        $product = sdLoadProduct('simple');
        if (!$product) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        sdConfigureShipping();
        $store = Mage::app()->getStore();
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_POLICY, 'finite');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_DAYS, '30');
        $store->setConfig(Maho_StructuredData_Helper_Data::XML_PATH_RETURNS_COUNTRIES, 'US');

        $policy = sdRenderProductJsonLd($product)['offers']['hasMerchantReturnPolicy'];

        expect($policy['@type'])->toBe('MerchantReturnPolicy');
        expect($policy['merchantReturnDays'])->toBe(30);
    });
});

describe('graph identifiers', function () {
    test('Organization and WebSite share the homepage anchor', function () {
        $organization = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_organization')->toHtml(),
        );
        $website = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_website')->toHtml(),
        );

        expect($organization['@id'])->toEndWith('#organization');
        expect($website['@id'])->toEndWith('#website');
        expect($website['publisher']['@id'])->toBe($organization['@id']);
    });
});

describe('Breadcrumbs block', function () {
    test('requires at least two crumbs', function () {
        $layout = Mage::app()->getLayout();
        $crumbs = $layout->createBlock('page/html_breadcrumbs', 'breadcrumbs');
        $crumbs->addCrumb('home', ['label' => 'Home', 'link' => 'http://maho.test/']);

        $block = $layout->createBlock('structureddata/jsonld_breadcrumbs');
        expect(trim($block->toHtml()))->toBe('');

        $crumbs->addCrumb('product', ['label' => 'A Product']);
        $data = decodeJsonLd($block->toHtml());

        expect($data['@type'])->toBe('BreadcrumbList');
        expect($data['itemListElement'])->toHaveCount(2);
        expect($data['itemListElement'][0]['position'])->toBe(1);
        expect($data['itemListElement'][0])->toHaveKey('item');
        // Last crumb has no link, so the "item" property is omitted.
        expect($data['itemListElement'][1])->not->toHaveKey('item');
    });
});
