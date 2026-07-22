<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('AccessibilityScan helper', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('accessibilityscan');
    });

    it('normalizes WCAG levels, defaulting to AA', function () {
        expect($this->helper->normalizeWcagLevel('a'))->toBe('A');
        expect($this->helper->normalizeWcagLevel(' aa '))->toBe('AA');
        expect($this->helper->normalizeWcagLevel('AAA'))->toBe('AAA');
        expect($this->helper->normalizeWcagLevel('bogus'))->toBe('AA');
        expect($this->helper->normalizeWcagLevel(null))->toBe('AA');
        expect($this->helper->normalizeWcagLevel(''))->toBe('AA');
    });

    it('builds cumulative axe-core tag lists per WCAG level', function () {
        expect($this->helper->getWcagTags('A'))->toBe(['wcag2a', 'wcag21a', 'wcag22a']);
        expect($this->helper->getWcagTags('AA'))->toContain('wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa');
        expect($this->helper->getWcagTags('AA'))->not->toContain('wcag2aaa');
        expect($this->helper->getWcagTags('AAA'))->toContain('wcag2a', 'wcag2aa', 'wcag2aaa', 'wcag21aaa', 'wcag22aaa');
    });

    it('parses the scheduled URL list, trimming and de-duplicating', function () {
        Mage::app()->getStore()->setConfig(
            'accessibilityscan/scheduled/urls',
            "https://store.example.com/\n\n  https://store.example.com/about  \r\nhttps://store.example.com/\n",
        );

        expect($this->helper->getScheduledScanUrls())->toBe([
            'https://store.example.com/',
            'https://store.example.com/about',
        ]);
    });

    it('returns an empty scheduled URL list when unconfigured', function () {
        Mage::app()->getStore()->setConfig('accessibilityscan/scheduled/urls', '');
        expect($this->helper->getScheduledScanUrls())->toBe([]);
    });

    it('rejects scan URLs that do not belong to a store base URL', function () {
        expect($this->helper->resolveScanUrlStoreId('https://definitely-not-a-store.example.invalid/'))->toBeNull();
        expect($this->helper->resolveScanUrlStoreId('ftp://example.com/'))->toBeNull();
        expect($this->helper->resolveScanUrlStoreId('not a url'))->toBeNull();
        expect($this->helper->resolveScanUrlStoreId(''))->toBeNull();
        expect($this->helper->isAllowedScanUrl('https://definitely-not-a-store.example.invalid/'))->toBeFalse();
    });

    it('rejects userinfo tricks that embed a store host in the credentials part', function () {
        $storeHost = parse_url(Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_HOST);
        expect($this->helper->resolveScanUrlStoreId("https://{$storeHost}@evil.example.com/"))->toBeNull();
    });

    it('accepts URLs under a configured store base URL', function () {
        $baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        expect($this->helper->resolveScanUrlStoreId($baseUrl))->toBeInt();
        expect($this->helper->resolveScanUrlStoreId($baseUrl . 'some/page.html'))->toBeInt();
        expect($this->helper->isAllowedScanUrl($baseUrl))->toBeTrue();
    });

    it('treats the cleanup-days setting as a non-negative integer', function () {
        Mage::app()->getStore()->setConfig('accessibilityscan/general/cleanup_days', '-5');
        expect($this->helper->getCleanupDays())->toBe(0);

        Mage::app()->getStore()->setConfig('accessibilityscan/general/cleanup_days', '90');
        expect($this->helper->getCleanupDays())->toBe(90);
    });

    it('enforces a minimum scan timeout', function () {
        Mage::app()->getStore()->setConfig('accessibilityscan/general/timeout', '3');
        expect($this->helper->getScanTimeout())->toBe(10);

        Mage::app()->getStore()->setConfig('accessibilityscan/general/timeout', '120');
        expect($this->helper->getScanTimeout())->toBe(120);
    });

    it('parses viewport config with fallback to defaults', function () {
        Mage::app()->getStore()->setConfig('accessibilityscan/general/viewport_desktop', '1920x1080');
        Mage::app()->getStore()->setConfig('accessibilityscan/general/viewport_mobile', 'garbage');

        $viewports = $this->helper->getViewports();
        expect($viewports['desktop'])->toBe(['width' => 1920, 'height' => 1080, 'mobile' => false]);
        expect($viewports['mobile'])->toBe(['width' => 390, 'height' => 844, 'mobile' => true]);
    });
});
