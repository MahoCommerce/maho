<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form;

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Data\Form\Element\Image preview', function () {
    it('escapes the stored value in every attribute', function () {
        $form = new Form();
        $element = $form->addField('image', 'image', ['name' => 'image']);
        $element->setValue('x" onmouseover="alert(1)');

        expect($element->getElementHtml())
            ->not->toContain('" onmouseover="')
            ->toContain('&quot; onmouseover=&quot;');
    });
});
