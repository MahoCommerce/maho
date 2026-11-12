<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The restore of a content version saves through the service of the record, as the admin form and the API do (#1348).
 */

class RestoreSpyBlockService extends Mage_Cms_Service_Block
{
    /** @var list<string> */
    public array $saved = [];

    #[\Override]
    public function save(Mage_Cms_Model_Block $block): Mage_Cms_Model_Block
    {
        $this->saved[] = (string) $block->getContent();
        return parent::save($block);
    }
}

beforeEach(function (): void {
    $this->block = Mage::getModel('cms/block')
        ->setTitle('Restore test')
        ->setIdentifier('restore_test_' . uniqid())
        ->setStores([0])
        ->setIsActive(true)
        ->setContent('<p>First</p>')
        ->save();
});

afterEach(function (): void {
    Mage::unregister('_service/cms/block');
    Mage::getResourceModel('contentversion/version_collection')
        ->addFieldToFilter('entity_type', 'cms_block')
        ->addFieldToFilter('entity_id', (int) $this->block->getId())
        ->walk('delete');
    $this->block->delete();
});

it('restores a block version through the block service', function (): void {
    $blockId = (int) $this->block->getId();
    Mage::getModel('cms/block')->load($blockId)->setContent('<p>Second</p>')->save();
    $version = Mage::getResourceModel('contentversion/version_collection')
        ->addFieldToFilter('entity_type', 'cms_block')
        ->addFieldToFilter('entity_id', $blockId)
        ->getFirstItem();
    expect($version->getId())->not->toBeNull();

    $spy = new RestoreSpyBlockService();
    Mage::unregister('_service/cms/block');
    Mage::register('_service/cms/block', $spy);

    $result = Mage::helper('contentversion')->restoreVersion((int) $version->getId());

    expect($result)->toBe(['entity_type' => 'cms_block', 'entity_id' => $blockId])
        ->and($spy->saved)->toBe(['<p>First</p>'])
        ->and(Mage::getModel('cms/block')->load($blockId)->getContent())->toBe('<p>First</p>');
});
