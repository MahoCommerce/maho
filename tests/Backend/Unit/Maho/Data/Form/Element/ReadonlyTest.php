<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form\Element\Text;

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Data\Form\Element\AbstractElement::serialize() with readonly', function () {
    it('writes the readonly attribute when the element is read-only', function () {
        $element = new Text(['name' => 'code', 'readonly' => true]);

        expect($element->serialize($element->getHtmlAttributes()))->toContain('readonly=');
    });

    it('writes no readonly attribute when the element is not read-only', function () {
        $element = new Text(['name' => 'code', 'readonly' => false]);

        expect($element->serialize($element->getHtmlAttributes()))->not->toContain('readonly');
    });
});
