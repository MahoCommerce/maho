<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront shows the draft of the content editor in the theme of the store, for the
 * admin that frames it, and to nobody else: the page is not cached, indexed or tracked.
 */
function previewSaveDraft(array $draft): string
{
    /** @var Mage_Cms_Model_Preview $preview */
    $preview = Mage::getSingleton('cms/preview');
    return $preview->saveDraft([
        'entity' => 'page',
        'entity_id' => null,
        'store_id' => (int) Mage::app()->getStore()->getId(),
        'admin_user_id' => 1,
        'admin_origin' => 'https://admin.example.com',
        'context_page_id' => null,
        'data' => [],
        ...$draft,
    ]);
}

/**
 * @return array{response: Mage_Core_Controller_Response_Http, request: Mage_Core_Controller_Request_Http, controller: Mage_Cms_PreviewController}
 */
function previewDispatch(string $draftId): array
{
    Mage::unregister('_singleton/cms/page');
    Mage::unregister(Mage_Cms_Model_Preview::REGISTRY_KEY);
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/cms/preview/view/' . $draftId));
    $request->setRouteName('cms')
        ->setControllerName('preview')
        ->setActionName('view')
        ->setParam('draft', $draftId)
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    // The domain policy observer sets this before the action runs
    $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
    $controller = new Mage_Cms_PreviewController($request, $response);
    $controller->viewAction();
    return ['response' => $response, 'request' => $request, 'controller' => $controller];
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

afterEach(function () {
    Mage::unregister('_singleton/cms/page');
    Mage::unregister(Mage_Cms_Model_Preview::REGISTRY_KEY);
});

describe('page draft', function () {
    it('renders a page that is not saved yet, with its title, heading and content', function () {
        $result = previewDispatch(previewSaveDraft(['data' => [
            'title' => 'Draft page title',
            'content_heading' => 'Draft heading',
            'content' => '<p>Draft body</p>',
            'root_template' => 'one_column',
        ]]));
        $body = $result['response']->getBody();

        expect($result['response']->getHttpResponseCode())->toBe(200)
            ->and($body)->toContain('Draft page title')
            ->toContain('Draft heading')
            ->toContain(Mage_Cms_Model_Preview::MARKER_START . '<p>Draft body</p>' . Mage_Cms_Model_Preview::MARKER_END)
            ->toContain('mage/cms/preview-frame.js');
    });

    it('shows the draft in place of the saved content of a page', function () {
        $page = Mage::getModel('cms/page')
            ->setTitle('Saved title')
            ->setIdentifier('preview-saved-' . uniqid())
            ->setIsActive()
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p>Saved body</p>')
            ->save();

        try {
            $body = previewDispatch(previewSaveDraft([
                'entity_id' => (int) $page->getId(),
                'data' => ['title' => 'Saved title', 'content' => '<p>Changed body</p>', 'root_template' => 'one_column'],
            ]))['response']->getBody();

            expect($body)->toContain('<p>Changed body</p>')->not->toContain('<p>Saved body</p>');
        } finally {
            $page->delete();
        }
    });

    it('lets only the admin frame the page, and keeps it out of caches and indexes', function () {
        $headers = previewDispatch(previewSaveDraft(['data' => ['content' => '<p>x</p>', 'root_template' => 'one_column']]))
            ['response']->getSymfonyResponse()->headers;

        expect($headers->has('X-Frame-Options'))->toBeFalse()
            ->and($headers->get('Content-Security-Policy'))->toBe("frame-ancestors 'self' https://admin.example.com")
            ->and($headers->get('Cache-Control'))->toContain('no-store')
            ->and($headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');
    });

    it('does not count the preview as a visit', function () {
        $layout = previewDispatch(previewSaveDraft(['data' => ['content' => '<p>x</p>', 'root_template' => 'one_column']]))
            ['controller']->getLayout();

        foreach (['google_tag_manager', 'google_analytics', 'meta_pixel', 'speculation_rules_script'] as $name) {
            expect($layout->getBlock($name))->toBeFalse();
        }
    });

    it('shows nothing for a draft that does not exist or that belongs to another store', function (bool $otherStore) {
        $draftId = str_repeat('a', 32);
        if ($otherStore) {
            $draftId = previewSaveDraft(['store_id' => 999999, 'data' => ['content' => '<p>x</p>']]);
        }
        $result = previewDispatch($draftId);

        expect($result['request']->getActionName())->toBe('noRoute')
            ->and($result['response']->getBody())->not->toContain('preview-frame.js');
    })->with(['unknown draft' => [false], 'other store' => [true]]);
});

describe('block draft', function () {
    beforeEach(function () {
        $this->block = Mage::getModel('cms/block')
            ->setTitle('Preview block')
            ->setIdentifier('preview-block-' . uniqid())
            ->setIsActive()
            ->setStores([0])
            ->setContent('<p>Saved block</p>')
            ->save();
        $this->page = Mage::getModel('cms/page')
            ->setTitle('Shows the block')
            ->setIdentifier('preview-context-' . uniqid())
            ->setIsActive()
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<p>Around</p>{{block id="' . $this->block->getIdentifier() . '"}}')
            ->save();
    });

    afterEach(function () {
        $this->page->delete();
        $this->block->delete();
        Mage::app()->cleanCache([Mage_Cms_Model_Block::CACHE_TAG]);
    });

    it('shows the draft alone in the store layout', function () {
        $body = previewDispatch(previewSaveDraft([
            'entity' => 'block',
            'entity_id' => (int) $this->block->getId(),
            'data' => ['identifier' => $this->block->getIdentifier(), 'content' => '<p>Draft block</p>'],
        ]))['response']->getBody();

        expect($body)->toContain(Mage_Cms_Model_Preview::MARKER_START . '<p>Draft block</p>' . Mage_Cms_Model_Preview::MARKER_END);
    });

    it('puts the draft in place of the block on a page that shows it', function () {
        $body = previewDispatch(previewSaveDraft([
            'entity' => 'block',
            'entity_id' => (int) $this->block->getId(),
            'context_page_id' => (int) $this->page->getId(),
            'data' => ['identifier' => $this->block->getIdentifier(), 'content' => '<p>Draft block</p>'],
        ]))['response']->getBody();

        expect($body)->toContain('<p>Around</p>')
            ->toContain('<p>Draft block</p>')
            ->not->toContain('<p>Saved block</p>');
    });
});
