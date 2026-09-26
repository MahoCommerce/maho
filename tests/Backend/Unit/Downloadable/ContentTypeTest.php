<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A merchant sells a file in a format that the built-in map does not hold, or holds less
 * exactly. Content sniffing reads a container format as zip, so a type declared in
 * global/mime/types wins over it.
 */

function mahoDownloadOf(string $name, string $content): Mage_Downloadable_Helper_Download
{
    Mage::getStorage('downloadable')->write($name, $content);

    test()->mahoDownloadFile = $name;

    return new Mage_Downloadable_Helper_Download()
        ->setResource($name, Mage_Downloadable_Helper_Download::LINK_TYPE_FILE);
}

afterEach(function () {
    if (isset($this->mahoDownloadFile)) {
        Mage::getStorage('downloadable')->delete($this->mahoDownloadFile);
        unset($this->mahoDownloadFile);
    }
});

it('serves the declared type rather than the type it reads from the file', function () {
    Mage::getConfig()->setNode('global/mime/types/xmahopack', 'application/vnd.maho-pack');

    // The content is plain text, so sniffing alone answers text/plain.
    $download = mahoDownloadOf('mahotest-download.mahopack', 'plain text content');

    expect($download->getContentType())->toBe('application/vnd.maho-pack');
});

it('reads the type from the file when the node declares none', function () {
    $download = mahoDownloadOf('mahotest-download.txt', 'plain text content');

    expect($download->getContentType())->toStartWith('text/plain');
});
