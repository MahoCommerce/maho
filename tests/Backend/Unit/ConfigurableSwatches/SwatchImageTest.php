<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('the swatch image entity', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_swatch_image_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::app()->setCurrentStore($this->storeId);
        Mage::getSingleton('core/image_config')->reset();

        $png = Maho::getImageManager()->createImage(80, 40)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write('wysiwyg/swatches/red.png', $png);
    });

    afterEach(function (): void {
        Mage::getSingleton('core/image_config')->reset();
        MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('makes square files at 1x and 2x of the listing slot of the swatch entity', function (): void {
        $settings = Mage::getSingleton('core/image_config')->getLiveSettings('swatch', $this->storeId);

        expect($settings->widths)->toBe([36, 72])
            ->and($settings->ratio)->toBe([1, 1])
            ->and($settings->fit)->toBe('cover');
    });

    it('lists the swatch images without the resized files', function (): void {
        $this->mount->write('wysiwyg/swatches/cache/0123456789ab/36/red.png.webp', 'x');

        expect(iterator_to_array(Mage::getModel('configurableswatches/swatch_image')->getImageFiles(), false))->toBe(['/red.png']);
    });

    it('finds the entity of a source file, and no entity for a resized file', function (): void {
        $resizer = Mage::getSingleton('core/image_resizer');

        expect($resizer->findSourceFile('wysiwyg/swatches/red.png'))->toBe(['swatch', '/red.png'])
            ->and($resizer->findSourceFile('wysiwyg/swatches/cache/0123456789ab/36/red.png.webp'))->toBeNull()
            ->and($resizer->findSourceFile('wysiwyg/banners/red.png'))->toBeNull()
            ->and($resizer->getCacheFolders())->toContain('wysiwyg/swatches/cache', 'catalog/product/cache');
    });

    it('links a global swatch to its 2x file, at least the size of the box', function (): void {
        $helper = Mage::helper('configurableswatches/productimg');
        $settings = Mage::getSingleton('core/image_config')->getActiveSettings('swatch', $this->storeId);
        $base = $this->mount->publicUrl('');

        expect($helper->getGlobalSwatchUrl(null, 'Red', 24, 24, '.png'))
            ->toBe($base . Mage_Core_Model_Image_Set::buildPath('wysiwyg/swatches', 'wysiwyg/swatches/red.png', $settings, 72));
    });

    it('draws a color swatch as an SVG data URL, and only from a hex color', function (): void {
        $helper = new Mage_ConfigurableSwatches_Helper_Productimg();
        (fn() => $this->swatchColors = ['blue' => '#0000ff', 'bad' => '"><script>'])->call($helper);

        expect($helper->getGlobalSwatchUrl(null, 'Blue', 24, 24))
            ->toBe('data:image/svg+xml,' . rawurlencode("<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24'><rect width='100%' height='100%' fill='#0000ff'/></svg>"))
            ->and($helper->getColorSwatchUrl('Bad', 24, 24))->toBeNull()
            ->and($this->mount->listFiles('catalog/swatches')->toArray())->toBe([]);
    });
});
