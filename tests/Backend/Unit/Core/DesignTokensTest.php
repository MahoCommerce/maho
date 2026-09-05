<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/** Every path the token map declares, so a test can clear the whole group. */
function designTokenPaths(): array
{
    $paths = [Mage_Core_Model_Design_Tokens::CUSTOM_CSS_PATH];
    foreach (Mage::getConfig()->getNode(Mage_Core_Model_Design_Tokens::CONFIG_NODE)->children() as $token) {
        $paths[] = trim((string) $token->path);
    }
    return $paths;
}

function designTokens(array $values = []): array
{
    $store = Mage::app()->getStore();
    foreach (designTokenPaths() as $path) {
        $store->setConfig($path, '');
    }
    foreach ($values as $name => $value) {
        $store->setConfig('design/tokens/' . $name, $value);
    }
    return Mage::getModel('core/design_tokens')->resolve();
}

it('emits nothing while every field is empty', function () {
    expect(designTokens())->toBe([]);
    expect(Mage::getModel('core/design_tokens')->toCss())->toBe('');
});

it('maps a field to the variable the config declares', function () {
    expect(designTokens(['color_rating' => '#e8890c']))
        ->toHaveKey('--maho-color-rating', '#e8890c');
});

it('picks the content ink with the higher contrast ratio', function (string $surface, string $expected) {
    expect(designTokens(['color_primary' => $surface]))
        ->toHaveKey('--color-primary-content', $expected);
})->with([
    'dark primary takes light ink' => ['#0b6d9f', '#ffffff'],
    'light primary takes dark ink' => ['#f5e6c8', '#101418'],
    'black takes light ink' => ['#000000', '#ffffff'],
    'white takes dark ink' => ['#ffffff', '#101418'],
]);

it('reads the editor palette from the theme file and lets a configured token win', function () {
    $store = Mage::app()->getStore();
    foreach (designTokenPaths() as $path) {
        $store->setConfig($path, '');
    }
    $store->setConfig('design/package/name', 'maho');
    $store->setConfig('design/theme/default', 'default');

    $palette = Mage::getModel('core/design_tokens')->palette((int) $store->getId());
    expect($palette)->toHaveKey('--color-primary', '#0b6d9f')
        ->and($palette)->toHaveKey('--color-neutral-content', '#f4f4f5')
        ->and(array_keys($palette))->toEqualCanonicalizing(Mage_Core_Model_Design_Tokens::PALETTE_VARS);

    $store->setConfig('design/tokens/color_primary', '#0e7a5f');
    $css = Mage::getModel('core/design_tokens')->editorCss((int) $store->getId());
    expect($css)->toStartWith('.ProseMirror{')
        ->and($css)->toContain('--color-primary:#0e7a5f;')
        ->and($css)->not->toContain('#0b6d9f;');

    $store->setConfig('design/package/name', 'base');
    expect(Mage::getModel('core/design_tokens')->palette((int) $store->getId()))
        ->toHaveKey('--color-primary', '#0e7a5f')
        ->not->toHaveKey('--color-neutral');
});

it('derives the quiet surfaces from the chosen page background', function () {
    $vars = designTokens(['color_surface' => '#ffffff', 'color_ink' => '#1c2126']);

    expect($vars['--color-base-200'])->toBe('color-mix(in oklab, #ffffff, #1c2126 4%)');
    expect($vars['--color-base-300'])->toBe('color-mix(in oklab, #ffffff, #1c2126 12%)');
});

it('falls back to the derived ink when only the background is set', function () {
    expect(designTokens(['color_surface' => '#ffffff'])['--color-base-200'])
        ->toContain('#101418');
});

it('stores the raw value a token takes, not a name for it', function () {
    expect(designTokens(['radius_field' => '999px']))->toBe(['--radius-field' => '999px']);
});

it('writes every variable a field lists', function () {
    expect(designTokens(['control_size' => '0.28rem']))->toBe([
        '--size-field' => '0.28rem',
        '--size-selector' => '0.28rem',
    ]);
});

