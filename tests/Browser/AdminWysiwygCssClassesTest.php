<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The CSS Classes dialog of the content editor changes the classes of a block, a div, a link
 * or a generic inline element at the cursor, and wraps selected text in a new span. The popup
 * menu of a table, a layout or a cell has a row with the classes of its element. Both warn
 * about a class that the storefront does not style.
 */

const CSS_CLASSES_ADMIN_USER = 'wysiwyg-classes-admin';
const CSS_CLASSES_ADMIN_PASSWORD = 'Password123!';

/** Helpers that the tests call in the page: select text, open the dialog, edit a target, confirm. */
const CSS_CLASSES_HELPERS = <<<'JS'
    window.classTest = {
        editor: () => window.tiptapEditors.get('block_content').editor,
        select(text, start = 1, length = 0) {
            let pos = null;
            this.editor().state.doc.descendants((node, offset) => {
                if (pos === null && node.isText && node.text.includes(text)) {
                    pos = offset + node.text.indexOf(text) + start;
                }
            });
            this.editor().commands.setTextSelection({ from: pos, to: pos + length });
        },
        open() {
            this.editor().commands.openClassDialog();
            return [...document.querySelectorAll('#tiptap-class-target option')].map((option) => option.textContent);
        },
        set(label, value) {
            const select = document.querySelector('#tiptap-class-target');
            select.value = [...select.options].find((option) => option.textContent === label).value;
            select.dispatchEvent(new Event('change'));
            const input = document.querySelector('#tiptap-class-value');
            const before = input.value;
            input.value = value;
            input.dispatchEvent(new Event('input'));
            return before;
        },
        ok() {
            [...document.querySelectorAll('#tiptap-class-dialog button')].find((button) => button.textContent.trim() === 'Update').click();
            return true;
        },
        html: () => window.classTest.editor().getHTML(),
    };
    true
    JS;

beforeEach(function () {
    deleteCssClassesAdmin();
    $user = Mage::getModel('admin/user')
        ->setUsername(CSS_CLASSES_ADMIN_USER)
        ->setFirstname('Wysiwyg')
        ->setLastname('Classes')
        ->setEmail('wysiwyg-classes@example.test')
        ->setPassword(CSS_CLASSES_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();

    $this->block = Mage::getModel('cms/block')
        ->setTitle('CSS classes block')
        ->setIdentifier('wysiwyg-classes-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<div class="card"><p>Intro <a href="/sale" class="link">sale</a> and <small class="note">fine print</small> text</p></div>')
        ->save();
});

afterEach(function () {
    deleteCssClassesAdmin();
    $this->block->delete();
});

function deleteCssClassesAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(CSS_CLASSES_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

function openCssClassesBlock(object $test): object
{
    $page = adminLoginAndVisit(
        CSS_CLASSES_ADMIN_USER,
        CSS_CLASSES_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $test->block->getId(),
        '#block_content',
    );
    waitForScript($page, '!!window.tiptapEditors?.get("block_content")?.editor', 20);
    $page->script(CSS_CLASSES_HELPERS);
    return $page;
}

it('changes the classes of the elements at the cursor and wraps selected text in a span', function () {
    $page = openCssClassesBlock($this);

    $page->script('classTest.select("sale")');
    expect($page->script('classTest.open()'))->toBe(['a', 'p', 'div'])
        ->and($page->script('classTest.set("a", "link link-primary")'))->toBe('link')
        ->and($page->script('classTest.set("p", "text-sm  text-sm mt-2")'))->toBe('')
        ->and($page->script('classTest.set("div", "card card-border")'))->toBe('card');
    $page->script('classTest.ok()');

    $page->script('classTest.select("fine print")');
    expect($page->script('classTest.open()'))->toBe(['small', 'p', 'div'])
        ->and($page->script('classTest.set("small", "")'))->toBe('note');
    $page->script('classTest.ok()');

    $page->script('classTest.select("text", 0, 4)');
    expect($page->script('classTest.open()')[0])->toBe('New span around the selected text');
    $page->script('classTest.set("New span around the selected text", "font-bold")');
    $page->script('classTest.ok()');

    expect($page->script('classTest.html()'))
        ->toContain('<div class="card card-border">')
        ->toContain('<p class="text-sm mt-2">')
        ->toContain('class="link link-primary"')
        ->toContain('<small>fine print</small>')
        ->toContain('<span class="font-bold">text</span>');
});

it('warns about a class that the storefront does not style', function () {
    $page = openCssClassesBlock($this);

    $page->script('classTest.select("Intro")');
    $page->script('classTest.open()');
    $page->script('classTest.set("p", "mt-4 md:text-center card txet-center")');

    waitForScript($page, '!document.querySelector(".tiptap-class-warning").hidden', 20);
    $warning = $page->script('document.querySelector(".tiptap-class-warning").textContent');

    expect($warning)->toContain('txet-center')
        ->not->toContain('mt-4')
        ->not->toContain('md:text-center')
        ->not->toContain(' card');
});

it('changes the classes of a table and of a column in the row of their popup menu', function () {
    $this->block->setContent(
        '<table><tbody><tr><td><p>Cell text</p></td></tr></tbody></table>'
        . '<div data-type="maho-columns" data-preset="2-equal" data-gap="medium" data-style="none">'
        . '<div data-type="maho-column"><p>Left</p></div>'
        . '<div data-type="maho-column" class="card"><p>Right</p></div>'
        . '</div>',
    )->save();
    $page = openCssClassesBlock($this);
    $row = fn(string $menu): string => "document.querySelector('#block_content_{$menu}_bubble_menu .toolbar-class-row input')";

    // The table menu has no badge: it shows for the table around the cursor
    $page->script('classTest.editor().commands.focus(); classTest.select("Cell text")');
    waitForScript($page, "document.querySelector('#block_content_table_bubble_menu').style.display === 'flex'");
    $page->script(<<<JS
        (() => {
            const input = {$row('table')};
            input.focus();
            input.value = 'table  table-zebra';
            input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
        })()
        JS);

    // A cell menu shows the cell of its badge, also when the cursor is in another element
    $page->script("document.querySelectorAll('.ProseMirror [data-type=\"maho-columns\"] .cell-badge')[1].click()");
    waitForScript($page, "document.querySelector('#block_content_cell_bubble_menu').style.display === 'flex'");
    expect($page->script("{$row('cell')}.value"))->toBe('card');
    $page->script(<<<JS
        (() => {
            const input = {$row('cell')};
            input.value = 'card card-border';
            input.dispatchEvent(new Event('change'));
        })()
        JS);

    expect($page->script('classTest.html()'))
        ->toContain('class="table table-zebra"')
        ->toContain('class="card card-border"')
        ->toContain('<div data-type="maho-column"><p>Left</p></div>');
});
