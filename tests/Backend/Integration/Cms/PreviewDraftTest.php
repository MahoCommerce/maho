<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * The live preview of the content editor keeps a draft per admin user, and shows what a
 * save would store: the providers sanitize as the save path does.
 */
function previewDraftModel(): Mage_Cms_Model_Preview
{
    /** @var Mage_Cms_Model_Preview $preview */
    $preview = Mage::getSingleton('cms/preview');
    return $preview;
}

function previewDraftData(int $adminUserId = 1, array $data = ['content' => '<p>Draft</p>']): array
{
    return [
        'entity' => 'page',
        'entity_id' => null,
        'store_id' => (int) Mage::app()->getDefaultStoreView()->getId(),
        'admin_user_id' => $adminUserId,
        'admin_origin' => 'https://admin.example.com',
        'context_page_id' => null,
        'data' => $data,
    ];
}

/**
 * @param array<string, mixed> $post
 */
function previewDraftController(array $post): Mage_Adminhtml_Cms_PreviewController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/admin/cms_preview/draft', 'POST', $post));
    $request->setActionName('draft');
    return new Mage_Adminhtml_Cms_PreviewController($request, new Mage_Core_Controller_Response_Http());
}

/**
 * @param array<string, mixed> $post
 * @return array{status: int, body: array<string, mixed>}
 */
function previewDraftPost(array $post): array
{
    $controller = previewDraftController($post);
    $controller->draftAction();
    $response = $controller->getResponse();
    return ['status' => $response->getHttpResponseCode(), 'body' => Mage::helper('core')->jsonDecode($response->getBody())];
}

/** A second active store view in the website of the default store view. */
function previewDraftSecondStore(): Mage_Core_Model_Store
{
    $default = Mage::app()->getDefaultStoreView();
    $store = Mage::getModel('core/store')
        ->setCode('preview_' . substr(uniqid(), -8))
        ->setWebsiteId((int) $default->getWebsiteId())
        ->setGroupId((int) $default->getGroupId())
        ->setName('Preview second store')
        ->setIsActive()
        ->setSortOrder(99)
        ->save();
    Mage::app()->reinitStores();
    return Mage::app()->getStore((int) $store->getId());
}

describe('draft store', function () {
    it('gives a draft back by its random id', function () {
        $id = previewDraftModel()->saveDraft(previewDraftData());

        expect($id)->toMatch('/^[0-9a-f]{32}$/')
            ->and(previewDraftModel()->loadDraft($id))->toBe(previewDraftData());
    });

    it('keeps the id when the same admin user saves again', function () {
        $id = previewDraftModel()->saveDraft(previewDraftData());
        $again = previewDraftModel()->saveDraft(previewDraftData(data: ['content' => '<p>Second</p>']), $id);

        expect($again)->toBe($id)
            ->and(previewDraftModel()->loadDraft($id)['data']['content'])->toBe('<p>Second</p>');
    });

    it('never lets an admin user overwrite the draft of another user', function () {
        $id = previewDraftModel()->saveDraft(previewDraftData(1));
        $other = previewDraftModel()->saveDraft(previewDraftData(2, ['content' => '<p>Other</p>']), $id);

        expect($other)->not->toBe($id)
            ->and(previewDraftModel()->loadDraft($id)['data']['content'])->toBe('<p>Draft</p>');
    });

    it('refuses an id that it did not make', function (string $id) {
        expect(previewDraftModel()->loadDraft($id))->toBeNull();
    })->with(['too short' => ['abc'], 'not hex' => [str_repeat('z', 32)], 'unknown' => [str_repeat('a', 32)]]);

});

