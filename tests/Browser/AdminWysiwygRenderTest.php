<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

/**
 * The content editor shows a block directive in a frame, as the storefront shows it, and an
 * icon directive as its SVG. The saved content keeps the directives.
 */

const WYSIWYG_RENDER_ADMIN_USER = 'wysiwyg-render-admin';
const WYSIWYG_RENDER_ADMIN_PASSWORD = 'Password123!';

beforeEach(function () {
    deleteWysiwygRenderAdmin();
    $user = Mage::getModel('admin/user')
        ->setUsername(WYSIWYG_RENDER_ADMIN_USER)
        ->setFirstname('Wysiwyg')
        ->setLastname('Render')
        ->setEmail('wysiwyg-render@example.test')
        ->setPassword(WYSIWYG_RENDER_ADMIN_PASSWORD)
        ->setIsActive()
        ->save();
    $roleId = Mage::getModel('admin/role')->getCollection()
        ->addFieldToFilter('role_type', Mage_Admin_Model_Acl::ROLE_TYPE_GROUP)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    Mage::getModel('admin/user')->load($user->getId())->setRoleIds([$roleId])->saveRelations();

    $this->inner = Mage::getModel('cms/block')
        ->setTitle('Rendered inner block')
        ->setIdentifier('wysiwyg-render-inner-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<p>WIDGETRENDERPROBE</p>')
        ->save();
    $this->holder = Mage::getModel('cms/block')
        ->setTitle('Rendered holder block')
        ->setIdentifier('wysiwyg-render-holder-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('{{block id="' . $this->inner->getIdentifier() . '"}}<p>{{icon name="truck" size="32"}} Free shipping</p>')
        ->save();
});

afterEach(function () {
    deleteWysiwygRenderAdmin();
    $this->holder->delete();
    $this->inner->delete();
});

function deleteWysiwygRenderAdmin(): void
{
    $user = Mage::getModel('admin/user')->loadByUsername(WYSIWYG_RENDER_ADMIN_USER);
    if ($user->getId()) {
        $user->delete();
    }
}

it('shows a block as the storefront shows it and an icon as its SVG, and saves the directives', function () {
    $page = adminLoginAndVisit(
        WYSIWYG_RENDER_ADMIN_USER,
        WYSIWYG_RENDER_ADMIN_PASSWORD,
        '/admin/cms_block/edit/block_id/' . $this->holder->getId(),
        '#block_content',
    );
    waitForScript($page, '!!window.tiptapEditors?.get("block_content")?.editor', 20);

    waitForScript(
        $page,
        '!!document.querySelector("#block_content_wrapper .maho-widget-frame")?.contentDocument?.body?.innerText.includes("WIDGETRENDERPROBE")',
        20,
    );
    waitForScript($page, '!!document.querySelector("#block_content_wrapper .is-rendered-inline svg")', 20);

    expect($page->script('document.querySelector("#block_content_wrapper .maho-widget-frame").getAttribute("sandbox")'))->toBe('allow-same-origin')
        ->and($page->script('window.tiptapEditors.get("block_content").editor.getHTML()'))
        ->toContain('{{block id=&quot;' . $this->inner->getIdentifier() . '&quot;}}')
        ->not->toContain('maho-widget-frame')
        ->not->toContain('<svg');
});
