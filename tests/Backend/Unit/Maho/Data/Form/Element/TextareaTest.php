<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form;

it('renders the language of the code editor as a data attribute', function () {
    $form = new Form();
    $element = $form->addField('layout_update_xml', 'textarea', [
        'name' => 'layout_update_xml',
        'data-code-editor' => 'xml',
    ]);

    expect($element->getElementHtml())->toContain('data-code-editor="xml"');
});

it('renders no code editor attribute on a plain textarea', function () {
    $form = new Form();
    $element = $form->addField('comment', 'textarea', ['name' => 'comment']);

    expect($element->getElementHtml())->not->toContain('data-code-editor');
});
