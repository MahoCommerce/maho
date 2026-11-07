<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A collector that reads $sources instead of the compiled attributes.
 *
 * @param array<string, array{module: string, class: string, alias: string, method: string, model: ?string, fields: list<string>}> $sources
 */
function contentCollectorWith(array $sources): Mage_Core_Model_Design_Content_Collector
{
    return new class ($sources) extends Mage_Core_Model_Design_Content_Collector {
        public function __construct(private readonly array $compiled) {}

        #[\Override]
        protected function getCompiledSources(): array
        {
            return $this->compiled;
        }
    };
}

describe('the class names of the storefront HTML', function () {
    it('reads double and single quoted class attributes', function (): void {
        $tokens = new Mage_Core_Model_Design_Content_Collector()->extractTokens(
            '<div class="grid  grid-cols-3"><p CLASS=\'text-primary md:hidden\'>x</p></div>',
        );

        expect($tokens)->toBe(['grid', 'grid-cols-3', 'text-primary', 'md:hidden']);
    });

    it('reads the class parameter of a directive', function (): void {
        $tokens = new Mage_Core_Model_Design_Content_Collector()->extractTokens(
            '{{icon name="truck" class="size-8 text-accent"}}',
        );

        expect($tokens)->toBe(['size-8', 'text-accent']);
    });

    it('decodes HTML entities, so an arbitrary value keeps its quotes', function (): void {
        $tokens = new Mage_Core_Model_Design_Content_Collector()->extractTokens(
            '<span class="content-[&quot;x&quot;] after:content-[\'y\']">',
        );

        expect($tokens)->toBe(['content-["x"]', "after:content-['y']"]);
    });

    it('does not take the words of the text', function (): void {
        $tokens = new Mage_Core_Model_Design_Content_Collector()->extractTokens(
            '<p>A block that is hidden and has a flex border.</p>',
        );

        expect($tokens)->toBe([]);
    });

    it('leaves out a token that is longer than the maximum', function (): void {
        $long = str_repeat('a', Mage_Core_Model_Design_Content_Collector::MAX_TOKEN_LENGTH + 1);
        $tokens = new Mage_Core_Model_Design_Content_Collector()->extractTokens("<i class=\"{$long} p-2\"></i>");

        expect($tokens)->toBe(['p-2']);
    });
});

describe('the sources of the collector', function () {
    beforeEach(function (): void {
        $this->source = new class {
            public function getHtml(): iterable
            {
                yield '<div class="p-4 rotate-47"></div>';
                yield '<div class="p-4 bg-lime-300"></div>';
            }
        };
        Mage::register('_singleton/test/tailwind_source', $this->source);
    });

    afterEach(function (): void {
        Mage::unregister('_singleton/test/tailwind_source');
    });

    it('compiles the sources of the core modules', function (): void {
        $sources = Mage::getModel('core/design_content_collector')->getSources();

        expect($sources)->toHaveKeys(['cms_block', 'cms_page', 'blog_post', 'catalog_product', 'catalog_category', 'widget_instance'])
            ->and($sources['cms_block'])->toMatchArray([
                'module' => 'Mage_Cms',
                'alias' => 'cms/resource_block',
                'method' => 'getTailwindContent',
                'model' => Mage_Cms_Model_Block::class,
                'fields' => ['content'],
            ]);
    });

    it('leaves out the source of a disabled module', function (): void {
        $source = ['class' => 'X', 'alias' => 'test/tailwind_source', 'method' => 'getHtml', 'model' => null, 'fields' => []];
        $collector = contentCollectorWith([
            'enabled' => ['module' => 'Mage_Cms'] + $source,
            'disabled' => ['module' => 'Missing_Module'] + $source,
        ]);

        expect(array_keys($collector->getSources()))->toBe(['enabled']);
    });

    it('collects the unique class names of every source, sorted', function (): void {
        $collector = contentCollectorWith([
            'test' => ['module' => 'Mage_Cms', 'class' => 'X', 'alias' => 'test/tailwind_source', 'method' => 'getHtml', 'model' => null, 'fields' => []],
        ]);

        expect($collector->collectTokens())->toBe(['bg-lime-300', 'p-4', 'rotate-47']);
    });
});
