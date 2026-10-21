<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * Fullscreen is the page builder of the content editor: a Blocks pane inserts the
 * layouts, a Preview pane shows the draft on the storefront, and a Button dialog writes a
 * theme button. A block moves with the keyboard, also out of a column.
 */

const PAGE_BUILDER_ADMIN_USER = 'page-builder-admin';
const PAGE_BUILDER_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    deletePageBuilderAdmin();
    $user = Mage::getModel('admin/user')
        ->setUsername(PAGE_BUILDER_ADMIN_USER)
        ->setFirstname('Page')
        ->setLastname('Builder')
        ->setEmail('page-builder@example.test')
        ->setPassword(PAGE_BUILDER_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();

    $this->block = Mage::getModel('cms/block')
        ->setTitle('Page Builder')
        ->setIdentifier('page-builder-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<p>first</p><p>second</p>')
        ->save();
});

afterEach(function () {
    deletePageBuilderAdmin();
    $this->block->delete();
});

function deletePageBuilderAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(PAGE_BUILDER_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

/** Poll $script in the page until it returns 1. */
function pageBuilderWaitFor(object $page, string $script, string $what, int $seconds = 15): void
{
    $deadline = microtime(true) + $seconds;
    while ($page->script($script) !== 1) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException("Timed out: {$what}");
        }
        usleep(100_000);
    }
}

function visitPageBuilderBlock(Mage_Cms_Model_Block $block): object
{
    $page = adminLoginAndVisit(
        PAGE_BUILDER_ADMIN_USER,
        PAGE_BUILDER_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $block->getId(),
        '#block_content',
    );
    pageBuilderWaitFor($page, 'window.tiptapEditors?.get("block_content")?.editor ? 1 : 0', 'editor');
    return $page;
}

function openPageBuilder(object $page): void
{
    $page->script('document.querySelector(\'#block_content_wrapper button[data-command="toggleFullscreen"]\').click()');
    pageBuilderWaitFor($page, 'document.querySelector(".tiptap-blocks-pane") ? 1 : 0', 'blocks pane');
}

it('inserts a layout from the Blocks pane of the fullscreen editor', function () {
    $page = visitPageBuilderBlock($this->block);
    openPageBuilder($page);

    $page->script('window.tiptapEditors.get("block_content").editor.commands.focus("end")');
    $page->script('document.querySelector(\'.tiptap-blocks-tile[title="2 Columns"]\').click()');

    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML().includes(\'data-preset="2-equal"\') ? 1 : 0'))->toBe(1);

    $page->script('document.querySelector(".tiptap-blocks-search input").value = "hero 4"');
    $page->script('document.querySelector(".tiptap-blocks-search input").dispatchEvent(new Event("input"))');
    expect($page->script('[...document.querySelectorAll(".tiptap-blocks-tile")].filter((tile) => !tile.hidden).map((tile) => tile.title).join("|")'))
        ->toBe('Hero + 4 Cards');
});

it('shows the draft in the Preview pane before a save', function () {
    $page = visitPageBuilderBlock($this->block);
    openPageBuilder($page);
    pageBuilderWaitFor($page, 'document.querySelector(".tiptap-preview-frame.is-current[src]") ? 1 : 0', 'first preview', 30);

    $page->script('(() => { const e = window.tiptapEditors.get("block_content").editor; e.commands.focus("start"); e.commands.insertContent("<p>PAGEBUILDERPROBE</p>"); })()');
    pageBuilderWaitFor(
        $page,
        'document.querySelector(".tiptap-preview-frame.is-current")?.contentDocument?.body?.innerText.includes("PAGEBUILDERPROBE") ? 1 : 0',
        'draft in the preview',
        30,
    );

    expect(Mage::getModel('cms/block')->load($this->block->getId())->getContent())->not->toContain('PAGEBUILDERPROBE');
});

it('marks the content as changed after an edit only, not after Fullscreen opens and closes', function () {
    $page = visitPageBuilderBlock($this->block);
    $page->script('window.pageBuilderChanges = 0; document.querySelector("#block_content").addEventListener("change", () => window.pageBuilderChanges++)');

    openPageBuilder($page);
    pageBuilderWaitFor($page, 'document.querySelector(".tiptap-preview-frame.is-current[src]") ? 1 : 0', 'first preview', 30);
    $page->script('document.querySelector(\'#block_content_wrapper button[data-command="toggleFullscreen"]\').click()');
    // Each transaction syncs the textarea after 200 ms
    usleep(500_000);

    expect($page->script('window.pageBuilderChanges'))->toBe(0)
        ->and($page->script('document.querySelector("#block_content").value'))->toBe('<p>first</p><p>second</p>');

    $page->script('window.tiptapEditors.get("block_content").editor.chain().focus("end").insertContent("<p>third</p>").run()');
    pageBuilderWaitFor($page, 'document.querySelector("#block_content").value.includes("third") ? 1 : 0', 'synced edit');

    expect($page->script('window.pageBuilderChanges'))->toBe(1);
});

it('moves a block with the keyboard command, also out of a column', function () {
    $page = visitPageBuilderBlock($this->block);
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
    $page = visitPageBuilderBlock($this->block);
    $page->script('(() => { const e = window.tiptapEditors.get("block_content").editor; e.commands.focus("end"); e.commands.openButtonDialog(); })()');
    pageBuilderWaitFor($page, 'document.querySelector("#tiptap-button-dialog[open]") ? 1 : 0', 'button dialog');

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
    pageBuilderWaitFor($page, 'document.querySelector("#tiptap-button-dialog") ? 0 : 1', 'dialog closed');

    expect($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))
        ->toContain('<a class="btn btn-accent btn-outline btn-lg" href="/sale">Shop now</a>');

    $page->script('document.querySelector("#block_content_wrapper .ProseMirror a.btn").click()');
    pageBuilderWaitFor($page, 'document.querySelector("#tiptap-button-dialog[open]") ? 1 : 0', 'edit dialog');
    expect($page->script('[document.querySelector("#tiptap-button-label").value, document.querySelector("#tiptap-button-color").value, document.querySelector("#tiptap-button-style").value, document.querySelector("#tiptap-button-size").value].join("|")'))
        ->toBe('Shop now|accent|outline|lg');
});
