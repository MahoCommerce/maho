<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Data\Form;
use Maho\Data\Form\Element\Date;

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Data\Form\Element\Date DateTimeImmutable handling', function () {
    // Regression: Locale::utcToStore() / storeToUtc() return DateTimeImmutable.
    // The element previously used `instanceof DateTime` guards in setValue(),
    // getValue() and getElementHtml(), missing immutables and falling through
    // to a preg_match() that threw TypeError on PHP 8 strict types.

    it('accepts a DateTimeImmutable in setValue and round-trips it via getValue', function () {
        $element = new Date();
        $element->setValue(new DateTimeImmutable('2026-05-02 10:15:30'));

        expect($element->getValue('Y-m-d H:i:s'))->toBe('2026-05-02 10:15:30');
        expect($element->getValueInstance())->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('accepts a DateTime in setValue and round-trips it via getValue', function () {
        $element = new Date();
        $element->setValue(new DateTime('2026-05-02 10:15:30'));

        expect($element->getValue('Y-m-d H:i:s'))->toBe('2026-05-02 10:15:30');
        expect($element->getValueInstance())->toBeInstanceOf(DateTime::class);
    });

    it('renders HTML for a DateTimeImmutable value without throwing', function () {
        $form = new Form();
        $element = $form->addField('queue_start_at', 'date', [
            'name' => 'queue_start_at',
            'time' => true,
        ]);
        $element->setValue(new DateTimeImmutable('2026-05-02 10:15:30'));

        $html = $element->getElementHtml();

        expect($html)->toContain('value="2026-05-02T10:15"');
        expect($html)->toContain('type="datetime-local"');
    });
});
