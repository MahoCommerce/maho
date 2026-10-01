<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function withInstallTranslation(string $text, string $translation, callable $callback): string
{
    $translator = Mage::app()->getTranslator();
    $original = (fn() => $this->_data)->call($translator);
    (fn() => $this->_data = [$text => $translation] + ($original ?? []))->call($translator);

    try {
        Mage::getDesign()->setArea('install')->setPackageName('default')->setTheme('default');
        return $callback();
    } finally {
        (fn() => $this->_data = $original)->call($translator);
    }
}

it('keeps the sample data script valid when a translation holds an apostrophe', function () {
    $html = withInstallTranslation(
        'Failed to start installation. Please try again.',
        "Impossibile avviare l'installazione. Riprova.",
        fn() => Mage::app()->getLayout()->createBlock('install/sampleData')->toHtml(),
    );

    expect($html)->not->toContain("'Impossibile avviare l'installazione")
        ->and($html)->toContain('showError("Impossibile avviare l\u0027installazione. Riprova.")');
});

it('keeps the administrator script valid when a translation holds an apostrophe', function () {
    $html = withInstallTranslation(
        'Passwords do not match.',
        "Le password non sono uguali, riprova l'inserimento.",
        fn() => Mage::app()->getLayout()->createBlock('install/administrator')->toHtml(),
    );

    expect($html)->toContain('setCustomValidity("Le password non sono uguali, riprova l\u0027inserimento.")');
});
