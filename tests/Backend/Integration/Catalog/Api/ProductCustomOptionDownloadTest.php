<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Catalog\Api\ProductCustomOptionProvider;
use Symfony\Component\HttpFoundation\Request;

uses(Tests\MahoBackendTestCase::class);

function productOptionDownloadProvider(string $title): ProductCustomOptionProvider
{
    $service = new class ($title) {
        public function __construct(private string $title) {}

        public function openUploadedFile(int $optionId, string $key): array
        {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, 'content');
            rewind($stream);
            return [
                'stream' => $stream,
                'type' => 'application/pdf',
                'title' => $this->title,
            ];
        }
    };
    Mage::register('_service/catalog/product_option', $service, true);

    return new ProductCustomOptionProvider();
}

afterEach(function () {
    Mage::unregister('_service/catalog/product_option');
});

it('uses RFC 6266 filename parameters for a Unicode option download', function () {
    $provider = productOptionDownloadProvider('Résumé 2026.pdf');

    $response = $provider->provide(
        new Get(name: 'download_option_file'),
        [],
        ['request' => Request::create('/custom-option-file/12/abcdef')],
    );

    $header = (string) $response->headers->get('Content-Disposition');
    expect($header)->toStartWith('attachment; filename="')
        ->and($header)->toContain("filename*=utf-8''R%C3%A9sum%C3%A9%202026.pdf")
        ->and($header)->toContain('filename="R__sum__ 2026.pdf"');
});

it('removes control characters before building the header', function () {
    $provider = productOptionDownloadProvider("invoice\r\n2026.pdf");

    $response = $provider->provide(
        new Get(name: 'download_option_file'),
        [],
        ['request' => Request::create('/custom-option-file/12/abcdef')],
    );

    $header = (string) $response->headers->get('Content-Disposition');
    expect($header)->not->toContain("\r")
        ->and($header)->not->toContain("\n")
        ->and($header)->toContain('filename=invoice2026.pdf');
});
