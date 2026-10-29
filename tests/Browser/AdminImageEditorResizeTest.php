<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The Resize tool shows the new size on the canvas as soon as the width changes, before Apply.
 * Save exported the image at its old size, so a resize without Apply was lost.
 */

/** Open the image editor on a 200x100 image, set the width to 100 in the Resize tool and click Save. */
const IMAGE_EDITOR_RESIZE_SAVE = <<<'JS'
    (async () => {
        window.imageEditorResult = null;
        try {
            const { MahoImageEditor } = await import('/js/mage/adminhtml/image-editor/editor.js');
            const source = document.createElement('canvas');
            source.width = 200;
            source.height = 100;
            source.getContext('2d').fillRect(0, 0, 200, 100);

            const container = document.createElement('div');
            container.style.cssText = 'position:fixed;inset:0;width:1000px;height:700px';
            document.body.appendChild(container);

            window.prompt = () => 'resized';
            const editor = new MahoImageEditor(container, {
                source: source.toDataURL('image/png'),
                filename: 'original',
                saveFormat: 'image/png',
                onSave: async (canvas, blob) => {
                    const bitmap = await createImageBitmap(blob);
                    window.imageEditorResult = { width: bitmap.width, height: bitmap.height };
                },
            });
            await editor.open();

            editor._setActiveTool(editor.tools.find((tool) => tool.name === 'resize'));
            const width = editor._optionsPanel.querySelector('input[type="number"]');
            width.value = '100';
            width.dispatchEvent(new Event('input'));

            await editor._save();
        } catch (error) {
            window.imageEditorResult = { error: String(error) };
        }
    })();
    true
    JS;

it('saves the size that the Resize tool shows when Apply was not clicked', function () {
    $page = waitForPageLoad(visit(MahoServer::baseUrl() . '/admin'), '#username');
    $page->script(IMAGE_EDITOR_RESIZE_SAVE);

    $deadline = microtime(true) + 10;
    while (($result = $page->script('window.imageEditorResult')) === null && microtime(true) < $deadline) {
        usleep(100_000);
    }

    expect($result)->toBe(['width' => 100, 'height' => 50]);
    $page->assertNoJavaScriptErrors();
});
