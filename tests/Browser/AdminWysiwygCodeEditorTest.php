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
 * a class that the storefront does not style.
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
