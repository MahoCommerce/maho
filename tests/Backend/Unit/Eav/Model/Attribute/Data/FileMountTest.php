<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Eav_Model_Attribute_Data_File::compactValue()', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_eav_file_' . uniqid();
        mkdir($this->root . '/customer', 0777, true);
        mkdir($this->root . '/media', 0777, true);
        $this->customerMount = new Mount('customer', new LocalFilesystemAdapter($this->root . '/customer'), $this->root . '/customer');
        $this->mediaMount = new Mount('media', new LocalFilesystemAdapter($this->root . '/media'), $this->root . '/media');
        MountRegistry::register($this->customerMount);
        MountRegistry::register($this->mediaMount);
        $this->customerMount->write('a/b/doc.pdf', 'private');
        $this->mediaMount->write('customer/a/b/doc.pdf', 'public');

        $attribute = Mage::getModel('customer/attribute')->setData([
            'entity_type_id' => Mage::getSingleton('eav/config')->getEntityType('customer')->getId(),
            'attribute_code' => 'test_document',
            'frontend_input' => 'file',
            'is_required' => 0,
        ]);
        $customer = Mage::getModel('customer/customer')->setData('test_document', '/a/b/doc.pdf');
        $this->dataModel = Mage::getModel('eav/attribute_data_file')
            ->setAttribute($attribute)
            ->setEntity($customer);
    });

    afterEach(function (): void {
        MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('deletes the file of a customer from the customer mount and not from media', function (): void {
        $value = ['delete' => '1'];

        expect($this->dataModel->validateValue($value))->toBeTrue();
        $this->dataModel->compactValue($value);

        expect($this->customerMount->fileExists('a/b/doc.pdf'))->toBeFalse()
            ->and($this->mediaMount->fileExists('customer/a/b/doc.pdf'))->toBeTrue()
            ->and($this->dataModel->getEntity()->getData('test_document'))->toBe('');
    });
});
