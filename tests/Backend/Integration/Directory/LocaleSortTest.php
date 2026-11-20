<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('sorts country labels in French and keeps their identifiers', function () {
    $locale = Mage::app()->getLocale();
    $previous = $locale->getLocaleCode();
    try {
        $locale->setLocaleCode('fr_FR');
        $countries = Mage::getResourceModel('directory/country_collection')->addCountryIdFilter(['CA', 'EG', 'ZM']);

        expect($countries->toOptionHash())->toBe(['CA' => 'Canada', 'EG' => 'Égypte', 'ZM' => 'Zambie']);
    } finally {
        $locale->setLocaleCode($previous);
    }
});

it('reorders localized keys while retaining references and the next numeric key', function () {
    $locale = Mage::app()->getLocale();
    $previous = $locale->getLocaleCode();
    try {
        $locale->setLocaleCode('sv_SE');
        $label = 'First value';
        $options = ['Zambie' => 'Second value', 'Égypte' => &$label, 999 => 'Removed value'];
        unset($options[999]);

        Mage::helper('core/string')->ksortMultibyte($options);
        $label = 'Updated value';
        $options[] = 'Appended value';

        expect($options)->toBe(['Égypte' => 'Updated value', 'Zambie' => 'Second value', 1000 => 'Appended value']);
    } finally {
        $locale->setLocaleCode($previous);
    }
});

it('sorts localized values in French and renumbers their keys', function () {
    $locale = Mage::app()->getLocale();
    $previous = $locale->getLocaleCode();
    try {
        $locale->setLocaleCode('fr_FR');
        $values = ['x' => 'Zambie', 'y' => 'Égypte'];

        Mage::helper('core/string')->sortMultibyte($values);

        expect($values)->toBe(['Égypte', 'Zambie']);
    } finally {
        $locale->setLocaleCode($previous);
    }
});

it('sorts the country options of the locale model in French', function () {
    $locale = Mage::app()->getLocale();
    $previous = $locale->getLocaleCode();
    try {
        $locale->setLocaleCode('fr_FR');
        $codes = array_column($locale->getOptionCountries(), 'value');

        expect(array_values(array_intersect($codes, ['ZM', 'EG', 'CA'])))->toBe(['CA', 'EG', 'ZM']);
        expect(array_values(array_intersect(array_keys($locale->getCountryTranslationList()), ['ZM', 'EG', 'CA'])))->toBe(['CA', 'EG', 'ZM']);
    } finally {
        $locale->setLocaleCode($previous);
    }
});
