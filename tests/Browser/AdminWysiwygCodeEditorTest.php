<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The plain HTML view of the content editor is CodeMirror. It writes the textarea that the form
 * saves, it completes the Tailwind classes of the theme inside a `class` attribute, and it marks
 * a class that the storefront does not style. The Source button shows it inside the editor, also
 * in fullscreen, and Edit HTML opens the HTML of one element in a dialog.
 */

const CODE_EDITOR_ADMIN_USER = 'wysiwyg-code-admin';
const CODE_EDITOR_ADMIN_PASSWORD = 'Password123!';

/** Type `text` at the cursor of the code editor, as keystrokes that open the completion. */
const CODE_EDITOR_TYPE = <<<'JS'
    window.codeEditorType = (text) => {
        const view = window.tiptapEditors.get('block_content').codeEditor.view;
        for (const char of text) {
            const pos = view.state.selection.main.head;
            view.dispatch({ changes: { from: pos, insert: char }, selection: { anchor: pos + 1 }, userEvent: 'input.type' });
        }
        return true;
    };
    true
    JS;

beforeEach(function () {
    deleteCodeEditorAdmin();
    $user = Mage::getModel('admin/user')
        ->setUsername(CODE_EDITOR_ADMIN_USER)
        ->setFirstname('Wysiwyg')
        ->setLastname('Code')
        ->setEmail('wysiwyg-code@example.test')
        ->setPassword(CODE_EDITOR_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();

    $this->block = Mage::getModel('cms/block')
        ->setTitle('Code editor block')
        ->setIdentifier('wysiwyg-code-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<div class="card"><p>Intro</p></div>')
        ->save();
});

afterEach(function () {
    deleteCodeEditorAdmin();
    $this->block->delete();
});

function deleteCodeEditorAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(CODE_EDITOR_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** Open the block, switch to the plain HTML view, and put the cursor after `card`. */
function openCodeEditor(object $test): object
{
    $page = adminLoginAndVisit(
        CODE_EDITOR_ADMIN_USER,
        CODE_EDITOR_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $test->block->getId(),
        '#block_content',
    );
    waitForScript($page, '!!window.tiptapEditors?.get("block_content")?.codeEditor', 20);
    $page->script(CODE_EDITOR_TYPE);
    $page->script('window.tiptapEditors.get("block_content").turnOff()');
    $page->script(<<<'JS'
        (() => {
            const view = window.tiptapEditors.get('block_content').codeEditor.view;
            view.focus();
            view.dispatch({ selection: { anchor: view.state.doc.toString().indexOf('card"') + 4 } });
        })()
        JS);
    return $page;
}

it('shows the plain HTML in CodeMirror and writes each change to the textarea', function () {
    $page = openCodeEditor($this);

    expect($page->script('window.tiptapEditors.get("block_content").codeEditor.isVisible()'))->toBeTrue()
        ->and($page->script('document.getElementById("block_content").checkVisibility()'))->toBeFalse();

    $page->script('codeEditorType(" shadow")');
    expect($page->script('document.getElementById("block_content").value'))->toContain('class="card shadow"');

    // The insert buttons under the field write at the cursor of CodeMirror
    $page->script('updateElementAtCursor(document.getElementById("block_content"), " rounded")');
    expect($page->script('document.getElementById("block_content").value'))->toContain('class="card shadow rounded"');

    // The rich text editor reads the textarea when it comes back
    $page->script('window.tiptapEditors.get("block_content").turnOn()');
    expect($page->script('window.tiptapEditors.get("block_content").codeEditor.isVisible()'))->toBeFalse()
        ->and($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))->toContain('card shadow rounded');
});

it('completes the Tailwind classes of the theme inside a class attribute', function () {
    $page = openCodeEditor($this);

    $page->script('codeEditorType(" bg-pr")');
    waitForScript($page, '!!document.querySelector(".cm-tooltip-autocomplete li")', 20);

    expect($page->script('[...document.querySelectorAll(".cm-tooltip-autocomplete li")].map((item) => item.textContent)'))
        ->toContain('bg-primary');
});

it('marks a class that the storefront does not style', function () {
    $page = openCodeEditor($this);

    $page->script('codeEditorType(" mt-4 md:text-center txet-center")');
    waitForScript($page, '!!document.querySelector(".cm-lintRange-warning")', 20);

    expect($page->script('[...document.querySelectorAll(".cm-lintRange-warning")].map((range) => range.textContent)'))
        ->toBe(['txet-center']);
});

it('shows the source inside the editor with the Source button, and reads it back', function () {
    $page = adminLoginAndVisit(
        CODE_EDITOR_ADMIN_USER,
        CODE_EDITOR_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $this->block->getId(),
        '#block_content',
    );
    waitForScript($page, '!!window.tiptapEditors?.get("block_content")?.codeEditor', 20);

    $page->script('document.querySelector("#block_content_toolbar button[data-source]").click()');
    expect($page->script('window.tiptapEditors.get("block_content").wrapper.contains(window.tiptapEditors.get("block_content").codeEditor.dom)'))->toBeTrue()
        ->and($page->script('window.tiptapEditors.get("block_content").isTiptapActive()'))->toBeFalse()
        ->and($page->script('[...document.querySelectorAll("#block_content_toolbar button:not(:disabled)")].map((button) => button.title)'))
        ->toContain('Source')
        ->not->toContain('Bold');

    $page->script(<<<'JS'
        (() => {
            const view = window.tiptapEditors.get('block_content').codeEditor.view;
            view.dispatch({ changes: { from: 0, insert: '<h2>From the source</h2>' } });
        })()
        JS);
    expect($page->script('document.getElementById("block_content").value'))->toContain('<h2>From the source</h2>');

    $page->script('document.querySelector("#block_content_toolbar button[data-source]").click()');
    expect($page->script('window.tiptapEditors.get("block_content").isTiptapActive()'))->toBeTrue()
        ->and($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))->toContain('<h2>From the source</h2>');
});

