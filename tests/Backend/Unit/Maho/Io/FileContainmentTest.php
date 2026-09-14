<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

describe('\Maho\Io\File working directory containment', function () {
    beforeEach(function () {
        $this->cwd = sys_get_temp_dir() . '/maho_io_cwd_' . uniqid();
        $this->outside = sys_get_temp_dir() . '/maho_io_out_' . uniqid();
        mkdir($this->cwd, 0755, true);
        mkdir($this->outside, 0755, true);
        $this->io = new \Maho\Io\File();
        $this->io->open(['path' => $this->cwd]);
    });

    afterEach(function () {
        foreach ([$this->cwd, $this->outside] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    });

    it('writes a relative file name inside the working directory', function () {
        expect($this->io->write('inside.txt', 'data'))->not->toBeFalse();
        expect(file_get_contents($this->cwd . '/inside.txt'))->toBe('data');
    });

    it('refuses to write through an absolute path outside the working directory', function () {
        expect(fn() => $this->io->write($this->outside . '/escaped.txt', 'data'))
            ->toThrow(\Exception::class);
        expect(file_exists($this->outside . '/escaped.txt'))->toBeFalse();
    });

    it('refuses to write through a traversal path', function () {
        $target = '../' . basename($this->outside) . '/escaped.txt';
        expect(fn() => $this->io->write($target, 'data'))->toThrow(\Exception::class);
        expect(file_exists($this->outside . '/escaped.txt'))->toBeFalse();
    });

    it('refuses to copy or move to a destination outside the working directory', function () {
        file_put_contents($this->cwd . '/src.txt', 'data');
        expect(fn() => $this->io->cp('src.txt', $this->outside . '/copied.txt'))->toThrow(\Exception::class);
        expect(fn() => $this->io->mv('src.txt', $this->outside . '/moved.txt'))->toThrow(\Exception::class);
        expect(file_exists($this->outside . '/copied.txt'))->toBeFalse();
        expect(file_exists($this->cwd . '/src.txt'))->toBeTrue();
    });

    it('still copies from a source outside the working directory', function () {
        file_put_contents($this->outside . '/src.txt', 'data');
        expect($this->io->cp($this->outside . '/src.txt', 'copied.txt'))->toBeTrue();
        expect(file_get_contents($this->cwd . '/copied.txt'))->toBe('data');
    });

    it('refuses to delete a file outside the working directory', function () {
        file_put_contents($this->outside . '/keep.txt', 'data');
        expect(fn() => $this->io->rm($this->outside . '/keep.txt'))->toThrow(\Exception::class);
        expect(file_exists($this->outside . '/keep.txt'))->toBeTrue();
    });

    it('refuses to open a write stream outside the working directory', function () {
        expect(fn() => $this->io->streamOpen($this->outside . '/stream.txt', 'w+'))->toThrow(\Exception::class);
        expect(file_exists($this->outside . '/stream.txt'))->toBeFalse();
    });

    it('does not restrict paths when no working directory is open', function () {
        $io = new \Maho\Io\File();
        file_put_contents($this->outside . '/free.txt', 'data');
        expect($io->rm($this->outside . '/free.txt'))->toBeTrue();
    });
});
