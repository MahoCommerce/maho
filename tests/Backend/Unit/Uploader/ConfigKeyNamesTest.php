<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Uploader
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_Uploader_Block_Abstract::getJsonConfig() hands the raw data of these three
 * config models to public/js/mage/adminhtml/uploader/instance.js. The script reads
 * the key names as they stand, so a setter that writes another name is lost there.
 */

dataset('uploader config keys', [
    'browse button directory' => ['uploader/config_browsebutton', 'setIsDirectory', true, 'is_directory'],
    'browse button single file' => ['uploader/config_browsebutton', 'setSingleFile', true, 'single_file'],
    'browse button dom nodes' => ['uploader/config_browsebutton', 'setDomNodes', ['a'], 'dom_nodes'],
    'misc max size' => ['uploader/config_misc', 'setMaxSizeInBytes', 100, 'max_size_in_bytes'],
    'misc max size text' => ['uploader/config_misc', 'setMaxSizePlural', '2M', 'max_size_plural'],
    'misc replace button' => ['uploader/config_misc', 'setReplaceBrowseWithRemove', true, 'replace_browse_with_remove'],
    'uploader file field' => ['uploader/config_uploader', 'setFileParameterName', 'image', 'file_parameter_name'],
    'uploader single file' => ['uploader/config_uploader', 'setSingleFile', true, 'single_file'],
]);

it('writes the key name that the browser reads', function (string $model, string $setter, mixed $value, string $key) {
    $config = Mage::getModel($model);
    $config->$setter($value);

    expect($config->getData())->toHaveKey($key)
        ->and($config->getData()[$key])->toBe($value);
})->with('uploader config keys');

it('gives a setter that no method declares the same key name', function () {
    $config = Mage::getModel('uploader/config_browsebutton')->setSomeOwnFlag(true);

    expect($config->getData())->toHaveKey('some_own_flag');
});
