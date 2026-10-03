<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use Mage\Cms\Api\CmsPage;
use Maho\ApiPlatform\Kernel;
use Maho\ApiPlatform\Metadata\EnumSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
|--------------------------------------------------------------------------
| EnumSource: live value lists as schema enums
|--------------------------------------------------------------------------
|
| A property that names an EnumSource must show the store's current values as an
| enum in the JSON schema and in the OpenAPI document, read at build time.
|
*/

final class EnumSourceKernel extends Kernel
{
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                foreach ([SchemaFactoryInterface::class, OpenApiFactoryInterface::class] as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    } elseif ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}

function enumSourceContainer(): \Psr\Container\ContainerInterface
{
    static $container = null;
    if ($container === null) {
        Mage::app();
        $kernel = new EnumSourceKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
    }
    return $container;
}

it('resolves a model alias and a static callable to value lists', function (): void {
    $layouts = EnumSource::values('page/source_layout');
    expect($layouts)->toContain('one_column', 'empty');
    expect($layouts)->not->toContain('');
    expect(EnumSource::values('core/locale::getOptionWeekdays'))->toHaveCount(7);
    expect(EnumSource::values(''))->toBe([]);
    expect(EnumSource::values('no/such_source'))->toBe([]);
});

it('puts the live layout codes in the JSON schema of the CMS page', function (): void {
    $schema = enumSourceContainer()->get(SchemaFactoryInterface::class)->buildSchema(CmsPage::class, 'json', Schema::TYPE_INPUT);
    $root = $schema->getDefinitions()[$schema->getRootDefinitionKey()];
    $expected = array_keys(Mage::getSingleton('page/source_layout')->getOptions());

    expect($root['properties']['pageLayout']['enum'])->toBe(array_map(strval(...), $expected));
    expect($root['properties']['customRootTemplate']['enum'])->toBe(array_map(strval(...), $expected));
    expect($root['properties']['title'])->not->toHaveKey('enum');
});

it('shows the enum in the OpenAPI document', function (): void {
    $openApi = enumSourceContainer()->get(OpenApiFactoryInterface::class)->__invoke();
    $schemas = $openApi->getComponents()->getSchemas();
    $found = false;
    foreach ($schemas as $name => $definition) {
        if (!str_starts_with((string) $name, 'CmsPage') || !isset($definition['properties']['pageLayout'])) {
            continue;
        }
        $found = true;
        expect($definition['properties']['pageLayout']['enum'] ?? null)->toContain('one_column');
    }
    expect($found)->toBeTrue();
});
