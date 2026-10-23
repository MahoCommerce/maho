<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Replace the bundle singleton with $bundle, or restore the real one when $bundle is null.
 */
function useContentBundle(?Mage_Core_Model_Design_Content_Bundle $bundle): void
{
    Mage::unregister('_singleton/core/design_content_bundle');
    if ($bundle !== null) {
        Mage::register('_singleton/core/design_content_bundle', $bundle);
    }
}

function contentBuildRows(): array
{
    return queueAdapter()->fetchAll(queueAdapter()->select()
        ->from(\Maho\Queue\QueueManager::tableName())
        ->where('message_class = ?', Mage_Core_Model_Design_Content_BuildMessage::class));
}

function deleteContentBuildRows(): void
{
    queueAdapter()->delete(\Maho\Queue\QueueManager::tableName(), ['message_class = ?' => Mage_Core_Model_Design_Content_BuildMessage::class]);
}

function deleteContentFlags(): void
{
    foreach ([Mage_Core_Model_Design_Content_Bundle::FLAG_MANIFEST, Mage_Core_Model_Design_Content_Bundle::FLAG_REQUEST] as $code) {
        Mage::getModel('core/flag', ['flag_code' => $code])->loadSelf()->delete();
    }
    Mage::app()->removeCache(Mage_Core_Model_Design_Content_Bundle::CACHE_ID);
}

function createContentBlock(string $content): Mage_Cms_Model_Block
{
    return Mage::getModel('cms/block')
        ->setTitle('Tailwind source test')
        ->setIdentifier('tailwind-source-test-' . bin2hex(random_bytes(4)))
        ->setContent($content)
        ->setIsActive(true)
        ->setStores([0])
        ->save();
}

beforeEach(function (): void {
    $this->blocks = [];
    deleteContentBuildRows();
});

afterEach(function (): void {
    useContentBundle(null);
    foreach ($this->blocks as $block) {
        $block->delete();
    }
    deleteContentBuildRows();
    deleteContentFlags();
});

describe('the table sources', function () {
    it('reads the content of a CMS block that has a class attribute', function (): void {
        $this->blocks[] = createContentBlock('<div class="rotate-47">styled</div>');
        $this->blocks[] = createContentBlock('<p>plain text only</p>');

        $content = iterator_to_array(Mage::getResourceSingleton('cms/block')->getTailwindContent(), false);

        expect($content)->toContain('<div class="rotate-47">styled</div>')
            ->not->toContain('<p>plain text only</p>');
    });

    it('reads the text attributes of the catalog that have the WYSIWYG editor only', function (): void {
        $attributes = Mage::getModel('catalog/design_tailwindSource')->getAttributes(Mage_Catalog_Model_Product::ENTITY);

        expect($attributes)->toContain('description')
            ->toContain('short_description')
            ->not->toContain('meta_keyword');
    });
});

describe('the queue of a build', function () {
    beforeEach(function (): void {
        useContentBundle(new class extends Mage_Core_Model_Design_Content_Bundle {
            #[\Override]
            public function getTailwindBinary(): ?string
            {
                return PHP_BINARY;
            }
        });
    });

    it('queues one build when a save changes the content of a CMS block', function (): void {
        $block = createContentBlock('<div class="rotate-47"></div>');
        $this->blocks[] = $block;
        $block->setContent('<div class="rotate-48"></div>')->save();

        $rows = contentBuildRows();
        expect($rows)->toHaveCount(1)
            ->and((string) $rows[0]['dedupe_key'])->toBe(Mage_Core_Model_Design_Content_Bundle::QUEUE_NAME)
            ->and((string) $rows[0]['queue'])->toBe(Mage_Core_Model_Design_Content_Bundle::QUEUE_NAME);
    });

    it('queues no build when a save changes another field', function (): void {
        $block = createContentBlock('<div class="rotate-47"></div>');
        $this->blocks[] = $block;
        deleteContentBuildRows();

        Mage::getModel('cms/block')->load($block->getId())->setTitle('Another title')->save();

        expect(contentBuildRows())->toBe([]);
    });

    it('queues no build without the Tailwind CLI', function (): void {
        useContentBundle(new class extends Mage_Core_Model_Design_Content_Bundle {
            #[\Override]
            public function getTailwindBinary(): ?string
            {
                return null;
            }
        });

        $this->blocks[] = createContentBlock('<div class="rotate-47"></div>');

        expect(contentBuildRows())->toBe([]);
    });
});

