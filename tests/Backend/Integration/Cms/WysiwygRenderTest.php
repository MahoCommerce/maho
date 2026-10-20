<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The content editor shows a widget, a block or an icon as the storefront of a store view
 * shows it. The server renders only these directive types, and only those that the role of
 * the admin can already see.
 */
function wysiwygRenderer(): Mage_Cms_Model_Wysiwyg_Render
{
    /** @var Mage_Cms_Model_Wysiwyg_Render $renderer */
    $renderer = Mage::getSingleton('cms/wysiwyg_render');
    return $renderer;
}

/**
 * @param list<string> $resources
 */
function wysiwygRenderAdmin(array $resources): Mage_Admin_Model_User
{
    $role = Mage::getModel('admin/role')->setData([
        'role_name' => 'wysiwyg_render_' . uniqid(),
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();
    Mage::getModel('admin/rules')->setRoleId($role->getId())->setResources($resources)->saveRel();

    $user = Mage::getModel('admin/user')->setData([
        'username' => 'wysiwyg_render_' . uniqid(),
        'firstname' => 'Pest',
        'lastname' => 'Render',
        'email' => uniqid('wysiwyg-render-') . '@example.test',
        'password' => 'pest-render-password-1234',
        'is_active' => 1,
    ])->save();
    Mage::getModel('admin/user')->setRoleId($role->getId())->setUserId($user->getId())->add();
    $user = Mage::getModel('admin/user')->load($user->getId());

    $session = Mage::getSingleton('admin/session');
    $session->setUser($user);
    $session->refreshAcl($user);
    return $user;
}

function wysiwygRenderDeleteAdmin(Mage_Admin_Model_User $user): void
{
    // Log out first: the activity log cannot record the deletion of the admin who does it
    $session = Mage::getSingleton('admin/session');
    $session->setUser(null);
    $session->setAcl(null);

    $role = $user->getRole();
    $user->delete();
    if ($role->getId()) {
        Mage::getModel('admin/role')->load($role->getId())->delete();
    }
}

/**
 * @param list<string> $directives
 * @return array{head: string, html: array<string, string>}
 */
function wysiwygRenderPost(array $directives, int $storeId): array
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        'http://localhost/admin/cms_wysiwyg/renderDirectives',
        'POST',
        ['directives' => $directives, 'store_id' => (string) $storeId],
    ));
    $response = new Mage_Core_Controller_Response_Http();
    (new Mage_Adminhtml_Cms_WysiwygController($request, $response))->renderDirectivesAction();
    return Mage::helper('core')->jsonDecode($response->getBody());
}

beforeEach(function () {
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $this->block = Mage::getModel('cms/block')
        ->setTitle('Render check')
        ->setIdentifier('wysiwyg-render-' . uniqid())
        ->setStores([0])
        ->setIsActive()
        ->setContent('<p>Rendered block body</p>')
        ->save();
});

afterEach(function () {
    $this->block->delete();
});

describe('directive check', function () {
    it('accepts one widget, block or icon directive', function (string $directive, string $type) {
        expect(wysiwygRenderer()->getDirectiveType($directive))->toBe($type);
    })->with([
        'widget' => ['{{widget type="cms/widget_block" block_id="1"}}', 'widget'],
        'block' => ['{{block id="footer_links"}}', 'block'],
        'icon' => ['{{icon name="truck" size="32"}}', 'icon'],
    ]);

    it('refuses any other text', function (string $directive) {
        expect(wysiwygRenderer()->getDirectiveType($directive))->toBeNull();
    })->with([
        'another type' => ['{{config path="web/secure/base_url"}}'],
        'a variable' => ['{{var store.name}}'],
        'two directives' => ['{{block id="a"}}{{block id="b"}}'],
        'text around it' => ['<p>x</p>{{block id="a"}}'],
        'no directive' => ['<p>x</p>'],
        'too long' => ['{{block id="' . str_repeat('a', Mage_Cms_Model_Wysiwyg_Render::MAX_LENGTH) . '"}}'],
    ]);
});

describe('render', function () {
    it('renders each directive in the store view, with the stylesheets of its theme and no script', function () {
        $block = '{{block id="' . $this->block->getIdentifier() . '"}}';
        $widget = '{{widget type="cms/widget_block" template="cms/widget/static_block/default.phtml" block_id="' . $this->block->getId() . '"}}';
        $icon = '{{icon name="truck" size="32"}}';
        $result = wysiwygRenderer()->render([$block, $widget, $icon], $this->storeId);

        expect($result['html'][$block])->toContain('Rendered block body')
            ->and($result['html'][$widget])->toContain('Rendered block body')
            ->and($result['html'][$icon])->toStartWith('<svg')
            ->and($result['head'])->toContain('css/styles.css')
            ->toContain('css/theme.css')
            ->not->toContain('<script');
    });

    it('gives an empty string for a widget type that does not exist', function () {
        $directive = '{{widget type="core/text" text="x"}}';

        expect(wysiwygRenderer()->render([$directive], $this->storeId)['html'][$directive])->toBe('');
    });

    it('gives back the admin store and design after the render', function () {
        $storeId = Mage::app()->getStore()->getId();
        $area = Mage::getDesign()->getArea();
        wysiwygRenderer()->render(['{{icon name="truck"}}'], $this->storeId);

        expect(Mage::app()->getStore()->getId())->toBe($storeId)
            ->and(Mage::getDesign()->getArea())->toBe($area);
    });
});

describe('endpoint', function () {
    it('renders only the directives that the role of the admin can see', function () {
        $user = wysiwygRenderAdmin(['admin/cms', 'admin/cms/block']);
        try {
            $block = '{{block id="' . $this->block->getIdentifier() . '"}}';
            $widget = '{{widget type="cms/widget_block" template="cms/widget/static_block/default.phtml" block_id="' . $this->block->getId() . '"}}';
            $icon = '{{icon name="truck"}}';
            $html = wysiwygRenderPost([$block, $widget, $icon, '{{config path="web/secure/base_url"}}'], $this->storeId)['html'];

            expect(array_keys($html))->toEqualCanonicalizing([$block, $icon]);
        } finally {
            wysiwygRenderDeleteAdmin($user);
        }
    });

    it('renders nothing for a store view that does not exist', function () {
        $user = wysiwygRenderAdmin(['admin/cms', 'admin/cms/block']);
        try {
            $result = wysiwygRenderPost(['{{icon name="truck"}}'], 999999);

            expect($result['html'])->toBe([])
                ->and($result['head'])->toBe('');
        } finally {
            wysiwygRenderDeleteAdmin($user);
        }
    });
});
