<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A breakpoint model that reads $themes, child first, in place of the theme chain of the store. Each
 * theme is the XML inside its <breakpoints> node.
 */
function designBreakpoints(string ...$themes): Mage_Core_Model_Design_Breakpoints
{
    $nodes = array_map(fn(string $xml) => new \Maho\Simplexml\Element("<breakpoints>{$xml}</breakpoints>"), $themes);
    return new class ($nodes) extends Mage_Core_Model_Design_Breakpoints {
        /** @param list<\Maho\Simplexml\Element> $nodes */
        public function __construct(private array $nodes) {}

        #[\Override]
        protected function getThemeNodes(int $storeId, string $package, string $theme): array
        {
            return $this->nodes;
        }
    };
}

/** Make $model the breakpoint singleton, which the template filters use. */
function useBreakpoints(Mage_Core_Model_Design_Breakpoints $model): void
{
    Mage::unregister('_singleton/core/design_breakpoints');
    Mage::register('_singleton/core/design_breakpoints', $model);
}

/**
 * The breakpoints in theme.xml of the theme $theme.
 *
 * @return array<string, int|null>
 */
function themeBreakpoints(string $theme): array
{
    $node = Mage::getSingleton('core/design_config')->getNode("frontend/{$theme}/breakpoints");
    return [
        'mobile' => Mage_Core_Model_Design_Breakpoints::toWidth((string) $node?->mobile),
        'tablet' => Mage_Core_Model_Design_Breakpoints::toWidth((string) $node?->tablet),
    ];
}

afterEach(function () {
    Mage::unregister('_singleton/core/design_breakpoints');
});

it('reads a width as a whole number in range', function (mixed $value, ?int $expected) {
    expect(Mage_Core_Model_Design_Breakpoints::toWidth($value))->toBe($expected);
})->with([
    'a number' => ['767', 767],
    'spaces around it' => [' 767 ', 767],
    'the lowest width' => ['1', 1],
    'the highest width' => ['9999', 9999],
    'zero' => ['0', null],
    'above the range' => ['10000', null],
    'a decimal' => ['767.5', null],
    'a unit' => ['767px', null],
    'empty' => ['', null],
    'missing' => [null, null],
]);

it('takes a preset of the child theme first, and a preset that it does not set from the parent theme', function () {
    $model = designBreakpoints('<tablet>1100</tablet>', '<mobile>770</mobile><tablet>1023</tablet>');

    expect($model->getWidths())->toBe(['mobile' => 770, 'tablet' => 1100]);
});

it('takes the parent value of a preset that the child theme sets to a width that is not valid', function () {
    expect(designBreakpoints('<mobile>wide</mobile>', '<mobile>770</mobile>')->getWidths()['mobile'])->toBe(770);
});

it('writes the media query of a preset from the width of the theme', function () {
    expect(designBreakpoints('<mobile>770</mobile>')->getMediaQuery('mobile'))->toBe('(max-width: 770px)');
});

it('writes a query that matches no device for an unknown preset or a preset that no theme sets', function () {
    $model = designBreakpoints('<mobile>770</mobile>');

    expect($model->getMediaQuery('watch'))->toBe(Mage_Core_Model_Design_Breakpoints::NO_MATCH);
    expect($model->getMediaQuery('tablet'))->toBe(Mage_Core_Model_Design_Breakpoints::NO_MATCH);
});

it('gives the editor the presets that have a valid width, from the narrowest', function () {
    expect(designBreakpoints('<mobile>770</mobile><tablet>1023</tablet>')->getEditorPresets())->toBe([
        ['code' => 'mobile', 'label' => 'Mobile', 'width' => 770],
        ['code' => 'tablet', 'label' => 'Tablet', 'width' => 1023],
    ]);
    expect(array_column(designBreakpoints('<tablet>1023</tablet>')->getEditorPresets(), 'code'))->toBe(['tablet']);
});

