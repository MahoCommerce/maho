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
                foreach ([SchemaFactoryInterface::class, OpenApiFactoryInterface::class, 'api_platform.graphql.schema_builder'] as $id) {
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

it('resolves every EnumSource declared on an API resource to a non-empty list, and casts the schema values to the property type', function (): void {
    $factory = enumSourceContainer()->get(SchemaFactoryInterface::class);
    $checked = 0;
    foreach (glob(dirname(__DIR__, 4) . '/app/code/core/*/*/Api/*.php') as $file) {
        $source = (string) file_get_contents($file);
        if (!str_contains($source, 'EnumSource::KEY')) {
            continue;
        }
        preg_match('/^namespace\s+([^;]+);/m', $source, $ns);
        $class = ($ns[1] ?? '') . '\\' . basename($file, '.php');
        if (!class_exists($class)) {
            continue;
        }
        // A read-only resource has no input schema: its enums show on the output.
        $schema = $factory->buildSchema($class, 'json', Schema::TYPE_INPUT);
        $root = $schema->getDefinitions()[$schema->getRootDefinitionKey()] ?? null;
        if ($root === null) {
            $schema = $factory->buildSchema($class, 'json', Schema::TYPE_OUTPUT);
            $root = $schema->getDefinitions()[$schema->getRootDefinitionKey()] ?? null;
        }
        expect($root)->not->toBeNull($class);
        foreach (new ReflectionClass($class)->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes(ApiPlatform\Metadata\ApiProperty::class) as $attribute) {
                $extra = $attribute->getArguments()['extraProperties'] ?? [];
                if (!isset($extra[EnumSource::KEY])) {
                    continue;
                }
                $name = $property->getName();
                $values = EnumSource::values($extra[EnumSource::KEY]);
                expect($values)->not->toBe([], "$class::$name resolves to no values");
                $propertySchema = $root['properties'][$name] ?? null;
                if ($propertySchema === null) {
                    continue; // read-only, so absent from the input schema
                }
                $target = in_array('array', (array) ($propertySchema['type'] ?? []), true) ? $propertySchema['items'] : $propertySchema;
                $enum = $target['enum'] ?? null;
                expect($enum)->toBeArray("$class::$name has no enum in the input schema");
                $integer = in_array('integer', (array) ($target['type'] ?? []), true);
                foreach ($enum as $value) {
                    expect($integer ? is_int($value) : is_string($value))->toBeTrue("$class::$name enum value " . var_export($value, true) . ' does not match the property type');
                }
                $checked++;
            }
        }
    }
    expect($checked)->toBeGreaterThan(40);
});

it('gives a string property with an EnumSource a GraphQL enum type, and leaves an integer list alone', function (): void {
    $schema = enumSourceContainer()->get('api_platform.graphql.schema_builder')->getSchema();
    // The object fields are lazy and the enum types are made while they are built.
    $schema->getTypeMap();

    $status = $schema->getType('ProductStatusEnum');
    expect($status)->toBeInstanceOf(GraphQL\Type\Definition\EnumType::class);
    expect(array_map(static fn(GraphQL\Type\Definition\EnumValueDefinition $v): string => $v->name, $status->getValues()))->toBe(['enabled', 'disabled']);
    expect($status->getValue('enabled')?->value)->toBe('enabled');

    $robots = $schema->getType('ProductMetaRobotsEnum');
    expect($robots)->toBeInstanceOf(GraphQL\Type\Definition\EnumType::class);
    expect($robots->getValue('INDEX_FOLLOW')?->value)->toBe('INDEX,FOLLOW');

    $layout = $schema->getType('CmsPagePageLayoutEnum');
    expect($layout)->toBeInstanceOf(GraphQL\Type\Definition\EnumType::class);
    expect(array_map(static fn(GraphQL\Type\Definition\EnumValueDefinition $v): string => $v->value, $layout->getValues()))->toContain('one_column');

    $product = $schema->getType('Product');
    expect($product)->toBeInstanceOf(GraphQL\Type\Definition\ObjectType::class);
    expect($product->getField('status')->getType()->toString())->toBe('ProductStatusEnum!');
    expect($product->getField('websiteIds')->getType()->toString())->not->toContain('Enum');
    expect($schema->getType('ProductWebsiteIdsEnum'))->toBeNull();

    expect(Maho\ApiPlatform\GraphQl\EnumSourceTypeConverter::memberName('24h'))->toBe('_24h');
    expect(Maho\ApiPlatform\GraphQl\EnumSourceTypeConverter::memberName(''))->toBe('EMPTY');
});