it('takes a font stack verbatim', function () {
    expect(designTokens(['font_body' => "'Karla', system-ui, sans-serif"]))
        ->toBe(['--font-body' => "'Karla', system-ui, sans-serif"]);
});

it('drops a value that could close the declaration or the element', function (string $value) {
    expect(designTokens(['title_tracking' => $value]))->toBe([]);
})->with([
    ['0.02em;color:red'],
    ['0.02em}body{display:none'],
    ['0.02em</style><script>alert(1)</script>'],
    ['0.02em/*'],
]);

it('refuses the same value on save', function (string $value) {
    $model = Mage::getModel('adminhtml/system_config_backend_design_token')->setValue($value);

    expect(fn() => (function () {
        return $this->_beforeSave();
    })->call($model))
        ->toThrow(Mage_Core_Exception::class);
})->with([
    ['0.02em;color:red'],
    ['0.02em}body{display:none'],
    ['0.02em/*'],
    [str_repeat('a', 513)],
]);

it('accepts a plain value on save', function () {
    $model = Mage::getModel('adminhtml/system_config_backend_design_token')->setValue('  -0.02em  ');
    (function () {
        return $this->_beforeSave();
    })->call($model);

    expect($model->getValue())->toBe('-0.02em');
});

it('picks up a token another module merged into the map', function () {
    $config = Mage::getConfig();
    $config->setNode('global/design/tokens/acme_overlay/path', 'design/tokens/acme_overlay', true);
    $config->setNode('global/design/tokens/acme_overlay/var', '--acme-overlay', true);

    expect(designTokens(['acme_overlay' => '0.4']))->toHaveKey('--acme-overlay', '0.4');
});

it('repeats every declaration in the dark block', function () {
    designTokens(['color_rating' => '#e8890c']);
    $css = Mage::getModel('core/design_tokens')->toCss();

    expect($css)->toStartWith(':root{--maho-color-rating:#e8890c;}')
        ->and($css)->toContain('@media (prefers-color-scheme:dark){:root{--maho-color-rating:#e8890c;}}');
});

it('strips every angle bracket that could leave the style element', function () {
    designTokens(['custom_css' => '.btn > .icon{opacity:.5}</style><script>alert(1)</script>']);

    expect(Mage::getModel('core/design_tokens')->toCss())
        ->toBe('.btn > .icon{opacity:.5}/style>script>alert(1)/script>');
});

it('points the contrast badge at its partner field and names it', function (string $field, string $against, string $caption) {
    $element = new Maho\Data\Form\Element\Color([
        'name' => "groups[tokens][fields][{$field}][value]",
        'html_id' => "design_tokens_{$field}",
    ]);
    $element->setForm(new Maho\Data\Form());
    $element->setData('original_data', ['contrast_against' => $against]);
    $element->setData('field_config', Mage::getSingleton('adminhtml/config')
        ->getSection('design')->groups->tokens->fields->{$field});

    $block = Mage::app()->getLayout()->createBlock('adminhtml/system_config_form_field_design_contrast');
    $html = (function () use ($element) {
        return $this->_getElementHtml($element);
    })->call($block);

    expect($html)->toContain("groups[tokens][fields][{$against}][value]")
        ->and($html)->toContain('data-for="design_tokens_' . $field . '"')
        ->and($html)->toContain('<span class="contrast-caption">' . $caption . '</span>');
})->with([
    ['color_ink', 'color_surface', 'Contrast with Page Background'],
    ['footer_ink', 'footer_bg', 'Contrast with Footer Background'],
]);

it('renders no badge when the field names no partner', function () {
    $element = new Maho\Data\Form\Element\Color(['name' => 'x', 'html_id' => 'x']);
    $element->setForm(new Maho\Data\Form());

    $block = Mage::app()->getLayout()->createBlock('adminhtml/system_config_form_field_design_contrast');
    $html = (function () use ($element) {
        return $this->_getElementHtml($element);
    })->call($block);

    expect($html)->not->toContain('contrast-check');
});