it('edits the HTML of one cell from its popup menu, in one step that undo reverts', function () {
    $this->block->setContent(
        '<div data-type="maho-columns" data-preset="2-equal" data-gap="medium" data-style="none">'
        . '<div data-type="maho-column"><p>Left</p></div>'
        . '<div data-type="maho-column" class="card"><p>Right</p></div>'
        . '</div>',
    )->save();
    $page = adminLoginAndVisit(
        CODE_EDITOR_ADMIN_USER,
        CODE_EDITOR_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $this->block->getId(),
        '#block_content',
    );
    waitForScript($page, '!!window.tiptapEditors?.get("block_content")?.codeEditor', 20);

    $page->script('document.querySelectorAll(".ProseMirror [data-type=\\"maho-columns\\"] .cell-badge")[1].click()');
    $page->script('document.querySelector("#block_content_cell_bubble_menu button[title=\\"Edit HTML\\"]").click()');
    waitForScript($page, '!!document.querySelector("#tiptap-element-source")?.codeView');

    expect($page->script('document.querySelector("#tiptap-element-source").codeView.state.doc.toString()'))
        ->toContain('class="card"')
        ->toContain('<p>Right</p>')
        ->not->toContain('Left');

    $page->script(<<<'JS'
        (() => {
            const view = document.querySelector('#tiptap-element-source').codeView;
            view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: '<div data-type="maho-column" class="card card-border"><p>Right side</p></div>' } });
            [...document.querySelectorAll('#tiptap-element-source button')].find((button) => button.textContent.trim() === 'Update').click();
        })()
        JS);
    waitForScript($page, '!document.querySelector("#tiptap-element-source")');

    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))
        ->toContain('class="card card-border"')
        ->toContain('<p>Right side</p>')
        ->toContain('<p>Left</p>');

    $page->script('window.tiptapEditors.get("block_content").editor.commands.undo()');
    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))
        ->toContain('<p>Right</p>')
        ->not->toContain('Right side');
});
