<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function llmsModel(): Mage_Sitemap_Model_Llms
{
    /** @var Mage_Sitemap_Model_Llms $model */
    $model = Mage::getSingleton('sitemap/llms');
    return $model;
}

function configureLlms(array $values = []): void
{
    $store = Mage::app()->getStore();
    $defaults = [
        Mage_Sitemap_Model_Llms::XML_PATH_ENABLED => '1',
        Mage_Sitemap_Model_Llms::XML_PATH_DESCRIPTION => '',
        Mage_Sitemap_Model_Llms::XML_PATH_CUSTOM => '',
        Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME => '',
        'design/head/default_description' => '',
    ];
    foreach ([...$defaults, ...$values] as $path => $value) {
        $store->setConfig($path, $value);
    }
}

beforeEach(function () {
    configureLlms();
});

describe('generated file', function () {
    test('the heading uses the configured store name', function () {
        configureLlms([Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME => "Maho  Demo\nStore"]);

        expect(llmsModel()->generate())->toStartWith("# Maho Demo Store\n");
    });

    test('the heading falls back to the store group name', function () {
        $expected = trim((string) preg_replace('/\s+/', ' ', (string) Mage::app()->getStore()->getGroup()->getName()));

        expect(llmsModel()->generate())->toStartWith("# {$expected}\n");
    });

    test('the description renders as a blockquote', function () {
        configureLlms([Mage_Sitemap_Model_Llms::XML_PATH_DESCRIPTION => "Fine goods.\nShipped fast."]);

        expect(llmsModel()->generate())->toContain("> Fine goods.\n> Shipped fast.");
    });

    test('the description falls back to the default meta description', function () {
        configureLlms(['design/head/default_description' => 'Meta fallback.']);

        expect(llmsModel()->generate())->toContain('> Meta fallback.');
    });

    test('no blockquote appears when no description exists', function () {
        expect(llmsModel()->generate())->not->toContain('> ');
    });

    test('locale and currency are listed', function () {
        $store = Mage::app()->getStore();
        $locale = str_replace('_', '-', (string) Mage::getStoreConfig('general/locale/code', $store->getId()));

        expect(llmsModel()->generate())
            ->toContain('- Locale: ' . $locale)
            ->toContain('- Currency: ' . $store->getDefaultCurrencyCode());
    });

    test('the structured data surface is announced only when enabled', function () {
        if (!Mage::helper('core')->isModuleEnabled('Maho_StructuredData')) {
            $this->markTestSkipped('Maho_StructuredData module is not enabled.');
        }
        $store = Mage::app()->getStore();

        $store->setConfig('catalog/structured_data/enabled', '1');
        expect(llmsModel()->generate())->toContain('- Structured data: ');

        $store->setConfig('catalog/structured_data/enabled', '0');
        expect(llmsModel()->generate())->not->toContain('- Structured data: ');
    });

    test('sitemaps are listed with absolute store URLs', function () {
        $store = Mage::app()->getStore();

        $sitemap = Mage::getModel('sitemap/sitemap');
        $sitemap->setSitemapPath('/')
            ->setSitemapFilename('llms_test_sitemap.xml')
            ->setStoreId($store->getId())
            ->save();

        try {
            $url = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'llms_test_sitemap.xml';
            expect(llmsModel()->generate())
                ->toContain('## Sitemaps')
                ->toContain("[llms_test_sitemap.xml]({$url})");
        } finally {
            $sitemap->delete();
        }
    });

    test('the sitemaps section is omitted when no sitemap exists for the store', function () {
        expect(llmsModel()->generate())->not->toContain('## Sitemaps');
    });

    test('extra content is appended verbatim', function () {
        configureLlms([
            Mage_Sitemap_Model_Llms::XML_PATH_CUSTOM => "## Support\n\n- [Contact](https://example.com/contacts)",
        ]);

        expect(llmsModel()->generate())->toEndWith("## Support\n\n- [Contact](https://example.com/contacts)\n");
    });

    test('the sitemap gate on robots.txt does not hide sitemaps from llms.txt', function () {
        Mage::app()->getStore()->setConfig(Mage_Sitemap_Model_Robots::XML_PATH_INCLUDE_SITEMAPS, '0');
        $store = Mage::app()->getStore();

        $sitemap = Mage::getModel('sitemap/sitemap');
        $sitemap->setSitemapPath('/')
            ->setSitemapFilename('llms_gate_sitemap.xml')
            ->setStoreId($store->getId())
            ->save();

        try {
            expect(llmsModel()->generate())->toContain('llms_gate_sitemap.xml');
        } finally {
            $sitemap->delete();
        }
    });
});

describe('controller', function () {
    test('serves the file as markdown', function () {
        $response = new Mage_Core_Controller_Response_Http();
        (new Mage_Sitemap_LlmsController(Mage::app()->getRequest(), $response))->indexAction();

        expect($response->getHttpResponseCode())->toBe(200);
        expect($response->getBody())->toStartWith('# ');
        expect($response->getBody())->not->toContain('<html');

        $headers = [];
        foreach ($response->getHeaders() as $header) {
            $headers[strtolower($header['name'])] = $header['value'];
        }
        expect($headers['content-type'])->toBe('text/markdown; charset=UTF-8');
        expect($headers['cache-control'])->toBe('public, max-age=3600');
    });

    test('answers 404 when generation is turned off', function () {
        configureLlms([Mage_Sitemap_Model_Llms::XML_PATH_ENABLED => '0']);

        $response = new Mage_Core_Controller_Response_Http();
        (new Mage_Sitemap_LlmsController(Mage::app()->getRequest(), $response))->indexAction();

        expect($response->getHttpResponseCode())->toBe(404);
        expect($response->getBody())->toBe('');
    });
});