describe('providers', function () {
    it('declares a provider and an ACL resource for each editor', function (string $entity, string $class, string $acl) {
        expect(previewDraftModel()->getProvider($entity))->toBeInstanceOf($class)
            ->and(previewDraftModel()->getAclResource($entity))->toBe($acl);
    })->with([
        'page' => ['page', Mage_Cms_Model_Preview_Provider_Page::class, 'cms/page/save'],
        'block' => ['block', Mage_Cms_Model_Preview_Provider_Block::class, 'cms/block'],
        'blog post' => ['blog_post', Maho_Blog_Model_Preview_Provider::class, 'cms/blog/posts'],
    ]);

    it('has no provider for unknown content', function () {
        expect(previewDraftModel()->getProvider('product'))->toBeNull()
            ->and(previewDraftModel()->getProvider('../page'))->toBeNull()
            ->and(previewDraftModel()->getAclResource('product'))->toBeNull();
    });

    it('sanitizes the content as a save does and keeps its directives', function (string $entity) {
        $prepared = previewDraftModel()->getProvider($entity)->prepare([
            'content' => '<p onclick="alert(1)">Hello</p><script>alert(2)</script><a href="{{store url="sale"}}">Sale</a>',
        ]);

        expect($prepared['data']['content'])->toContain('Hello')
            ->not->toContain('onclick')
            ->not->toContain('<script')
            ->toContain('{{store url="sale"}}');
    })->with(['page', 'block', 'blog_post']);

    it('keeps only the fields of the form that change the storefront', function () {
        $prepared = previewDraftModel()->getProvider('page')->prepare([
            'title' => 'Draft title',
            'content' => '<p>Body</p>',
            'is_active' => '0',
            'stores' => ['1'],
            'form_key' => 'abc',
        ]);

        expect(array_keys($prepared['data']))->toEqualCanonicalizing(['title', 'content']);
    });

    it('leaves out a layout update that the save path refuses, and says why', function () {
        $prepared = previewDraftModel()->getProvider('page')->prepare([
            'content' => '<p>Body</p>',
            'layout_update_xml' => '<reference name="content"><action method="setText"><text helper="core/data/getConfig"/></action></reference>',
        ]);

        expect($prepared['data'])->not->toHaveKey('layout_update_xml')
            ->and($prepared['warnings'])->not->toBeEmpty();
    });
});

