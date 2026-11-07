<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function previewStylesFile(string $theme): string
{
    return Mage::getBaseDir('skin') . "/frontend/{$theme}/css/styles.css";
}

describe('the stylesheet of the browser build in the editor preview', function () {
    it('references the tokens of the theme and imports only the utilities', function (): void {
        $css = Mage::getModel('core/design_content_previewCss')->getCss(previewStylesFile('base/default'));

        expect($css)->toStartWith("@theme reference {\n")
            ->toContain('    --breakpoint-nav: 771px;')
            ->toContain('    --text-13: 0.8125rem;')
            ->toContain('    --color-primary: #0b6d9f;')
            ->toContain('    --radius-box: 1rem;')
            ->toEndWith("@import \"tailwindcss/theme.css\" theme(reference);\n@import \"tailwindcss/utilities.css\" layer(utilities);\n")
            ->not->toContain('preflight')
            ->not->toContain('@plugin');
    });

    it('gives nothing for a styles.css that no Tailwind theme compiles', function (): void {
        expect(Mage::getModel('core/design_content_previewCss')->getCss(previewStylesFile('legacy/default')))->toBeNull();
    });

    it('follows the local imports, and a later file wins', function (): void {
        $dir = Mage::getBaseDir('tmp') . '/preview_css_test_' . bin2hex(random_bytes(4));
        mkdir($dir . '/partials', 0777, true);
        file_put_contents($dir . '/entry.css', implode("\n", [
            '@import "tailwindcss" source(none);',
            '@import "./partials/_tokens.css";',
            '/* @theme { --in-a-comment: 1px; } */',
            '@theme { --text-13: 13px; --brand: red; }',
        ]));
        file_put_contents($dir . '/partials/_tokens.css', implode("\n", [
            '@plugin "daisyui/theme" { name: "x"; --color-primary: blue; }',
            '@theme { --text-13: 1rem; }',
        ]));

        try {
            $declarations = Mage::getModel('core/design_content_previewCss')->collectDeclarations($dir . '/entry.css');
        } finally {
            unlink($dir . '/partials/_tokens.css');
            unlink($dir . '/entry.css');
            rmdir($dir . '/partials');
            rmdir($dir);
        }

        expect($declarations)->toBe([
            '--color-primary' => '    --color-primary: blue;',
            '--text-13' => '    --text-13: 13px;',
            '--brand' => '    --brand: red;',
        ]);
    });
});

describe('the block of the preview page', function () {
    beforeEach(function (): void {
        $this->design = Mage::getDesign();
        $this->previousDesign = [$this->design->getArea(), $this->design->getPackageName(), $this->design->getTheme('skin')];
    });

    afterEach(function (): void {
        [$area, $package, $theme] = $this->previousDesign;
        $this->design->setArea($area)->setPackageName($package)->setTheme($theme);
    });

    it('adds the browser build and its stylesheet in a Tailwind theme', function (): void {
        $this->design->setArea('frontend')->setPackageName('base')->setTheme('default');

        $html = Mage::app()->getLayout()->createBlock('cms/preview_tailwind')->toHtml();

        expect($html)->toContain('<script src="' . Mage::getBaseUrl('js') . 'tailwindcss-browser.js?v=')
            ->toContain('<style type="text/tailwindcss">')
            ->toContain('--breakpoint-nav: 771px;');
    });

    it('adds nothing in a theme without Tailwind', function (): void {
        $this->design->setArea('frontend')->setPackageName('legacy')->setTheme('default');

        expect(Mage::app()->getLayout()->createBlock('cms/preview_tailwind')->toHtml())->toBe('');
    });

    it('is in the head of the preview page only', function (): void {
        $layout = simplexml_load_file(Mage::getBaseDir('design') . '/frontend/base/default/layout/cms.xml');

        expect($layout->xpath('//block[@type="cms/preview_tailwind"]'))->toHaveCount(1)
            ->and($layout->xpath('/layout/cms_preview_view/reference[@name="head"]/block[@type="cms/preview_tailwind"]'))->toHaveCount(1);
    });
});