it('gives each core theme presets in order', function () {
    expect(themeBreakpoints('base/default'))->toBe(['mobile' => 770, 'tablet' => 1023]);
    expect(themeBreakpoints('legacy/default'))->toBe(['mobile' => 770, 'tablet' => 979]);
});

it('takes the presets of the active skin theme for the page that renders, also after a change of the theme', function () {
    $design = Mage::getDesign();
    $previous = [$design->getPackageName(), $design->getTheme('skin')];
    $model = new Mage_Core_Model_Design_Breakpoints();
    try {
        $design->setPackageName('base')->setTheme('skin', 'default');
        expect($model->getWidths()['tablet'])->toBe(1023);
        $design->setPackageName('legacy')->setTheme('skin', 'default');
        expect($model->getWidths()['tablet'])->toBe(979);
    } finally {
        $design->setPackageName($previous[0])->setTheme('skin', $previous[1]);
    }
});

it('takes the presets of the theme in the store config for another store view', function () {
    $store = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $paths = ['design/package/name' => 'legacy', 'design/theme/skin' => 'default', 'design/theme/default' => 'default'];
    $restore = [];
    foreach (array_keys($paths) as $path) {
        $restore[$path] = $store->getConfig($path);
    }
    try {
        foreach ($paths as $path => $value) {
            $store->setConfig($path, $value);
        }
        expect(new Mage_Core_Model_Design_Breakpoints()->getWidths($store)['tablet'])->toBe(979);
    } finally {
        foreach ($restore as $path => $value) {
            $store->setConfig($path, $value);
        }
    }
});

it('writes the presets before the scripts of the head, for app.js', function () {
    useBreakpoints(designBreakpoints('<mobile>770</mobile><tablet>979</tablet>'));
    $html = Mage::app()->getLayout()->createBlock('page/html_head')
        ->addItem('skin_js', 'js/app.js')
        ->getCssJsHtml();

    expect($html)->toContain('<script>var BREAKPOINTS = {"mobile":770,"tablet":979};</script>');
    expect(strpos($html, 'BREAKPOINTS'))->toBeLessThan(strpos($html, '<script src'));
});

it('keeps the nav breakpoint of the base theme CSS one pixel above the mobile preset', function () {
    $css = (string) file_get_contents(Mage::getBaseDir() . '/public/skin/frontend/base/default/src/_theme.css');
    preg_match('/--breakpoint-nav:\s*(\d+)px/', $css, $match);

    expect((int) ($match[1] ?? 0))->toBe(themeBreakpoints('base/default')['mobile'] + 1);
});

it('resolves the directive in the media attribute of a source in CMS content', function () {
    useBreakpoints(designBreakpoints('<mobile>700</mobile>'));
    $html = '<picture><source media="{{breakpoint code=mobile}}" srcset="a.jpg"><img src="b.jpg" alt=""></picture>';

    expect(Mage::helper('cms')->getPageTemplateProcessor()->filter($html))
        ->toContain('<source media="(max-width: 700px)" srcset="a.jpg">');
});

it('resolves the directive in a product description', function () {
    useBreakpoints(designBreakpoints('<tablet>1100</tablet>'));

    expect(Mage::helper('catalog')->getPageTemplateProcessor()->filter('<source media="{{breakpoint code=tablet}}">'))
        ->toBe('<source media="(max-width: 1100px)">');
});

it('keeps the directive when the sanitizer runs on save', function () {
    $html = '<picture><source media="{{breakpoint code=mobile}}" srcset="a.jpg"><img src="b.jpg" alt=""></picture>';
    $result = Mage::getSingleton('core/input_filter_maliciousCode')
        ->filterPreservingDirectives($html, false, Mage::helper('cms')->getPageTemplateProcessor());

    expect($result)->toContain('media="{{breakpoint code=mobile}}"');
});

it('keeps the directive as an invalid media query on a path that does not resolve it', function () {
    // The browser reads the unresolved text as a query that matches no device, so it shows the default image
    expect(Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives('<source media="{{breakpoint code=mobile}}">'))
        ->toBe('<source media="{{breakpoint code=mobile}}">');
});
