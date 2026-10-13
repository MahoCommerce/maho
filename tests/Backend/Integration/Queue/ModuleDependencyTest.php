<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/** Loads the module declarations from the files that a test gives. */
class QueueModuleDependencyProbe extends Mage_Core_Model_Config
{
    /** @var list<string> */
    public array $files = [];

    #[\Override]
    protected function _getDeclaredModuleFiles()
    {
        return $this->files;
    }

    public function loadDeclarations(): static
    {
        $this->loadString('<config/>');
        $this->_loadDeclaredModules();
        return $this;
    }
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir() . '/maho_queue_dependency_' . uniqid();
    mkdir($this->dir);
    foreach (glob(BP . '/app/etc/modules/*.xml') as $file) {
        copy($file, $this->dir . '/' . basename($file));
    }
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir . '/*.xml'));
    rmdir($this->dir);
});

it('refuses to load the modules when Maho_Queue is disabled', function (): void {
    $file = $this->dir . '/Maho_Queue.xml';
    file_put_contents($file, str_replace('<active>true</active>', '<active>false</active>', file_get_contents($file)));
    $config = new QueueModuleDependencyProbe();
    $config->files = glob($this->dir . '/*.xml');

    expect(fn() => $config->loadDeclarations())->toThrow(Mage_Core_Exception::class, 'requires module "Maho_Queue"');
});
