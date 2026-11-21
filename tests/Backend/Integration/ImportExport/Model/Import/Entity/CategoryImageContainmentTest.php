<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class CategoryImageContainmentProbe extends Mage_ImportExport_Model_Import_Entity_Category
{
    public function imageSource(string $image): string
    {
        return $this->_getImageSource($image);
    }

    public function importImage(string $image): string
    {
        return $this->_importImage($image);
    }
}

beforeEach(function () {
    $this->base = sys_get_temp_dir() . '/maho-category-image-' . uniqid();
    $this->media = $this->base . '/media';
    mkdir($this->media, 0755, true);
    file_put_contents($this->media . '/inside.jpg', 'inside');
    file_put_contents($this->base . '/outside.jpg', 'outside');
    $this->importer = new CategoryImageContainmentProbe();
    $this->importer->setParameters([
        Mage_ImportExport_Model_Import_Entity_Category::PARAM_MEDIA_DIR => $this->media,
    ]);
});

afterEach(function () {
    unlink($this->media . '/inside.jpg');
    unlink($this->base . '/outside.jpg');
    rmdir($this->media);
    rmdir($this->base);
});

it('resolves an image inside the configured media directory', function () {
    expect($this->importer->imageSource('inside.jpg'))
        ->toBe(realpath($this->media . '/inside.jpg'));
});

it('rejects an image that resolves outside the configured media directory', function () {
    expect($this->importer->imageSource('../outside.jpg'))->toBe('')
        ->and(fn() => $this->importer->importImage('../outside.jpg'))
        ->toThrow(Mage_Core_Exception::class);
});
