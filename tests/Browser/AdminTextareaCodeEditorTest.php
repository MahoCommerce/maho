<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const TEXTAREA_EDITOR_ADMIN_USER = 'pest-textarea-editor';
const TEXTAREA_EDITOR_ADMIN_PASSWORD = 'PestTextareaEditor123!';

beforeEach(function () {
    deleteTextareaEditorAdmin();

    $user = Mage::getModel('admin/user')
        ->setUsername(TEXTAREA_EDITOR_ADMIN_USER)
        ->setFirstname('Pest')
        ->setLastname('Code Editor')
        ->setEmail('pest-textarea-editor@example.test')
        ->setPassword(TEXTAREA_EDITOR_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();
});

afterEach(fn() => deleteTextareaEditorAdmin());

function deleteTextareaEditorAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(TEXTAREA_EDITOR_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

function editTextareaCode(object $page, string $id, string $value): mixed
{
    return $page->page()->evaluate('([id, value]) => {
        const field = document.getElementById(id);
        const view = field.mahoCodeEditor.view;
        view.dispatch({ changes: { from: 0, to: view.state.doc.length, insert: value } });
        return new FormData(field.form).get(field.name);
    }', [$id, $value]);
}

it('edits CMS layout updates in the original form fields', function () {
    $page = adminLoginAndVisit(TEXTAREA_EDITOR_ADMIN_USER, TEXTAREA_EDITOR_ADMIN_PASSWORD, '/admin/cms_page/new', '#page_title');
    $page->click('#page_tabs_design_section');
    waitForScript($page, '!!document.getElementById("page_layout_update_xml").mahoCodeEditor', 5);

    $markup = '<reference name="content"></reference>';
    expect(editTextareaCode($page, 'page_layout_update_xml', $markup))->toBe($markup)
        ->and(editTextareaCode($page, 'page_custom_layout_update_xml', $markup))->toBe($markup);

    $page->click('label[for="page_layout_update_xml"]');
    expect($page->script('document.getElementById("page_layout_update_xml").mahoCodeEditor.view.hasFocus'))->toBeTrue();
});

it('keeps edited, loaded and cleared email CSS in sync with the original field', function () {
    $page = adminLoginAndVisit(TEXTAREA_EDITOR_ADMIN_USER, TEXTAREA_EDITOR_ADMIN_PASSWORD, '/admin/system_email_template/new', '#template_subject');
    waitForScript($page, '!!document.getElementById("template_styles").mahoCodeEditor', 5);

    $css = 'p { color: rebeccapurple; }';
    expect(editTextareaCode($page, 'template_styles', $css))->toBe($css);

    $loadStyles = 'async () => {
        await templateControl.load();
        const field = document.getElementById("template_styles");
        return { editor: field.mahoCodeEditor.view.state.doc.toString(), field: field.value };
    }';

    $page->select('#template_select', 'giftcard_email_template');
    $loaded = $page->page()->evaluate($loadStyles);
    expect($loaded['field'])->toContain('.giftcard-box')
        ->and($loaded['editor'])->toBe($loaded['field']);

    $page->select('#template_select', 'sales_email_order_template');
    expect($page->page()->evaluate($loadStyles))->toBe(['editor' => '', 'field' => '']);
});
