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
function previewController(string $draftId): array
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
    // preDispatch() loads these on a real request, and the marker observer is a frontend one
    Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_FRONTEND, Mage_Core_Model_App_Area::PART_EVENTS);
    $response = new Mage_Core_Controller_Response_Http();
    // The domain policy observer sets this before the action runs
    $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
    return ['response' => $response, 'request' => $request, 'controller' => new Mage_Cms_PreviewController($request, $response)];
}

/**
 * @return array{response: Mage_Core_Controller_Response_Http, request: Mage_Core_Controller_Request_Http, controller: Mage_Cms_PreviewController}
 */
function previewDispatch(string $draftId): array
{
    $result = previewController($draftId);
    $result['controller']->viewAction();
    return $result;
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

afterEach(function () {
    Mage::unregister('_singleton/cms/page');
    Mage::unregister(Mage_Cms_Model_Preview::REGISTRY_KEY);
    Mage::unregister('current_blog_post');
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

    it('takes the body class and the layout handle of the storefront page, for the home page too', function (bool $home) {
        $identifier = $home
            ? Mage::helper('cms/page')->getConfigPageIdentifier(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE)
            : 'preview-page-' . uniqid();
        $action = $home ? 'cms_index_index' : 'cms_page_view';
        $result = previewDispatch(previewSaveDraft(['data' => ['identifier' => $identifier, 'content' => '<p>x</p>', 'root_template' => 'one_column']]));

        expect($result['response']->getBody())->toMatch('/<body class="[^"]*\\b' . str_replace('_', '-', $action) . '\\b/')
            ->and($result['controller']->getLayout()->getUpdate()->getHandles())->toContain($action)->toContain('cms_preview_view');
    })->with(['page' => [false], 'home page' => [true]]);

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

    it('starts no session, so a frame of another site leaves no session behind', function () {
        $controller = previewController(previewSaveDraft(['data' => ['content' => '<p>x</p>']]))['controller'];
        $controller->preDispatch();

        expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_START_SESSION))->toBeTrue();
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

    it('finds the block on a page by its saved identifier when the draft renames it', function () {
        $body = previewDispatch(previewSaveDraft([
            'entity' => 'block',
            'entity_id' => (int) $this->block->getId(),
            'context_page_id' => (int) $this->page->getId(),
            'data' => ['identifier' => 'renamed-' . uniqid(), 'content' => '<p>Draft block</p>'],
        ]))['response']->getBody();

        expect($body)->toContain('<p>Draft block</p>')
            ->not->toContain('<p>Saved block</p>');
    });

    it('keeps the wrapper of a static block widget that names the block by id', function () {
        $page = Mage::getModel('cms/page')
            ->setTitle('Shows the widget')
            ->setIdentifier('preview-widget-' . uniqid())
            ->setIsActive()
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('{{widget type="cms/widget_block" template="cms/widget/static_block/default.phtml" block_id="' . $this->block->getId() . '"}}')
            ->save();
        try {
            $body = previewDispatch(previewSaveDraft([
                'entity' => 'block',
                'entity_id' => (int) $this->block->getId(),
                'context_page_id' => (int) $page->getId(),
                'data' => ['identifier' => $this->block->getIdentifier(), 'content' => '<p>Draft block</p>'],
            ]))['response']->getBody();

            expect($body)->toContain(Mage_Cms_Model_Preview::MARKER_START . '<div class="widget widget-static-block"><p>Draft block</p></div>')
                ->not->toContain('<p>Saved block</p>');
        } finally {
            $page->delete();
        }
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

    it('shows a disabled block on a disabled page', function () {
        $this->block->setIsActive(false)->save();
        $this->page->setIsActive(false)->save();

        $body = previewDispatch(previewSaveDraft([
            'entity' => 'block',
            'entity_id' => (int) $this->block->getId(),
            'context_page_id' => (int) $this->page->getId(),
            'data' => ['identifier' => $this->block->getIdentifier(), 'content' => '<p>Draft block</p>'],
        ]))['response']->getBody();

        expect($body)->toContain('<p>Around</p>')
            ->toContain(Mage_Cms_Model_Preview::MARKER_START . '<p>Draft block</p>' . Mage_Cms_Model_Preview::MARKER_END);
    });

    it('shows nothing for a page of another store view', function () {
        $default = Mage::app()->getDefaultStoreView();
        $other = Mage::getModel('core/store')
            ->setCode('preview_' . substr(uniqid(), -8))
            ->setWebsiteId((int) $default->getWebsiteId())
            ->setGroupId((int) $default->getGroupId())
            ->setName('Preview other store')
            ->setIsActive()
            ->save();
        Mage::app()->reinitStores();
        Mage::app()->setCurrentStore($default);
        $this->page->setStores([(int) $other->getId()])->save();

        try {
            $result = previewDispatch(previewSaveDraft([
                'entity' => 'block',
                'entity_id' => (int) $this->block->getId(),
                'context_page_id' => (int) $this->page->getId(),
                'data' => ['identifier' => $this->block->getIdentifier(), 'content' => '<p>Draft block</p>'],
            ]));

            expect($result['request']->getActionName())->toBe('noRoute');
        } finally {
            $this->page->setStores([0])->save();
            Mage::register('isSecureArea', true, true);
            $other->delete();
            Mage::unregister('isSecureArea');
            Mage::app()->reinitStores();
        }
    });
});

describe('blog post draft', function () {
    it('renders a post that is not saved yet, with its title, body and robots', function () {
        $result = previewDispatch(previewSaveDraft([
            'entity' => 'blog_post',
            'data' => ['title' => 'Draft post title', 'content' => '<p>Draft post body</p>', 'meta_robots' => 'NOINDEX,NOFOLLOW'],
        ]));
        $body = $result['response']->getBody();

        expect($result['response']->getHttpResponseCode())->toBe(200)
            ->and($body)->toContain('Draft post title')
            ->toContain('<p>Draft post body</p>')
            ->toContain(Mage_Cms_Model_Preview::MARKER_START)
            ->toMatch('/<body class="[^"]*\\bblog-index-view\\b/')
            ->and($result['controller']->getLayout()->getBlock('head')->getRobots())->toBe('NOINDEX,NOFOLLOW');
    });
});