describe('draft endpoint', function () {
    beforeEach(function () {
        $this->user = AdminRoleFixture::login(['admin/cms', 'admin/cms/page', 'admin/cms/page/save']);
    });

    afterEach(function () {
        AdminRoleFixture::delete($this->user);
    });

    it('refuses content that has no preview', function () {
        $result = previewDraftPost(['preview_entity' => 'product', 'preview_store' => (string) Mage::app()->getDefaultStoreView()->getId()]);

        expect($result['status'])->toBe(422)
            ->and($result['body']['error'])->toBeTrue();
    });

    it('stores the draft of a saved page for the admin user, with a URL that sets no store cookie', function () {
        $page = Mage::getModel('cms/page')
            ->setTitle('Draft endpoint')
            ->setIdentifier('preview-endpoint-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('<p>Saved</p>')
            ->save();
        try {
            $result = previewDraftPost([
                'preview_entity' => 'page',
                'preview_store' => (string) Mage::app()->getDefaultStoreView()->getId(),
                'page_id' => (string) $page->getId(),
                'content' => '<p>Changed</p>',
            ]);

            expect($result['status'])->toBe(200)
                ->and($result['body']['url'])->not->toContain('___store')
                ->and(previewDraftModel()->loadDraft($result['body']['draft']))->toMatchArray([
                    'entity_id' => (int) $page->getId(),
                    'admin_user_id' => (int) $this->user->getId(),
                ]);
        } finally {
            $page->delete();
        }
    });
});

describe('access', function () {
    it('takes the ACL resource of the draft endpoint from the content', function (string $entity, bool $allowed) {
        $user = AdminRoleFixture::login(['admin/cms', 'admin/cms/block']);
        try {
            $controller = previewDraftController(['preview_entity' => $entity]);

            expect((fn(): bool => $this->_isAllowed())->call($controller))->toBe($allowed);
        } finally {
            AdminRoleFixture::delete($user);
        }
    })->with(['page' => ['page', false], 'block' => ['block', true], 'unknown' => ['product', false]]);

    it('turns the Preview pane off for content that the role cannot preview', function () {
        $user = AdminRoleFixture::login(['admin/cms', 'admin/cms/block']);
        $block = Mage::getModel('cms/block')
            ->setTitle('Preview access')
            ->setIdentifier('preview-access-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('<p>Block</p>')
            ->save();
        try {
            expect(Mage::helper('cms/preview')->getEditorConfig('page', Mage::getModel('cms/page')))->toBe([])
                ->and(Mage::helper('cms/preview')->getEditorConfig('block', $block->load($block->getId())))->toHaveKey('preview');
        } finally {
            $block->delete();
            AdminRoleFixture::delete($user);
        }
    });
});

describe('store view of the editor', function () {
    beforeEach(function () {
        $this->second = previewDraftSecondStore();
    });

    afterEach(function () {
        Mage::unregister('blog_post');
        Mage::unregister('cms_page');
        $this->second->delete();
        Mage::app()->reinitStores();
    });

    it('uses the store view of a blog post', function () {
        Mage::register('blog_post', Mage::getModel('blog/post')->setData('stores', [(int) $this->second->getId()]));

        expect(Mage::getSingleton('cms/wysiwyg_config')->getPreviewStoreId())->toBe((int) $this->second->getId());
    });

    it('uses the default store view when the content shows in it', function () {
        $defaultId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::register('cms_page', Mage::getModel('cms/page')->setData('store_id', [(int) $this->second->getId(), $defaultId]));

        expect(Mage::getSingleton('cms/wysiwyg_config')->getPreviewStoreId())->toBe($defaultId);
    });
});

describe('editor settings', function () {
    beforeEach(function () {
        $this->block = Mage::getModel('cms/block')
            ->setTitle('Preview usage')
            ->setIdentifier('preview-usage-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('<p>Block</p>')
            ->save();
        $this->byIdentifier = Mage::getModel('cms/page')
            ->setTitle('By identifier')
            ->setIdentifier('preview-by-identifier-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('{{block id="' . $this->block->getIdentifier() . '"}}')
            ->save();
        $this->byId = Mage::getModel('cms/page')
            ->setTitle('By id')
            ->setIdentifier('preview-by-id-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('{{widget type="cms/widget_block" block_id="' . $this->block->getId() . '"}}')
            ->save();
    });

    afterEach(function () {
        $this->byIdentifier->delete();
        $this->byId->delete();
        $this->block->delete();
    });

    it('lists the pages that show a block, by id and by identifier', function () {
        $ids = array_column(Mage::helper('cms/preview')->getBlockUsages($this->block), 'id');

        expect($ids)->toContain((int) $this->byIdentifier->getId())
            ->toContain((int) $this->byId->getId());
    });

    it('lists a page only for a {{block}} or a {{widget}} directive with the exact name', function () {
        $suffix = uniqid();
        $block = Mage::getModel('cms/block')
            ->setTitle('Preview exact')
            ->setIdentifier('preview_exact_' . $suffix)
            ->setIsActive()
            ->setStores([0])
            ->setContent('<p>Block</p>')
            ->save();
        $pages = [];
        foreach (['{{block id="preview_exact_' . $suffix . '"}}', '{{block id="preview-exact-' . $suffix . '"}}', '<div id="preview_exact_' . $suffix . '">x</div>'] as $content) {
            $pages[] = Mage::getModel('cms/page')
                ->setTitle('Exact usage')
                ->setIdentifier('preview-exact-page-' . uniqid())
                ->setIsActive()
                ->setStores([0])
                ->setContent($content)
                ->save();
        }
        try {
            $ids = array_column(Mage::helper('cms/preview')->getBlockUsages($block), 'id');

            expect($ids)->toContain((int) $pages[0]->getId())
                ->not->toContain((int) $pages[1]->getId())
                ->not->toContain((int) $pages[2]->getId());
        } finally {
            foreach ($pages as $page) {
                $page->delete();
            }
            $block->delete();
        }
    });

    it('lists every active store view for content on all store views', function () {
        $stores = Mage::helper('cms/preview')->getStores($this->block->load($this->block->getId()));
        $active = array_filter(Mage::app()->getStores(), static fn(Mage_Core_Model_Store $store): bool => (bool) $store->getIsActive());

        expect(array_column($stores, 'id'))->toEqualCanonicalizing(array_map(intval(...), array_keys($active)));
    });
});