describe('the stylesheet in the page head', function () {
    beforeEach(function (): void {
        $this->design = Mage::getDesign();
        $this->previousDesign = [$this->design->getArea(), $this->design->getPackageName(), $this->design->getTheme('skin')];
        $this->design->setArea('frontend')->setPackageName('base')->setTheme('default');
        $this->styles = (string) $this->design->getFilename('css/styles.css', ['_type' => 'skin']);

        $this->headHtml = function (string $stylesHash): string {
            useContentBundle(new class ($stylesHash) extends Mage_Core_Model_Design_Content_Bundle {
                public function __construct(private readonly string $stylesHash) {}

                #[\Override]
                public function getManifest(): array
                {
                    return ['base/default' => ['file' => 'theme/test.css', 'tokens' => 'x', 'styles' => $this->stylesHash]];
                }
            });
            $head = Mage::app()->getLayout()->createBlock('page/html_head');
            $head->addItem('skin_css', 'css/styles.css');
            $head->addItem('skin_css', 'css/theme.css');
            return $head->getCssJsHtml();
        };
    });

    afterEach(function (): void {
        [$area, $package, $theme] = $this->previousDesign;
        $this->design->setArea($area)->setPackageName($package)->setTheme($theme);
    });

    it('links the copy with the content classes when it comes from the current styles.css', function (): void {
        $html = ($this->headHtml)(hash_file('xxh128', $this->styles));

        expect($html)->toContain(Mage::getStorage('media')->publicUrl('theme/test.css'))
            ->not->toContain('css/styles.css')
            ->toContain('css/theme.css');
    });

    it('links the committed styles.css when the copy comes from another version of it', function (): void {
        $html = ($this->headHtml)('another-hash');

        expect($html)->toContain('skin/frontend/base/default/css/styles.css')
            ->not->toContain('theme/test.css');
    });
});

describe('a manifest whose file is not on the mount', function () {
    beforeEach(function (): void {
        $styles = Mage::getBaseDir('skin') . '/frontend/base/default/css/styles.css';
        Mage::getModel('core/flag', ['flag_code' => Mage_Core_Model_Design_Content_Bundle::FLAG_MANIFEST])->loadSelf()
            ->setFlagData(['base/default' => ['file' => 'theme/missing-0000.css', 'tokens' => 'x', 'styles' => hash_file('xxh128', $styles)]])
            ->save();
        Mage::app()->removeCache(Mage_Core_Model_Design_Content_Bundle::CACHE_ID);
        $this->styles = $styles;
    });

    it('links the committed styles.css', function (): void {
        expect(Mage::getModel('core/design_content_bundle')->getUrl($this->styles))->toBeNull();
    });

    it('makes the theme outdated, so the cron job queues a build', function (): void {
        expect(Mage::getModel('core/design_content_bundle')->getOutdatedThemes())->toContain('base/default');
    });
});

describe('a build that fails', function () {
    beforeEach(function (): void {
        $this->styles = Mage::getBaseDir('skin') . '/frontend/base/default/css/styles.css';
        $this->mount = Mage::getStorage(Mage_Core_Model_Design_Content_Bundle::MOUNT);
        $this->mount->write('theme/base-default-previous.css', '.previous{}');
        Mage::getModel('core/flag', ['flag_code' => Mage_Core_Model_Design_Content_Bundle::FLAG_MANIFEST])->loadSelf()
            ->setFlagData(['base/default' => ['file' => 'theme/base-default-previous.css', 'tokens' => 'older tokens', 'styles' => hash_file('xxh128', $this->styles)]])
            ->save();
        Mage::app()->removeCache(Mage_Core_Model_Design_Content_Bundle::CACHE_ID);
        $this->blocks[] = createContentBlock('<div class="rotate-47"></div>');
    });

    afterEach(function (): void {
        $this->mount->delete('theme/base-default-previous.css');
    });

    it('keeps the previous file in the head, and makes the theme outdated', function (): void {
        $bundle = new class extends Mage_Core_Model_Design_Content_Bundle {
            #[\Override]
            public function getTailwindBinary(): ?string
            {
                return PHP_BINARY;
            }

            #[\Override]
            protected function compile(string $binary, string $workDir, string $theme, string $entry): ?string
            {
                return null;
            }
        };

        expect($bundle->build())->toHaveKey('base/default', Mage_Core_Model_Design_Content_Bundle::STATUS_FAILED)
            ->and($bundle->getUrl($this->styles))->toBe($this->mount->publicUrl('theme/base-default-previous.css'))
            ->and($bundle->getOutdatedThemes())->toContain('base/default');
    });
});

describe('the build', function () {
    beforeEach(function (): void {
        if (Mage::getModel('core/design_content_bundle')->getTailwindBinary() === null) {
            $this->markTestSkipped('the Tailwind toolchain is not installed');
        }
    });

    afterEach(function (): void {
        $mount = Mage::getStorage(Mage_Core_Model_Design_Content_Bundle::MOUNT);
        foreach ($mount->listFiles(Mage_Core_Model_Design_Content_Bundle::FOLDER) as $file) {
            $mount->delete($file->path());
        }
    });

    it('compiles the class of a CMS block into a copy of the theme bundle', function (): void {
        $this->blocks[] = createContentBlock('<div class="rotate-47"></div>');
        $bundle = Mage::getModel('core/design_content_bundle');

        $status = $bundle->build();
        $file = $bundle->getManifest()['base/default']['file'] ?? null;

        expect($status)->toHaveKey('base/default', Mage_Core_Model_Design_Content_Bundle::STATUS_BUILT)
            ->and($file)->toBeString();
        $css = Mage::getStorage(Mage_Core_Model_Design_Content_Bundle::MOUNT)->read($file);
        expect($css)->toContain('.rotate-47')
            ->toContain('--color-primary');

        expect($bundle->build())->toHaveKey('base/default', Mage_Core_Model_Design_Content_Bundle::STATUS_UNCHANGED);
    });
});
