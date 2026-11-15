<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Helper Data', function () {
    test('getOutputDirectory returns absolute path', function () {
        $helper = Mage::helper('feedmanager');
        $path = withoutDeprecations(fn() => $helper->getOutputDirectory());
        expect($path)->toBeString()
            ->and($path)->toStartWith('/');
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

    test('getConfigurableModeOptions returns the modes of the feed model', function () {
        $helper = Mage::helper('feedmanager');
        expect($helper->getConfigurableModeOptions())->toBe(Maho_FeedManager_Model_Feed::getConfigurableModeOptions());
    });
});

describe('Helper formatFileSize', function () {
    test('formats a file size', function (int $bytes, string $expected) {
        expect(Mage::helper('feedmanager')->formatFileSize($bytes))->toBe($expected);
    })->with([
        'bytes' => [500, '500 B'],
        'kilobytes' => [1024, '1 KB'],
        'megabytes' => [1048576, '1 MB'],
        'gigabytes' => [1073741824, '1 GB'],
        'zero bytes' => [0, '0 B'],
        'fractional kilobytes' => [1536, '1.5 KB'],
    ]);
});

describe('Helper getPlatformFormats', function () {
    test('returns formats for custom', function () {
        $helper = Mage::helper('feedmanager');
        $formats = $helper->getPlatformFormats('custom');
        expect($formats)->toBeArray()
            ->and($formats)->toContain('xml')
            ->and($formats)->toContain('csv')
            ->and($formats)->toContain('json');
    });
});
