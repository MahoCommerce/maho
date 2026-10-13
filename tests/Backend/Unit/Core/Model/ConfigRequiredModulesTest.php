<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/** Loads the module declarations from the files that a test gives. */
class ConfigRequiredModulesProbe extends Mage_Core_Model_Config
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
    $this->dir = sys_get_temp_dir() . '/maho_required_modules_' . uniqid();
    mkdir($this->dir);
    $this->declare = function (string $module, string $active, string $depends = ''): string {
        $file = "{$this->dir}/{$module}.xml";
        file_put_contents($file, "<config><modules><{$module}><active>{$active}</active>"
            . "<codePool>core</codePool>{$depends}</{$module}></modules></config>");
        return $file;
    };
});

afterEach(function (): void {
    array_map(unlink(...), glob($this->dir . '/*.xml'));
    rmdir($this->dir);
});

it('keeps a required module active when its declaration disables it', function (): void {
    $config = new ConfigRequiredModulesProbe();
    $config->files = [
        ($this->declare)('Mage_Core', 'true'),
        ($this->declare)('Maho_Queue', 'false', '<depends><Mage_Core/></depends>'),
        ($this->declare)('Maho_Blog', 'false'),
    ];

    $config->loadDeclarations();

    expect((string) $config->getNode('modules/Maho_Queue/active'))->toBe('true')
        ->and((string) $config->getNode('modules/Maho_Blog/active'))->toBe('false');
});
