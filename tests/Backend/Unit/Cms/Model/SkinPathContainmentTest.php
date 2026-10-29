<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('admin skin directive path', function () {
    beforeEach(function () {
        $this->filter = Mage::getModel('cms/adminhtml_template_filter');
        $this->outsideDir = Mage::getBaseDir('var') . '/skin_escape_' . uniqid();
        mkdir($this->outsideDir, 0o755, true);
        file_put_contents($this->outsideDir . '/secret.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->skinPackage = 'skintest_' . uniqid();
        $this->skinPackageDir = Mage::getBaseDir('skin') . '/frontend/' . $this->skinPackage;
    });

    afterEach(function () {
        @unlink($this->skinPackageDir . '/default/link.svg');
        @rmdir($this->skinPackageDir . '/default');
        @rmdir($this->skinPackageDir);
        @unlink($this->outsideDir . '/secret.svg');
        @rmdir($this->outsideDir);
    });

    it('resolves a skin file of the admin theme', function () {
        $path = $this->filter->skinDirective(['', 'skin', ' url="images/logo.svg" _area="adminhtml" _package="default" _theme="default"']);
        expect($path)->toEndWith('/skin/adminhtml/default/default/images/logo.svg')
            ->and(is_file($path))->toBeTrue();
    });

    it('refuses an area, a package or a theme that is not one directory name', function (array $directive) {
        expect(fn() => $this->filter->skinDirective($directive))->toThrow(Mage_Core_Exception::class);
    })->with([
        'area traversal' => [['', 'skin', ' url="secret.svg" _area="../../var" _package="." _theme="."']],
        'package traversal' => [['', 'skin', ' url="secret.svg" _area="frontend" _package="../../../var" _theme="."']],
        'theme traversal' => [['', 'skin', ' url="secret.svg" _area="frontend" _package="base" _theme="../../../../var"']],
        'dot package' => [['', 'skin', ' url="images/logo.svg" _area="adminhtml" _package="." _theme="default"']],
        'slash in theme' => [['', 'skin', ' url="images/logo.svg" _area="adminhtml" _package="default" _theme="default/images"']],
    ]);

    it('refuses a file outside the skin directory that an area traversal names', function () {
        $directive = ['', 'skin', ' url="secret.svg" _area="../../var" _package="' . basename($this->outsideDir) . '" _theme="."'];
        expect(fn() => $this->filter->skinDirective($directive))->toThrow(Mage_Core_Exception::class);
    });

    it('refuses a url with a parent directory and a missing file', function (string $url) {
        expect(fn() => $this->filter->skinDirective(['', 'skin', ' url="' . $url . '" _area="adminhtml" _package="default" _theme="default"']))
            ->toThrow(Mage_Core_Exception::class);
    })->with(['../../../../app/etc/config.xml', 'images/missing.svg']);

    it('refuses a skin symlink that leads outside the skin directory', function () {
        mkdir($this->skinPackageDir . '/default', 0o755, true);
        if (!@symlink($this->outsideDir . '/secret.svg', $this->skinPackageDir . '/default/link.svg')) {
            $this->markTestSkipped('Symlinks are not supported here.');
        }

        expect(fn() => $this->filter->skinDirective(['', 'skin', ' url="link.svg" _area="frontend" _package="' . $this->skinPackage . '" _theme="default"']))
            ->toThrow(Mage_Core_Exception::class);
    });
});
