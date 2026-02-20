<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Helper Data', function () {
    test('can be instantiated via Mage::helper', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper)->toBeInstanceOf(Maho_FeedManager_Helper_Data::class);
    });

    test('isEnabled returns boolean', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->isEnabled())->toBeBool();
    });

    test('getOutputDirectory returns absolute path', function () {
        $helper = Mage::helper('feedmanager');
        $path = $helper->getOutputDirectory();
        expect($path)->toBeString()
            ->and($path)->toStartWith('/');
    });

    test('getOutputDirectoryRelative returns string', function () {
        $helper = Mage::helper('feedmanager');
        $relative = $helper->getOutputDirectoryRelative();
        expect($relative)->toBeString()->not->toBeEmpty();
    });

    test('getOutputDirectoryRelative defaults to feeds', function () {
        $helper = Mage::helper('feedmanager');
        $relative = $helper->getOutputDirectoryRelative();
        expect($relative)->toBe('feeds');
    });

    test('getBatchSize returns positive integer', function () {
        $helper = Mage::helper('feedmanager');
        $batchSize = $helper->getBatchSize();
        expect($batchSize)->toBeInt()
            ->and($batchSize)->toBeGreaterThan(0);
    });

    test('getPlatformOptions returns array with empty key for selection', function () {
        $helper = Mage::helper('feedmanager');
        $options = $helper->getPlatformOptions();
        expect($options)->toBeArray()
            ->and($options)->not->toBeEmpty()
            ->and($options)->toHaveKey('')
            ->and($options)->toHaveKey('google')
            ->and($options)->toHaveKey('facebook')
            ->and($options)->toHaveKey('custom');
    });

    test('getFileFormatOptions returns expected formats', function () {
        $helper = Mage::helper('feedmanager');
        $options = $helper->getFileFormatOptions();
        expect($options)->toBeArray()
            ->and($options)->toHaveKey('xml')
            ->and($options)->toHaveKey('csv')
            ->and($options)->toHaveKey('json')
            ->and($options)->toHaveKey('jsonl');
    });

    test('getConfigurableModeOptions returns non-empty array', function () {
        $helper = Mage::helper('feedmanager');
        $options = $helper->getConfigurableModeOptions();
        expect($options)->toBeArray()->not->toBeEmpty();
    });
});

describe('Helper formatFileSize', function () {
    test('formats bytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(500))->toBe('500 B');
    });

    test('formats kilobytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(1024))->toBe('1 KB');
    });

    test('formats megabytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(1048576))->toBe('1 MB');
    });

    test('formats gigabytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(1073741824))->toBe('1 GB');
    });

    test('formats zero bytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(0))->toBe('0 B');
    });

    test('formats fractional kilobytes', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->formatFileSize(1536))->toBe('1.5 KB');
    });
});

describe('Helper getPlatformFormats', function () {
    test('returns formats for google', function () {
        $helper = Mage::helper('feedmanager');
        $formats = $helper->getPlatformFormats('google');
        expect($formats)->toBeArray()
            ->and($formats)->toContain('xml');
    });

    test('returns formats for custom', function () {
        $helper = Mage::helper('feedmanager');
        $formats = $helper->getPlatformFormats('custom');
        expect($formats)->toBeArray()
            ->and($formats)->toContain('xml')
            ->and($formats)->toContain('csv')
            ->and($formats)->toContain('json');
    });
});
