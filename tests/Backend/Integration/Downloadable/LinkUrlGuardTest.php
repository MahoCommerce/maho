<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Downloadable link URL guard', function (): void {
    it('refuses to fetch a link URL that points to a non-public host', function (string $url): void {
        $helper = Mage::helper('downloadable/download');
        $helper->setResource($url, Mage_Downloadable_Helper_Download::LINK_TYPE_URL);
        expect(fn() => $helper->getFilesize())->toThrow(Mage_Core_Exception::class, 'not allowed');
    })->with(['http://127.0.0.1:1/file.zip', 'http://[::1]:1/file.zip', 'http://169.254.169.254/latest/meta-data/']);

    it('refuses a link URL with a scheme other than http or https', function (): void {
        $helper = Mage::helper('downloadable/download');
        $helper->setResource('ftp://93.184.216.34/file.zip', Mage_Downloadable_Helper_Download::LINK_TYPE_URL);
        expect(fn() => $helper->getFilesize())->toThrow(Mage_Core_Exception::class, 'not allowed');
    });

    it('refuses to save a link or a sample with a URL that is not valid RFC 3986', function (): void {
        $link = Mage::getModel('downloadable/link')
            ->setLinkType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
            ->setLinkUrl('https://files.example/guía.pdf');
        expect(fn() => $link->save())->toThrow(Mage_Core_Exception::class, 'is not valid');

        $sample = Mage::getModel('downloadable/sample')
            ->setSampleType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
            ->setSampleUrl('https://files.example/a b.pdf');
        expect(fn() => $sample->save())->toThrow(Mage_Core_Exception::class, 'is not valid');

        $linkWithSample = Mage::getModel('downloadable/link')
            ->setLinkType(Mage_Downloadable_Helper_Download::LINK_TYPE_FILE)
            ->setSampleType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
            ->setSampleUrl('https://files.example/a b.pdf');
        expect(fn() => $linkWithSample->save())->toThrow(Mage_Core_Exception::class, 'is not valid');
    });

    it('rejects ftp link URLs in the API validator', function (): void {
        $validator = new Mage_Downloadable_Model_Link_Api_Validator();
        $url = 'ftp://example.com/file.zip';
        expect(fn() => $validator->validateUrl($url))->toThrow(Exception::class, 'url_not_valid');
        $url = 'https://example.com/file.zip';
        $validator->validateUrl($url);
        expect($url)->toBe('https://example.com/file.zip');
        $url = 'https://files.example/gu%C3%ADa.pdf';
        $validator->validateUrl($url);
        expect($url)->toBe('https://files.example/gu%C3%ADa.pdf');
    });
});
