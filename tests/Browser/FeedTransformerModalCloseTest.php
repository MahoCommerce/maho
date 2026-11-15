<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const FEED_TRANSFORMER_MODAL_CLOSE_SCENARIO = <<<'JS'
(() => {
    window.transformerModalResult = null;
    window.TransformerData = {
        translations: {configure_transformers: 'Transformers', save: 'Save', no_transformers: 'None', add_transformer: 'Add'},
        icons: {arrow_up: '', arrow_down: '', x: ''},
        definitions: {uppercase: {name: 'Uppercase', options: {}}, lowercase: {name: 'Lowercase', options: {}}},
        categories: {Text: {uppercase: {name: 'Uppercase'}, lowercase: {name: 'Lowercase'}}}
    };
    const input = document.createElement('input');
    input.id = 'editor_transformers';
    input.value = 'uppercase';
    document.body.appendChild(input);
    const delay = () => new Promise(resolve => setTimeout(resolve, 30));
    const add = code => {
        const select = document.getElementById('transformer-select');
        select.value = code;
        select.dispatchEvent(new Event('change', {bubbles: true}));
    };
    (async () => {
        TransformerModal.open();
        await delay();
        add('lowercase');
        document.getElementById('transformer-modal-ok').click();
        await delay();
        const applied = input.value;
        const afterApply = TransformerModal.dialog === null && !document.getElementById('transformer-modal');
        TransformerModal.open();
        await delay();
        const reopened = TransformerModal.buildChainString();
        add('uppercase');
        document.querySelector('#transformer-modal .dialog-header button').click();
        await delay();
        const afterCancel = TransformerModal.dialog === null && !document.getElementById('transformer-modal');
        const unchanged = input.value === applied;
        TransformerModal.open();
        await delay();
        const reopenedAfterCancel = TransformerModal.buildChainString();
        document.querySelector('#transformer-modal').dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
        await delay();
        const afterEscape = TransformerModal.dialog === null && !document.getElementById('transformer-modal');
        window.CsvBuilder = {currentColumnIndex: 0, columns: [{transformers: ''}], renderCount: 0,
            render() { this.renderCount++; }};
        initTransformerModalOverrides();
        TransformerModal.open();
        await delay();
        add('uppercase');
        document.getElementById('transformer-modal-ok').click();
        await delay();
        const builderApplied = CsvBuilder.columns[0].transformers === 'uppercase|lowercase|uppercase'
            && CsvBuilder.renderCount === 1 && !('currentColumnIndex' in CsvBuilder);
        const builderClosed = TransformerModal.dialog === null && !document.getElementById('transformer-modal');
        delete window.CsvBuilder;
        window.transformerModalResult = {applied, afterApply, reopened, afterCancel, unchanged, reopenedAfterCancel,
            afterEscape, builderApplied, builderClosed};
    })().catch(error => { window.transformerModalResult = {error: String(error)}; });
    return true;
})()
JS;

it('applies or cancels transformer changes and reopens without a dialog error', function (): void {
    $page = waitForPageLoad(visit(MahoServer::baseUrl() . '/admin'), '#username');
    $page->script(<<<'JS'
        window.transformerScriptsReady = false;
        (async () => {
            for (const src of ['/js/varien/js.js', '/js/maho-dialog.js', '/js/mage/adminhtml/feedmanager-mapping.js']) {
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = src;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.body.appendChild(script);
                });
            }
            window.transformerScriptsReady = true;
        })();
        true
        JS);
    waitForScript($page, 'window.transformerScriptsReady === true');
    $page->script(FEED_TRANSFORMER_MODAL_CLOSE_SCENARIO);
    waitForScript($page, 'window.transformerModalResult !== null');
    expect($page->script('window.transformerModalResult'))->toBe([
        'applied' => 'uppercase|lowercase', 'afterApply' => true, 'reopened' => 'uppercase|lowercase',
        'afterCancel' => true, 'unchanged' => true, 'reopenedAfterCancel' => 'uppercase|lowercase', 'afterEscape' => true,
        'builderApplied' => true, 'builderClosed' => true,
    ]);
    $page->assertNoJavaScriptErrors();
});
