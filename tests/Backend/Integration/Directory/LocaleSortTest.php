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
        $options = ['ö' => &$label, 'z' => 'Second value', 999 => 'Removed value'];
        unset($options[999]);

        Mage::helper('core/string')->ksortMultibyte($options);
        $label = 'Updated value';
        $options[] = 'Appended value';

        expect($options)->toBe(['z' => 'Second value', 'ö' => 'Updated value', 1000 => 'Appended value']);
    } finally {
        $locale->setLocaleCode($previous);
    }
});
