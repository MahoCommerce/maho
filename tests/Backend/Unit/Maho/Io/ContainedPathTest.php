<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Io::containedPath()', function () {
    beforeEach(function () {
        $this->base = sys_get_temp_dir() . '/maho_contained_' . uniqid();
        mkdir($this->base . '/sub', 0755, true);
        file_put_contents($this->base . '/sub/file.txt', 'x');
        $this->outside = sys_get_temp_dir() . '/maho_outside_' . uniqid();
        mkdir($this->outside, 0755, true);
        file_put_contents($this->outside . '/secret.txt', 'y');
    });

    afterEach(function () {
        if (is_link($this->base . '/link')) {
            unlink($this->base . '/link');
        }
        unlink($this->base . '/sub/file.txt');
        rmdir($this->base . '/sub');
        rmdir($this->base);
        unlink($this->outside . '/secret.txt');
        rmdir($this->outside);
    });

    it('resolves a relative path inside the base directory', function () {
        expect(\Maho\Io::containedPath($this->base, 'sub/file.txt'))
            ->toBe($this->base . '/sub/file.txt');
    });

    it('accepts an absolute path inside the base directory', function () {
        expect(\Maho\Io::containedPath($this->base, $this->base . '/sub/file.txt'))
            ->toBe($this->base . '/sub/file.txt');
    });

    it('accepts a path that does not exist yet', function () {
        expect(\Maho\Io::containedPath($this->base, 'new/dir/file.txt'))
            ->toBe($this->base . '/new/dir/file.txt');
    });

    it('collapses dot segments that stay inside the base directory', function () {
        expect(\Maho\Io::containedPath($this->base, 'sub/../sub/./file.txt'))
            ->toBe($this->base . '/sub/file.txt');
    });

    it('rejects ../ traversal', function () {
        expect(\Maho\Io::containedPath($this->base, '../' . basename($this->outside) . '/secret.txt'))->toBeFalse();
        expect(\Maho\Io::containedPath($this->base, 'sub/../../etc/passwd'))->toBeFalse();
    });

    it('rejects backslash traversal', function () {
        expect(\Maho\Io::containedPath($this->base, '..\\..\\etc\\passwd'))->toBeFalse();
    });

    it('rejects an absolute path outside the base directory', function () {
        expect(\Maho\Io::containedPath($this->base, '/etc/passwd'))->toBeFalse();
        expect(\Maho\Io::containedPath($this->base, $this->outside . '/secret.txt'))->toBeFalse();
    });

    it('rejects a sibling directory sharing the base prefix', function () {
        expect(\Maho\Io::containedPath($this->base, $this->base . '_other/file.txt'))->toBeFalse();
    });

    it('rejects stream wrappers and null bytes', function () {
        expect(\Maho\Io::containedPath($this->base, 'phar://evil.phar/x'))->toBeFalse();
        expect(\Maho\Io::containedPath('phar://evil.phar', 'x'))->toBeFalse();
        expect(\Maho\Io::containedPath($this->base, "sub/file.txt\0.jpg"))->toBeFalse();
    });

    it('rejects an empty path', function () {
        expect(\Maho\Io::containedPath($this->base, ''))->toBeFalse();
    });

    it('does not expand a home directory shortcut', function () {
        expect(\Maho\Io::containedPath($this->base, '~/file.txt'))
            ->toBe($this->base . '/~/file.txt');
    });

    it('rejects a symlink that points outside the base directory', function () {
        symlink($this->outside, $this->base . '/link');
        expect(\Maho\Io::containedPath($this->base, 'link/secret.txt'))->toBeFalse();
    });

    it('accepts an absolute path spelled through a symlink to the base directory', function () {
        symlink($this->base, $this->outside . '/alias');
        $viaAlias = $this->outside . '/alias/sub/file.txt';
        expect(\Maho\Io::containedPath(realpath($this->base), $viaAlias))->toBe($viaAlias);
        unlink($this->outside . '/alias');
    });

    it('accepts a symlink that stays inside the base directory', function () {
        symlink($this->base . '/sub', $this->base . '/link');
        expect(\Maho\Io::containedPath($this->base, 'link/file.txt'))
            ->toBe($this->base . '/link/file.txt');
    });
});
