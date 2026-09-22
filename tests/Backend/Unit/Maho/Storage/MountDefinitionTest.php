<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Maho\Storage\MountDefinition;
use Maho\Storage\StorageException;

/** Stands in for Mage::getBaseDir(...): the real map lives in Mage_Core_Model_Config_Options. */
function storageBaseDir(string $type = 'base'): string
{
    return match ($type) {
        'base' => '/srv/maho',
        'var' => '/mnt/state/var',
        'export' => '/mnt/state/var/export',
        'media' => '/srv/maho/public/media',
        default => throw new Mage_Core_Exception('Invalid dir type requested: ' . $type),
    };
}

function storageDefinition(string $xml, string $name = 'media'): MountDefinition
{
    return MountDefinition::fromElement($name, new Mage_Core_Model_Config_Element($xml), storageBaseDir(...));
}

describe('Maho\Storage\MountDefinition::fromElement', function () {
    it('defaults to the local adapter with no url', function (): void {
        $definition = storageDefinition('<media><path>public/media</path></media>');

        expect($definition->name)->toBe('media')
            ->and($definition->adapterType)->toBe('local')
            ->and($definition->adapterOptions)->toBe([])
            ->and($definition->urlType)->toBeNull()
            ->and($definition->publicUrl)->toBeNull()
            ->and($definition->visibility)->toBeNull();
    });

    it('resolves a relative path against the base dir and keeps an absolute one', function (): void {
        expect(storageDefinition('<m><path>public/media</path></m>', 'm')->path)->toBe('/srv/maho/public/media')
            ->and(storageDefinition('<m><path>/mnt/shared</path></m>', 'm')->path)->toBe('/mnt/shared');
    });

    it('resolves a path against the directory that Maho owns, not the root', function (): void {
        expect(storageDefinition('<m><dir>var</dir><path>import</path></m>', 'm')->path)->toBe('/mnt/state/var/import')
            ->and(storageDefinition('<m><dir>export</dir></m>', 'm')->path)->toBe('/mnt/state/var/export')
            ->and(storageDefinition('<m><dir>var</dir><path>/mnt/shared</path></m>', 'm')->path)->toBe('/mnt/shared');
    });

    it('rejects a dir that Maho does not know', function (): void {
        expect(fn() => storageDefinition('<m><dir>nosuchdir</dir></m>', 'm'))
            ->toThrow(Mage_Core_Exception::class, 'Invalid dir type requested: nosuchdir');
    });

    it('reads url_type, public_url and visibility', function (): void {
        $definition = storageDefinition('<m><path>p</path><url_type>web</url_type><public_url>https://cdn.example.com/</public_url><visibility>private</visibility></m>', 'm');

        expect($definition->urlType)->toBe('web')
            ->and($definition->publicUrl)->toBe('https://cdn.example.com/')
            ->and($definition->visibility)->toBe('private');
    });

    it('reads the adapter type and options', function (): void {
        $definition = storageDefinition(
            '<m><path>p</path><adapter><type>s3</type><bucket>b</bucket><endpoint/><use_path_style_endpoint>1</use_path_style_endpoint></adapter></m>',
            'm',
        );

        expect($definition->adapterType)->toBe('s3')
            ->and($definition->option('bucket'))->toBe('b')
            ->and($definition->option('endpoint'))->toBeNull()
            ->and($definition->option('missing'))->toBeNull()
            ->and($definition->flag('use_path_style_endpoint'))->toBeTrue()
            ->and($definition->flag('missing'))->toBeFalse();
    });

    it('reads flags like the rest of the config', function (string $value, bool $expected): void {
        $definition = storageDefinition("<m><path>p</path><adapter><type>x</type><f>$value</f></adapter></m>", 'm');

        expect($definition->flag('f'))->toBe($expected);
    })->with([
        ['1', true],
        ['true', true],
        ['yes', true],
        ['0', false],
        ['false', false],
        ['off', false],
        ['', false],
    ]);

    it('rejects a plain adapter value', function (): void {
        expect(fn() => storageDefinition('<m><path>p</path><adapter>s3</adapter></m>', 'm'))
            ->toThrow(StorageException::class, 'must be a block');
    });

    it('rejects an adapter block without a type', function (): void {
        expect(fn() => storageDefinition('<m><path>p</path><adapter><bucket>b</bucket></adapter></m>', 'm'))
            ->toThrow(StorageException::class, 'no <type>');
    });

    it('rejects an invalid name', function (): void {
        expect(fn() => storageDefinition('<x><path>p</path></x>', 'Bad-Name'))
            ->toThrow(StorageException::class, 'Invalid storage mount name');
    });

    it('requires a path for the local adapter', function (): void {
        expect(fn() => storageDefinition('<m><url_type>media</url_type></m>', 'm'))
            ->toThrow(StorageException::class, 'needs a <path>');
    });

    it('does not require a path for a remote adapter', function (): void {
        expect(storageDefinition('<m><adapter><type>s3</type><bucket>b</bucket></adapter></m>', 'm')->path)->toBeNull();
    });

    it('rejects an unknown url_type or visibility', function (): void {
        expect(fn() => storageDefinition('<m><path>p</path><url_type>skin</url_type></m>', 'm'))
            ->toThrow(StorageException::class, 'invalid <url_type>')
            ->and(fn() => storageDefinition('<m><path>p</path><visibility>hidden</visibility></m>', 'm'))
            ->toThrow(StorageException::class, 'invalid <visibility>');
    });
});
