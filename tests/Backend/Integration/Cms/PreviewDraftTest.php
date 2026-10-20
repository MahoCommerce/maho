<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

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

    it('forgets a deleted draft', function () {
        $id = previewDraftModel()->saveDraft(previewDraftData());
        previewDraftModel()->deleteDraft($id);

        expect(previewDraftModel()->loadDraft($id))->toBeNull();
    });
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
        ], 1);

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
        ], 1);

        expect(array_keys($prepared['data']))->toEqualCanonicalizing(['title', 'content']);
    });

    it('leaves out a layout update that the save path refuses, and says why', function () {
        $prepared = previewDraftModel()->getProvider('page')->prepare([
            'content' => '<p>Body</p>',
            'layout_update_xml' => '<reference name="content"><action method="setText"><text helper="core/data/getConfig"/></action></reference>',
        ], 1);

        expect($prepared['data'])->not->toHaveKey('layout_update_xml')
            ->and($prepared['warnings'])->not->toBeEmpty();
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

    it('lists every active store view for content on all store views', function () {
        $stores = Mage::helper('cms/preview')->getStores($this->block->load($this->block->getId()));
        $active = array_filter(Mage::app()->getStores(), static fn(Mage_Core_Model_Store $store): bool => (bool) $store->getIsActive());

        expect(array_column($stores, 'id'))->toEqualCanonicalizing(array_map(intval(...), array_keys($active)));
    });
});
