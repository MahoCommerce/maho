<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Fullscreen is the design workspace of the content editor: a Blocks pane inserts the
 * layouts, a Preview pane shows the draft on the storefront, and a Button dialog writes a
 * theme button. A block moves with the keyboard, also out of a column.
 */

const WORKSPACE_ADMIN_USER = 'editor-workspace-admin';
const WORKSPACE_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    deleteWorkspaceAdmin();
    $user = Mage::getModel('admin/user')
        ->setUsername(WORKSPACE_ADMIN_USER)
        ->setFirstname('Editor')
        ->setLastname('Workspace')
        ->setEmail('editor-workspace@example.test')
        ->setPassword(WORKSPACE_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();

    $this->block = Mage::getModel('cms/block')
        ->setTitle('Editor Workspace')
        ->setIdentifier('editor-workspace-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<p>first</p><p>second</p>')
        ->save();
});

afterEach(function () {
    deleteWorkspaceAdmin();
    $this->block->delete();
});

function deleteWorkspaceAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(WORKSPACE_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** Poll $script in the page until it returns 1. */
function workspaceWaitFor(object $page, string $script, string $what, int $seconds = 15): void
{
    $deadline = microtime(true) + $seconds;
    while ($page->script($script) !== 1) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("Timed out: {$what}");
        }
        usleep(100_000);
    }
}

function visitWorkspaceBlock(Mage_Cms_Model_Block $block): object
{
    $page = adminLoginAndVisit(
        WORKSPACE_ADMIN_USER,
        WORKSPACE_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $block->getId(),
        '#block_content',
    );
    workspaceWaitFor($page, 'window.tiptapEditors?.get("block_content")?.editor ? 1 : 0', 'editor');
    return $page;
}

function openWorkspace(object $page): void
{
    $page->script('document.querySelector(\'#block_content_wrapper button[data-command="toggleFullscreen"]\').click()');
    workspaceWaitFor($page, 'document.querySelector(".tiptap-blocks-pane") ? 1 : 0', 'blocks pane');
}

it('inserts a layout from the Blocks pane of the fullscreen editor', function () {
    $page = visitWorkspaceBlock($this->block);
    openWorkspace($page);

    $page->script('window.tiptapEditors.get("block_content").editor.commands.focus("end")');
    $page->script('document.querySelector(\'.tiptap-blocks-tile[title="2 Columns"]\').click()');

    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML().includes(\'data-preset="2-equal"\') ? 1 : 0'))->toBe(1);

    $page->script('document.querySelector(".tiptap-blocks-search input").value = "hero 4"');
    $page->script('document.querySelector(".tiptap-blocks-search input").dispatchEvent(new Event("input"))');
    expect($page->script('[...document.querySelectorAll(".tiptap-blocks-tile")].filter((tile) => !tile.hidden).map((tile) => tile.title).join("|")'))
        ->toBe('Hero + 4 Cards');
});

it('shows the draft in the Preview pane before a save', function () {
    $page = visitWorkspaceBlock($this->block);
    openWorkspace($page);
    workspaceWaitFor($page, 'document.querySelector(".tiptap-preview-frame.is-current[src]") ? 1 : 0', 'first preview', 30);

    $page->script('(() => { const e = window.tiptapEditors.get("block_content").editor; e.commands.focus("start"); e.commands.insertContent("<p>WORKSPACEPROBE</p>"); })()');
    workspaceWaitFor(
        $page,
        'document.querySelector(".tiptap-preview-frame.is-current")?.contentDocument?.body?.innerText.includes("WORKSPACEPROBE") ? 1 : 0',
        'draft in the preview',
        30,
    );

    expect(Mage::getModel('cms/block')->load($this->block->getId())->getContent())->not->toContain('WORKSPACEPROBE');
});

it('moves a block with the keyboard command, also out of a column', function () {
    $page = visitWorkspaceBlock($this->block);
    $html = $page->script('(() => {
        const editor = window.tiptapEditors.get("block_content").editor;
        editor.commands.setContent("<p>outside</p>");
        editor.commands.focus("end");
        editor.commands.insertColumns("2-equal");
        editor.commands.insertContent("INNER");
        editor.commands.moveBlockUp();
        return editor.getHTML();
    })()');

    expect($html)->toStartWith('<p>outside</p><p>INNER</p><div data-preset="2-equal"');
});

it('writes a theme button and reads it back for editing', function () {
    $page = visitWorkspaceBlock($this->block);
    $page->script('(() => { const e = window.tiptapEditors.get("block_content").editor; e.commands.focus("end"); e.commands.openButtonDialog(); })()');
    workspaceWaitFor($page, 'document.querySelector("#tiptap-button-dialog[open]") ? 1 : 0', 'button dialog');

    $page->script('document.querySelector("#tiptap-button-dialog-ok").click()');
    expect($page->script('document.querySelector(".tiptap-button-error").hidden ? 1 : 0'))->toBe(0);

    $page->script('(() => {
        document.querySelector("#tiptap-button-label").value = "Shop now";
        document.querySelector("#tiptap-button-href").value = "/sale";
        document.querySelector("#tiptap-button-color").value = "accent";
        document.querySelector("#tiptap-button-style").value = "outline";
        document.querySelector("#tiptap-button-size").value = "lg";
        document.querySelector("#tiptap-button-dialog-ok").click();
    })()');
    workspaceWaitFor($page, 'document.querySelector("#tiptap-button-dialog") ? 0 : 1', 'dialog closed');

    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))
        ->toContain('<a class="btn btn-accent btn-outline btn-lg" href="/sale">Shop now</a>');

    $page->script('document.querySelector("#block_content_wrapper .ProseMirror a.btn").click()');
    workspaceWaitFor($page, 'document.querySelector("#tiptap-button-dialog[open]") ? 1 : 0', 'edit dialog');
    expect($page->script('[document.querySelector("#tiptap-button-label").value, document.querySelector("#tiptap-button-color").value, document.querySelector("#tiptap-button-style").value, document.querySelector("#tiptap-button-size").value].join("|")'))
        ->toBe('Shop now|accent|outline|lg');
});
