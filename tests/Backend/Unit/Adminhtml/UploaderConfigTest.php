<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Mage_Adminhtml_Block_Uploader_Abstract::getJsonConfig() hands the raw data of these three
 * config models to public/js/mage/adminhtml/uploader/instance.js. The script reads the key
 * names as they stand, so a setter that writes another name is lost there.
 */

dataset('uploader config keys', [
    'browse button directory' => ['adminhtml/uploader_config_browsebutton', 'setIsDirectory', true, 'is_directory'],
    'browse button single file' => ['adminhtml/uploader_config_browsebutton', 'setSingleFile', true, 'single_file'],
    'browse button dom nodes' => ['adminhtml/uploader_config_browsebutton', 'setDomNodes', ['a'], 'dom_nodes'],
    'misc max size' => ['adminhtml/uploader_config_misc', 'setMaxSizeInBytes', 100, 'max_size_in_bytes'],
    'misc max size text' => ['adminhtml/uploader_config_misc', 'setMaxSizePlural', '2M', 'max_size_plural'],
    'misc replace button' => ['adminhtml/uploader_config_misc', 'setReplaceBrowseWithRemove', true, 'replace_browse_with_remove'],
    'uploader file field' => ['adminhtml/uploader_config_uploader', 'setFileParameterName', 'image', 'file_parameter_name'],
    'uploader single file' => ['adminhtml/uploader_config_uploader', 'setSingleFile', true, 'single_file'],
]);

it('writes the key name that the browser reads', function (string $model, string $setter, mixed $value, string $key) {
    $config = Mage::getModel($model);
    $config->$setter($value);

    expect($config->getData())->toHaveKey($key)
        ->and($config->getData()[$key])->toBe($value);
})->with('uploader config keys');

it('gives a setter that no method declares the same key name', function () {
    $config = Mage::getModel('adminhtml/uploader_config_browsebutton')->setSomeOwnFlag(true);

    expect($config->getData())->toHaveKey('some_own_flag');
});

it('builds an accept attribute that names every allowed image type', function () {
    $accept = Mage::getModel('adminhtml/uploader_config_browsebutton')
        ->getMimeTypesByExtensions(\Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS);

    expect($accept)->toContain('image/webp')
        ->and($accept)->toContain('image/avif')
        ->and($accept)->toContain('image/jpeg')
        ->and($accept)->not->toContain('application/octet-stream');
});

it('reads the upload size limit of the server', function () {
    $helper = Mage::helper('adminhtml/uploader');

    expect($helper->getDataMaxSizeInBytes())->toBeInt()->toBeGreaterThan(0)
        ->and($helper->getDataMaxSize())->toBeString()->not->toBeEmpty();
});

it('gives the misc config the size limit of the server', function () {
    $config = Mage::getModel('adminhtml/uploader_config_misc');

    expect($config->getData()['max_size_in_bytes'])
        ->toBe(Mage::helper('adminhtml/uploader')->getDataMaxSizeInBytes());
});

it('builds the three config groups that the browser reads', function () {
    $block = Mage::app()->getLayout()->createBlock('adminhtml/uploader_multiple');
    $config = json_decode($block->getJsonConfig(), true, flags: JSON_THROW_ON_ERROR);

    expect($config)->toHaveKeys(['uploaderConfig', 'elementIds', 'browseConfig', 'miscConfig']);
});

it('marks the single file uploader as single in both config groups', function () {
    $block = Mage::app()->getLayout()->createBlock('adminhtml/uploader_single');

    expect($block->getUploaderConfig()->getData()['single_file'])->toBeTrue()
        ->and($block->getButtonConfig()->getData()['single_file'])->toBeTrue();
});

it('no longer ships the Mage_Uploader module', function () {
    expect(Mage::getConfig()->getModuleConfig('Mage_Uploader')->asArray())->toBeEmpty()
        ->and(is_dir(Mage::getBaseDir('code') . '/core/Mage/Uploader'))->toBeFalse();
});
