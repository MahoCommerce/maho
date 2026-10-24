<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form;
use Maho\DataObject;

uses(Tests\MahoBackendTestCase::class);

/**
 * The Source button of the content editor switches between the rich text and the HTML, so the
 * field renders no Show / Hide Editor button. The insert buttons under the field belong to the
 * source view: they show when the editor opens in it, because the setting hides the editor.
 */
function editorElementHtml(bool $hidden): string
{
    $form = new Form();
    $element = $form->addField('content', 'editor', [
        'name' => 'content',
        'config' => new DataObject([
            'enabled' => true,
            'hidden' => $hidden,
            'plugins' => [],
            'add_widgets' => true,
            'widget_window_url' => 'https://example.test/widget/',
        ]),
    ]);
    return $element->getElementHtml();
}

it('renders no button that shows or hides the editor', function () {
    expect(editorElementHtml(false))
        ->not->toContain('Show / Hide Editor')
        ->not->toContain('id="togglecontent"');
});

it('shows the insert buttons only when the editor opens in the source view', function () {
    expect(editorElementHtml(true))->toMatch('/class="scalable add-widget plugin"/')
        ->and(editorElementHtml(false))->toMatch('/class="scalable add-widget plugin no-display"/');
});
