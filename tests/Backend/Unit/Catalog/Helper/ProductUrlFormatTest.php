<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Catalog Product Url Helper format()', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('catalog/product_url');
    });

    describe('transliteration without a locale', function () {
        it('strips accents and lowercases by default', function () {
            expect($this->helper->format('Ünïcödé Prödüct'))->toBe('unicode product');
        });

        it('preserves case when $lower is false', function () {
            expect($this->helper->format('Ünïcödé Prödüct', null, false))->toBe('Unicode Product');
        });

        it('expands ligatures', function () {
            expect($this->helper->format('Ægte Å-Vare'))->toBe('aegte a-vare');
        });

        it('applies the conversion table', function () {
            expect($this->helper->format('Product © 2026 ®'))->toBe('product c 2026 r');
        });

        it('returns an empty string for null', function () {
            expect($this->helper->format(null))->toBe('');
        });

        it('leaves plain ascii untouched', function () {
            expect($this->helper->format('0'))->toBe('0');
            expect($this->helper->format(''))->toBe('');
        });
    });

    describe('locale-specific transliteration', function () {
        it('uses the BGN ruleset for a locale that has one', function () {
            // Any-Latin renders Ж as z, the Russian BGN ruleset as zh
            expect($this->helper->format('Жук', 'ru_RU'))->toBe('zhuk');
            expect($this->helper->format('Жук'))->toBe('zuk');
        });

        it('honours $lower on the locale branch', function () {
            expect($this->helper->format('Жук', 'ru_RU', false))->toBe('Zhuk');
        });

        it('falls back to the generic ruleset for a locale without one', function () {
            expect($this->helper->format('Жук', 'sv_SE'))->toBe('zuk');
            expect($this->helper->format('Жук', 'xx_XX'))->toBe('zuk');
        });

        it('treats an empty locale as no locale', function () {
            expect($this->helper->format('Жук', ''))->toBe('zuk');
        });
    });

    describe('cached transliterators', function () {
        it('returns the same result when rulesets are interleaved', function () {
            $first = $this->helper->format('Жук', 'ru_RU');
            $this->helper->format('Жук');
            $this->helper->format('Жук', 'ru_RU', false);
            $this->helper->format('Ünïcödé Prödüct', 'sv_SE');

            expect($this->helper->format('Жук', 'ru_RU'))->toBe($first)->toBe('zhuk');
        });

        it('keeps each ruleset on its own cache entry', function () {
            $results = [];
            foreach ([null, 'ru_RU', 'sv_SE'] as $locale) {
                foreach ([true, false] as $lower) {
                    $results[] = $this->helper->format('Жук', $locale, $lower);
                }
            }

            expect($results)->toBe(['zuk', 'Zuk', 'zhuk', 'Zhuk', 'zuk', 'Zuk']);
        });

        it('is not affected by helper instance reuse', function () {
            $other = Mage::helper('catalog/product_url');

            expect($other->format('Жук', 'ru_RU'))->toBe($this->helper->format('Жук', 'ru_RU'));
        });

        it('returns the input unchanged for an unusable ruleset and does not retry it', function () {
            $rules = 'Definitely-Not-A-Real-Transliterator-ID';
            $transliterate = new ReflectionMethod(Mage_Catalog_Helper_Product_Url::class, 'transliterate');

            expect(@$transliterate->invoke(null, $rules, 'fallback value'))->toBe('fallback value');

            $cache = (new ReflectionProperty(Mage_Catalog_Helper_Product_Url::class, '_transliterators'))->getValue();
            expect($cache)->toHaveKey($rules);
            expect($cache[$rules])->toBeNull();
        });
    });
});
