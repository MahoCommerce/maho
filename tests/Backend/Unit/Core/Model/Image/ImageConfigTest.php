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

describe('the watermark of the live image settings', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_config_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->store = Mage::app()->getDefaultStoreView();
        $this->storeId = (int) $this->store->getId();
        $this->restore = ['design/watermark/image_image' => Mage::getStoreConfig('design/watermark/image_image', $this->storeId)];
        $this->config = Mage::getSingleton('core/image_config');
        $this->config->reset();
    });

    afterEach(function (): void {
        foreach ($this->restore as $path => $value) {
            $this->store->setConfig($path, $value);
        }
        $this->config->reset();
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

    it('takes the file of the store folder, then the website folder, the default folder and the watermark folder', function (): void {
        $this->store->setConfig('design/watermark/image_image', 'wm.png');
        $png = Maho::getImageManager()->createImage(10, 10)->fill('0000ff')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $base = 'catalog/product/watermark';
        $websiteId = (int) $this->store->getWebsiteId();
        $found = [];
        foreach (['', '/default', "/websites/{$websiteId}", "/stores/{$this->storeId}"] as $folder) {
            $this->mount->write("{$base}{$folder}/wm.png", $png);
            $this->config->reset();
            $found[] = $this->config->getLiveSettings('product', $this->storeId)->watermark['path'] ?? null;
        }

        expect($found)->toBe([
            "{$base}/wm.png",
            "{$base}/default/wm.png",
            "{$base}/websites/{$websiteId}/wm.png",
            "{$base}/stores/{$this->storeId}/wm.png",
        ]);
    });
});

describe('the image settings of a theme and its parents', function () {
    it('takes a value of the child theme first, a missing value from the parent, and the slot list of an entity from the child only', function (): void {
        $child = new \Maho\Simplexml\Element(
            '<images><slots><listing>100</listing></slots>'
            . '<product><ratio>3:4</ratio><slots><gallery>800</gallery></slots></product></images>',
        );
        $parent = new \Maho\Simplexml\Element(
            '<images><slots><listing>400</listing><thumbnail>50</thumbnail></slots>'
            . '<product><ratio>1:1</ratio><fit>cover</fit><slots><thumbnail/><gallery>600</gallery><zoom>1200</zoom></slots></product>'
            . '<blog><ratio>16:9</ratio></blog></images>',
        );
        $config = new class ([$child, $parent]) extends Mage_Core_Model_Image_Config {
            /** @param list<\Maho\Simplexml\Element> $nodes */
            public function __construct(private readonly array $nodes) {}

            #[\Override]
            protected function getThemeImagesNodes(int $storeId): array
            {
                return $this->nodes;
            }

            public function getMergedNode(): array
            {
                return $this->getThemeImagesNode(0);
            }
        };

        $node = $config->getMergedNode();

        expect($node['slots'])->toBe(['listing' => 100, 'thumbnail' => 50])
            ->and($node['entities']['product']['ratio'])->toBe('3:4')
            ->and($node['entities']['product']['fit'])->toBe('cover')
            ->and($node['entities']['product']['slots'])->toBe(['gallery' => 800])
            ->and($node['entities']['blog'])->toBe(['ratio' => '16:9']);
    });
});