it('refuses a value that does not match its declared shape', function (string $name, string $value) {
    expect(designTokens([$name => $value]))->toBe([]);
})->with([
    'depth is a flag, not a number' => ['depth', '50'],
    'a radius needs a unit' => ['radius_field', '50'],
    'a weight has a ceiling' => ['title_weight', '1200'],
    'a case is one of four words' => ['btn_case', 'shouty'],
    'a font stack is not a function' => ['font_body', 'url(evil)'],
]);

it('accepts the shapes each setting declares', function (string $name, string $value) {
    expect(designTokens([$name => $value]))->not->toBe([]);
})->with([
    ['depth', '1'],
    ['radius_field', '999px'],
    ['radius_box', '0'],
    ['title_tracking', '-0.02em'],
    ['title_weight', '600'],
    ['title_weight', 'bold'],
    ['btn_case', 'uppercase'],
    ['font_body', "'Karla', system-ui, sans-serif"],
]);

it('tells the merchant what shape the value must take', function (string $path, string $value, string $expected) {
    $model = Mage::getModel('adminhtml/system_config_backend_design_token')
        ->setPath('design/tokens/' . $path)->setValue($value);

    expect(fn() => (function () {
        return $this->_beforeSave();
    })->call($model))->toThrow(Mage_Core_Exception::class, $expected);
})->with([
    ['depth', '50', 'Enter a whole number between 0 and 1.'],
    ['radius_field', '50', 'Enter a CSS length'],
    ['btn_case', 'shouty', 'Allowed words: none, uppercase, lowercase, capitalize.'],
    ['font_stylesheet', 'javascript:alert(1)', 'Enter a full http:// or https:// address.'],
]);

it('reads a palette for every installed theme', function () {
    $root = Mage::getBaseDir('skin') . DS . 'frontend' . DS . 'maho';

    foreach (Maho::listDirectories($root) as $theme) {
        expect(Mage_Core_Model_Design_Tokens::paletteOf('maho', $theme))
            ->toHaveKeys(['--color-base-100', '--color-primary', '--color-base-content']);
    }
});

it('reports no palette for a theme that ships no stylesheet', function () {
    expect(Mage_Core_Model_Design_Tokens::paletteOf('maho', 'nosuchtheme'))->toBe([]);
});

function renderDesignField(string $kind, string $htmlId): string
{
    $element = new Maho\Data\Form\Element\Text(['name' => 'x', 'html_id' => $htmlId]);
    $element->setForm(new Maho\Data\Form());
    $block = Mage::app()->getLayout()->createBlock('adminhtml/system_config_form_field_design_' . $kind);

    return (function () use ($element) {
        return $this->_getElementHtml($element);
    })->call($block);
}

it('offers the importer a field for every variable the map declares', function () {
    preg_match('/initImport\((.*)\);/', renderDesignField('import', 'design_tokens_import'), $match);
    $config = json_decode($match[1], true);

    expect($config['map'])->toHaveKeys(['--color-primary', '--radius-field', '--border', '--depth', '--size-field'])
        ->and($config['colors'])->toContain('--color-primary')
        ->and($config['colors'])->not->toContain('--radius-field');
});

it('gives the preview the variables each field writes', function () {
    preg_match('/initPreview\((.*)\);/', renderDesignField('preview', 'design_tokens_preview'), $match);
    $tokens = json_decode($match[1], true)['tokens'];

    $byName = array_column($tokens, null, 'name');
    expect($byName['groups[tokens][fields][control_size][value]']['vars'])
        ->toBe(['--size-field', '--size-selector'])
        ->and($byName['groups[tokens][fields][color_primary][value]']['derive'])->toBe('content');
});

it('offers a palette for every theme of every package', function () {
    preg_match('/initThemeSelect\((.*)\);/', renderDesignField('skin', 'design_theme_skin'), $match);
    $config = json_decode($match[1], true);

    expect($config['packageId'])->toBe('design_package_name')
        ->and($config['palettes']['maho'])->toHaveKey('fashion')
        ->and($config['palettes']['maho']['fashion'])->not->toBeEmpty();
});
