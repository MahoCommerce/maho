<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

function cmsEditRevisionHistory(int $pageId): Maho_ContentVersion_Model_Resource_Version_Collection
{
    return Mage::getResourceModel('contentversion/version_collection')
        ->addFieldToFilter('entity_type', 'cms_page')
        ->addFieldToFilter('entity_id', $pageId);
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    $this->cms = Mage::getService('cms/page');
    $this->page = $this->cms->save($this->cms->newPage()
        ->setIdentifier('cms-edit-revision-' . bin2hex(random_bytes(5)))
        ->setTitle('Sample page')
        ->setIsActive(false)
        ->setContent('<p>Original</p>'));
    $this->pageId = (int) $this->page->getId();
    $this->originalRevision = (int) $this->cms->getById($this->pageId)->getData(Mage_Cms_Model_Page::EDIT_REVISION);
    $this->form = [
        'page_id' => $this->pageId,
        'identifier' => $this->page->getIdentifier(),
        'title' => $this->page->getTitle(),
        'stores' => [0],
        'root_template' => $this->page->getRootTemplate(),
        'is_active' => 0,
        Mage_Cms_Model_Page::EDIT_REVISION => (string) $this->originalRevision,
    ];
});

afterEach(function (): void {
    cmsEditRevisionHistory($this->pageId)->walk('delete');
    $this->cms->delete($this->cms->getById($this->pageId));
});

it('refuses an older page form and keeps the draft with the revision it opened', function (): void {
    $first = $this->form + ['content' => '<p>First edit</p>'];
    $second = $this->form + ['content' => '<p>Second edit</p>'];
    $message = 'This page changed after you opened it. Copy your edits and reopen the page before saving.';

    expect(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $first)['errors'])->toBe([]);
    expect(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $second)['errors'])->toBe([$message]);

    $draft = Mage::getSingleton('adminhtml/session')->getFormData(true);
    expect($draft)->toMatchArray(['content' => $second['content'], Mage_Cms_Model_Page::EDIT_REVISION => (string) $this->originalRevision])
        ->and(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $draft)['errors'])->toBe([$message])
        ->and($this->cms->getById($this->pageId)->getContent())->toBe($first['content'])
        ->and((int) $this->cms->getById($this->pageId)->getData(Mage_Cms_Model_Page::EDIT_REVISION))->toBe($this->originalRevision + 1)
        ->and(cmsEditRevisionHistory($this->pageId)->getSize())->toBe(1);
});

it('advances the revision for regular saves and history restores', function (): void {
    $this->cms->getById($this->pageId)
        ->setContent('<p>Edited by a normal model save</p>')
        ->setData(Mage_Cms_Model_Page::EDIT_REVISION, 42)
        ->save();
    $version = cmsEditRevisionHistory($this->pageId)->getFirstItem();
    expect($version->getId())->not->toBeNull()
        ->and($version->getContentDataDecoded())->not->toHaveKey(Mage_Cms_Model_Page::EDIT_REVISION);

    $legacyForm = $this->form + ['content' => '<p>Edited by a legacy form</p>'];
    unset($legacyForm[Mage_Cms_Model_Page::EDIT_REVISION]);
    expect(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $legacyForm)['errors'])->toBe([]);
    expect($this->cms->getById($this->pageId)->getContent())->toBe($legacyForm['content'])
        ->and((int) $this->cms->getById($this->pageId)->getData(Mage_Cms_Model_Page::EDIT_REVISION))->toBe($this->originalRevision + 2);

    Mage::helper('contentversion')->restoreVersion((int) $version->getId());
    $page = $this->cms->getById($this->pageId);
    expect($page->getContent())->toBe('<p>Original</p>')
        ->and((int) $page->getData(Mage_Cms_Model_Page::EDIT_REVISION))->toBe($this->originalRevision + 3);
});

it('rolls back the revision and history if a guarded save fails resource validation', function (): void {
    $duplicate = $this->cms->save($this->cms->newPage()
        ->setIdentifier($this->page->getIdentifier() . '-other')
        ->setTitle('Existing page'));
    try {
        $form = array_replace($this->form, ['identifier' => $duplicate->getIdentifier(), 'content' => '<p>Change</p>']);
        expect(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $form)['errors'])
            ->toBe(['A page URL key for specified store already exists.']);
        $page = $this->cms->getById($this->pageId);
        expect($page->getContent())->toBe('<p>Original</p>')
            ->and((int) $page->getData(Mage_Cms_Model_Page::EDIT_REVISION))->toBe($this->originalRevision)
            ->and(cmsEditRevisionHistory($this->pageId)->getSize())->toBe(0);

        expect(AdminApiParity::admin(Mage_Adminhtml_Cms_PageController::class, 'save', $this->form + ['content' => '<p>Retried</p>'])['errors'])->toBe([])
            ->and($this->cms->getById($this->pageId)->getContent())->toBe('<p>Retried</p>');
    } finally {
        $this->cms->delete($duplicate);
    }
});

it('can retry a rolled back save on the same page and save it again without a revision check', function (): void {
    $page = $this->cms->getById($this->pageId);
    $duplicate = $this->cms->save($this->cms->newPage()
        ->setIdentifier($page->getIdentifier() . '-other')
        ->setTitle('Existing page'));
    try {
        $identifier = $page->getIdentifier();
        $page->setIdentifier($duplicate->getIdentifier())->setContent('<p>Retried on the same page</p>');
        expect(fn() => $this->cms->saveWithEditRevision($page, $this->originalRevision))->toThrow(Mage_Core_Exception_Conflict::class);

        $page->setIdentifier($identifier);
        $this->cms->saveWithEditRevision($page, $this->originalRevision);
        $this->cms->save($page->setContent('<p>Saved again</p>'));

        $page->setContent('<p>Stale edit</p>');
        expect(fn() => $this->cms->saveWithEditRevision($page, $this->originalRevision))->toThrow(Mage_Core_Exception_Conflict::class);
        $this->cms->save($page->setContent('<p>Saved after a conflict</p>'));

        $stored = $this->cms->getById($this->pageId);
        expect($stored->getContent())->toBe('<p>Saved after a conflict</p>')
            ->and((int) $stored->getData(Mage_Cms_Model_Page::EDIT_REVISION))->toBe($this->originalRevision + 3);
    } finally {
        $this->cms->delete($duplicate);
    }
});
