<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Helper\FileChangeWatcher;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->file = tempnam(sys_get_temp_dir(), 'watch');
    file_put_contents($this->file, 'a');
    $this->watcher = new FileChangeWatcher([$this->file], 0.3);
});

afterEach(function (): void {
    @unlink($this->file);
});

it('gives false while no file changes', function (): void {
    expect($this->watcher->poll(10.0))->toBeFalse()
        ->and($this->watcher->poll(20.0))->toBeFalse();
});

it('gives true one time when a file changed and then stayed the same for the settle time', function (): void {
    file_put_contents($this->file, 'b');

    expect($this->watcher->poll(10.0))->toBeFalse()
        ->and($this->watcher->poll(10.1))->toBeFalse()
        ->and($this->watcher->poll(10.4))->toBeTrue()
        ->and($this->watcher->poll(11.0))->toBeFalse();
});

it('waits again when the file changes during the settle time', function (): void {
    file_put_contents($this->file, 'b');
    $this->watcher->poll(10.0);
    file_put_contents($this->file, 'c');

    expect($this->watcher->poll(10.2))->toBeFalse()
        ->and($this->watcher->poll(10.4))->toBeFalse()
        ->and($this->watcher->poll(10.5))->toBeTrue();
});

it('sees a change of the content inside the same second', function (): void {
    $time = filemtime($this->file);
    file_put_contents($this->file, 'b');
    touch($this->file, $time);

    expect($this->watcher->poll(10.0))->toBeFalse()
        ->and($this->watcher->poll(10.3))->toBeTrue();
});

it('gives true after the settle time when a change is marked', function (): void {
    $this->watcher->markChanged(10.0);

    expect($this->watcher->poll(10.1))->toBeFalse()
        ->and($this->watcher->poll(10.3))->toBeTrue();
});
