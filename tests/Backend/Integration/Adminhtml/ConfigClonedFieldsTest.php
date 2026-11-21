<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class ClonedConfigImagePrefixes extends Mage_Adminhtml_Model_System_Config_Clone_Media_Image
{
    /** @return list<array{field: string, label: string}> */
    #[\Override]
    public function getPrefixes(): array
    {
        return [
            ['field' => 'image_', 'label' => 'Image'],
            ['field' => 'image_hover_', 'label' => 'Hover image'],
        ];
    }
}

beforeEach(function (): void {
    Mage::getConfig()->setNode('global/models/adminhtml/rewrite/system_config_clone_media_image', ClonedConfigImagePrefixes::class);
});

it('accepts cloned placeholder fields with overlapping image attribute codes', function (): void {
    $groups = ['placeholder' => ['fields' => [
        'image_placeholder' => ['value' => 'image.png'],
        'image_hover_placeholder' => ['value' => 'hover.png'],
    ]]];
    $config = Mage::getModel('adminhtml/config_data')->setSection('catalog');
    $config->setGroupsSecure($groups);

    expect($config->getGroups())->toBe($groups);
});

it('rejects cloned placeholder fields without a matching definition', function (): void {
    $groups = ['placeholder' => ['fields' => ['image_hover_missing' => ['value' => 'example.png']]]];
    $config = Mage::getModel('adminhtml/config_data')->setSection('catalog');

    expect(fn() => $config->setGroupsSecure($groups))->toThrow(Mage_Core_Exception::class, 'Wrong field specified.');
});

it('saves the watermark value under its full cloned field name', function (): void {
    $groups = ['watermark' => ['fields' => ['image_hover_imageOpacity' => ['value' => '42']]]];
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    $connection->beginTransaction();

    try {
        Mage::getModel('adminhtml/config_data')->setSection('design')->setGroupsSecure($groups)->save();
        $value = $connection->fetchOne(
            $connection->select()->from($resource->getTableName('core/config_data'), ['value'])
                ->where('path = ?', 'design/watermark/image_hover_imageOpacity')
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0),
        );

        expect($value)->toBe('42');
    } finally {
        $connection->rollBack();
    }
});
