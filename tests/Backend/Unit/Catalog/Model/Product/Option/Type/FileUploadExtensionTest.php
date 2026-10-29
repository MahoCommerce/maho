<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function fileOptionType(?string $allowedExtensions): Mage_Catalog_Model_Product_Option_Type_File
{
    $option = Mage::getModel('catalog/product_option')
        ->setId(7)
        ->setData('type', Mage_Catalog_Model_Product_Option::OPTION_TYPE_FILE)
        ->setData('title', 'Artwork')
        ->setData('is_require', 1)
        ->setData('file_extension', $allowedExtensions);

    $model = Mage::getModel('catalog/product_option_type_file');
    $model->setOption($option);
    $model->setRequest(new Maho\DataObject());

    return $model;
}

function callFileOptionMethod(Mage_Catalog_Model_Product_Option_Type_File $model, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($model, $method))->invoke($model, ...$args);
}

describe('Mage_Catalog_Model_Product_Option_Type_File::getUploadForbiddenExtensions()', function () {
    it('refuses protected, configured and active content extensions when the option lists none', function () {
        $forbidden = fileOptionType(null)->getUploadForbiddenExtensions(null);

        foreach (['php', 'php8', 'phar', 'phps', 'pht', 'phtml', 'shtml', 'htaccess', 'exe', 'htm', 'html', 'svg', 'svgz', 'xhtml', 'xht', 'xml', 'xsl', 'xslt'] as $ext) {
            expect($forbidden)->toContain($ext);
        }
        foreach (['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'zip'] as $ext) {
            expect($forbidden)->not->toContain($ext);
        }
        expect($forbidden)->toBe(array_values(array_unique($forbidden)));
    });

    it('keeps an active content type that the option lists, and still refuses scripts', function () {
        $forbidden = fileOptionType('svg, xml, png')->getUploadForbiddenExtensions(['svg', 'xml', 'png']);

        foreach (['svg', 'svgz', 'xhtml', 'xht', 'xml', 'xsl', 'xslt'] as $ext) {
            expect($forbidden)->not->toContain($ext);
        }
        foreach (['php', 'phar', 'phtml', 'exe'] as $ext) {
            expect($forbidden)->toContain($ext);
        }
    });

    it('keeps the protected minimum when a saved value leaves extensions out', function () {
        Mage::app()->getStore()->setConfig(Mage_Core_Helper_Data::XML_PATH_PROTECTED_FILE_EXTENSIONS, 'html');

        $forbidden = fileOptionType('phps')->getUploadForbiddenExtensions(['phps']);

        expect($forbidden)->toContain('phps')->toContain('pht')->toContain('shtml');
    });
});

describe('Mage_Catalog_Model_Product_Option_Type_File API upload', function () {
    it('refuses a dangerous extension on an option with no list of allowed extensions', function (string $name) {
        $model = fileOptionType(null);

        expect(fn() => callFileOptionMethod($model, '_validateApiFileData', [
            'name' => $name,
            'base64_encoded_data' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
        ]))->toThrow(Mage_Core_Exception::class, 'not allowed for security reasons');
    })->with(['artwork.svg', 'artwork.SVG', 'page.html', 'page.shtml', 'page.xhtml', 'shell.pht', 'shell.phps', 'shell.phar']);

    it('refuses HTML content in a file with a harmless extension on an option with no list', function () {
        $model = fileOptionType(null);

        expect(fn() => callFileOptionMethod($model, '_validateApiFileData', [
            'name' => 'notes.txt',
            'base64_encoded_data' => base64_encode("<!DOCTYPE html>\n<html><body><script>alert(1)</script></body></html>"),
        ]))->toThrow(Mage_Core_Exception::class, 'text/html');
    });

    it('refuses a protected extension even when the option lists it', function () {
        $model = fileOptionType('png, phtml');

        expect(fn() => callFileOptionMethod($model, '_validateApiFileData', [
            'name' => 'shell.phtml',
            'base64_encoded_data' => base64_encode('<?php echo 1;'),
        ]))->toThrow(Mage_Core_Exception::class, 'not allowed for security reasons');
    });
});

describe('Mage_Catalog_Model_Product_Option_Type_File form upload', function () {
    beforeEach(function () {
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'opt');
        file_put_contents($this->tmpFile, '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
    });

    afterEach(function () {
        unset($_FILES['options_7_file']);
        @unlink($this->tmpFile);
    });

    it('refuses an active content extension on an option with no list of allowed extensions', function (string $name) {
        $_FILES['options_7_file'] = [
            'name' => $name,
            'type' => 'image/svg+xml',
            'tmp_name' => $this->tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($this->tmpFile),
        ];
        $model = fileOptionType(null);

        expect(fn() => callFileOptionMethod($model, '_validateUploadedFile'))
            ->toThrow(Mage_Core_Exception::class, 'not allowed for security reasons');
    })->with(['artwork.svg', 'artwork.svgz', 'page.xhtml', 'page.xht', 'data.xml', 'style.xsl', 'style.xslt', 'shell.phps']);
});
