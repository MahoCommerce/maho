<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Maho\ApiPlatform\Kernel;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Security\ApiPermissionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Field-visibility regression guard.
 *
 * Which fields a caller sees is decided by API Platform's per-property
 * `security:` / `securityPostDenormalize:` expressions, evaluated by
 * AbstractItemNormalizer, so one declaration covers REST, GraphQL and MCP,
 * in both directions. Nothing strips fields in a provider any more, which
 * makes these expressions the only gate and worth pinning down:
 *
 *   1. `is_back_office('<resource>')` resolves the way the grant model says it
 *      should, wildcards included.
 *   2. Round-tripping a DTO through the real serializer actually drops the
 *      gated fields, including the stockItem policy columns a property
 *      expression cannot reach.
 *   3. Write-side gates reset an unprivileged caller's admin-only input.
 *   4. Every referenced permission is real (a typo would silently always-deny).
 *   5. The set of fields an unauthenticated caller can read matches the
 *      committed snapshot, so a newly added property cannot join the public
 *      surface without someone editing that file on purpose.
 */

/**
 * Test kernel that publishes the (private) services these assertions drive.
 * The production container is untouched.
 */
final class PropertyAuthzKernel extends Kernel
{
    private const EXPOSE = [
        'api_platform.serializer',
        'api_platform.security.resource_access_checker',
        'security.token_storage',
        ResourceNameCollectionFactoryInterface::class,
        PropertyNameCollectionFactoryInterface::class,
        PropertyMetadataFactoryInterface::class,
    ];

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                foreach (PropertyAuthzKernel::exposedServiceIds() as $id) {
                    if ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    } elseif ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }

    /** @return list<string> */
    public static function exposedServiceIds(): array
    {
        return self::EXPOSE;
    }
}

function propertyAuthzContainer(): \Psr\Container\ContainerInterface
{
    static $container = null;
    if ($container === null) {
        Mage::app();
        $kernel = new PropertyAuthzKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer();
    }
    return $container;
}

/**
 * Authenticate the container as the given caller for the duration of one
 * assertion. Null clears the token (unauthenticated).
 *
 * @param list<string> $permissions
 */
function propertyAuthzAuthenticate(?string $kind, array $permissions = []): void
{
    $storage = propertyAuthzContainer()->get('security.token_storage');

    if ($kind === null) {
        $storage->setToken(null);
        return;
    }

    $user = match ($kind) {
        'admin' => new ApiUser('admin', ['ROLE_ADMIN'], null, 1),
        'customer' => new ApiUser('customer', ['ROLE_CUSTOMER'], 1),
        'api' => new ApiUser('service', ['ROLE_API_USER'], null, null, 1, $permissions),
    };

    $storage->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
}

/** Evaluate a security expression as the currently authenticated caller. */
function propertyAuthzEvaluate(string $expression): bool
{
    return propertyAuthzContainer()
        ->get('api_platform.security.resource_access_checker')
        ->isGranted(Mage\Catalog\Api\Product::class, $expression);
}

/**
 * Normalize a DTO through the real API Platform serializer.
 *
 * @return array<string, mixed>
 */
function propertyAuthzNormalize(object $dto, array $groups = []): array
{
    $context = ['resource_class' => $dto::class];
    if ($groups !== []) {
        $context['groups'] = $groups;
    }

    /** @var array<string, mixed> */
    return propertyAuthzContainer()->get('api_platform.serializer')->normalize($dto, 'json', $context);
}

afterEach(function (): void {
    propertyAuthzAuthenticate(null);
});

it('grants is_back_office to admins and to service tokens holding the resource', function (): void {
    $cases = [
        'unauthenticated' => [null, [], false],
        'admin' => ['admin', [], true],
        'customer' => ['customer', [], false],
        'service token with products/read' => ['api', ['products/read'], true],
        'service token with products/write' => ['api', ['products/write'], true],
        'service token with products/all' => ['api', ['products/all'], true],
        'service token with the global all' => ['api', ['all'], true],
        'service token with only products/delete' => ['api', ['products/delete'], false],
        'service token for another resource' => ['api', ['orders/read'], false],
    ];

    foreach ($cases as $label => [$kind, $permissions, $expected]) {
        propertyAuthzAuthenticate($kind, $permissions);
        expect(propertyAuthzEvaluate("is_back_office('products')"))
            ->toBe($expected, "is_back_office('products') for {$label}");
    }
});

it('omits back-office product fields for a caller without a products grant', function (): void {
    $product = new Mage\Catalog\Api\Product();
    $product->id = 1;
    $product->sku = 'SKU-1';
    $product->cost = 12.34;
    $product->customDesign = 'default/modern';
    $product->customLayoutUpdate = '<reference name="content"/>';
    $product->customAttributes = ['internal_note' => 'do not show'];

    $groups = ['product:read', 'product:detail'];
    $gated = ['cost', 'customDesign', 'customLayoutUpdate', 'customAttributes'];

    propertyAuthzAuthenticate(null);
    expect(array_keys(propertyAuthzNormalize($product, $groups)))
        ->not->toContain(...$gated)
        ->toContain('sku');

    propertyAuthzAuthenticate('api', ['orders/read']);
    expect(array_keys(propertyAuthzNormalize($product, $groups)))->not->toContain(...$gated);

    propertyAuthzAuthenticate('api', ['products/read']);
    expect(array_keys(propertyAuthzNormalize($product, $groups)))->toContain(...$gated);

    propertyAuthzAuthenticate('admin');
    expect(array_keys(propertyAuthzNormalize($product, $groups)))->toContain(...$gated);
});

it('omits inventory policy columns from stockItem for a caller without a products grant', function (): void {
    $product = new Mage\Catalog\Api\Product();
    $product->id = 1;
    $product->sku = 'SKU-1';
    $product->stockItem = [
        'qty' => 5.0,
        'is_in_stock' => true,
        'min_sale_qty' => 1.0,
        'min_qty' => 2.0,
        'backorders' => 1,
        'manage_stock' => true,
        'notify_stock_qty' => 3.0,
        'use_config_min_qty' => true,
    ];

    $groups = ['product:read', 'product:detail'];
    $policy = ['min_qty', 'backorders', 'manage_stock', 'notify_stock_qty', 'use_config_min_qty'];
    $public = ['qty', 'is_in_stock', 'min_sale_qty'];

    propertyAuthzAuthenticate(null);
    $columns = array_keys(propertyAuthzNormalize($product, $groups)['stockItem']);
    expect($columns)->not->toContain(...$policy)->toContain(...$public);

    propertyAuthzAuthenticate('api', ['products/write']);
    $columns = array_keys(propertyAuthzNormalize($product, $groups)['stockItem']);
    expect($columns)->toContain(...$policy)->toContain(...$public);
});

it('omits back-office fields on other resources for unprivileged callers', function (): void {
    $page = new Mage\Cms\Api\CmsPage();
    $page->id = 1;
    $page->title = 'Home';
    $page->layoutUpdateXml = '<reference name="content"/>';
    $page->customTheme = 'default/modern';

    propertyAuthzAuthenticate(null);
    expect(array_keys(propertyAuthzNormalize($page)))
        ->not->toContain('layoutUpdateXml', 'customTheme')
        ->toContain('title');

    propertyAuthzAuthenticate('api', ['cms-pages/read']);
    expect(array_keys(propertyAuthzNormalize($page)))->toContain('layoutUpdateXml', 'customTheme');

    $order = new Mage\Sales\Api\Order();
    $order->id = 1;
    $order->remoteIp = '203.0.113.7';
    $order->xForwardedFor = '198.51.100.4';

    propertyAuthzAuthenticate('customer');
    expect(array_keys(propertyAuthzNormalize($order)))->not->toContain('remoteIp', 'xForwardedFor');

    propertyAuthzAuthenticate('api', ['orders/read']);
    expect(array_keys(propertyAuthzNormalize($order)))->toContain('remoteIp', 'xForwardedFor');
});

it('resets admin-only customer fields submitted by an unprivileged caller', function (): void {
    $payload = (string) Mage::helper('core')->jsonEncode([
        'email' => 'shopper@example.com',
        'firstname' => 'Shopper',
        'groupId' => 3,
        'isActive' => true,
        'taxvat' => 'IT12345678901',
        'disableAutoGroupChange' => true,
    ]);

    $deserialize = static fn(): Mage\Customer\Api\Customer => propertyAuthzContainer()
        ->get('api_platform.serializer')
        ->deserialize($payload, Mage\Customer\Api\Customer::class, 'json', [
            'resource_class' => Mage\Customer\Api\Customer::class,
        ]);

    // Public registration: the profile fields land, the admin-only ones do not.
    propertyAuthzAuthenticate(null);
    $customer = $deserialize();
    expect($customer->email)->toBe('shopper@example.com')
        ->and($customer->firstname)->toBe('Shopper')
        ->and($customer->groupId)->toBeNull()
        ->and($customer->isActive)->toBeNull()
        ->and($customer->taxvat)->toBeNull()
        ->and($customer->disableAutoGroupChange)->toBeNull();

    // A customer's own token is no more privileged than an anonymous one here.
    propertyAuthzAuthenticate('customer');
    expect($deserialize()->groupId)->toBeNull();

    propertyAuthzAuthenticate('api', ['customers/create']);
    $customer = $deserialize();
    expect($customer->groupId)->toBe(3)
        ->and($customer->isActive)->toBeTrue()
        ->and($customer->taxvat)->toBe('IT12345678901');

    propertyAuthzAuthenticate('admin');
    expect($deserialize()->groupId)->toBe(3);
});

it('references only real, grantable permissions in every property security expression', function (): void {
    $valid = (new ApiPermissionRegistry())->getPermissionIds();
    $phantom = [];

    foreach (propertyAuthzSecuredProperties() as $label => $expression) {
        // `is_granted('resource/op')` and `is_back_office('resource')` both name a
        // resource that has to exist; a typo in either is silently always-denied.
        preg_match_all("/is_granted\\(\\s*['\"]([^'\"]+)['\"]/", $expression, $granted);
        foreach (array_filter($granted[1], static fn(string $a): bool => str_contains($a, '/')) as $permission) {
            if (!in_array($permission, $valid, true)) {
                $phantom[] = "{$label} -> {$permission}";
            }
        }

        preg_match_all("/is_back_office\\(\\s*['\"]([^'\"]+)['\"]/", $expression, $backOffice);
        foreach ($backOffice[1] as $resourceId) {
            if (!in_array($resourceId . '/read', $valid, true) && !in_array($resourceId . '/write', $valid, true)) {
                $phantom[] = "{$label} -> is_back_office({$resourceId})";
            }
        }
    }

    expect($phantom)->toBe(
        [],
        'Property security expressions naming a permission that cannot be granted: ' . implode(', ', $phantom),
    );
});

it('exposes exactly the snapshotted set of fields to an unauthenticated caller', function (): void {
    $snapshotFile = __DIR__ . '/public-api-fields.php';
    $expected = require $snapshotFile;
    $actual = propertyAuthzPublicFields();

    // Reported per resource so the failure names the field that changed rather
    // than dumping the whole surface.
    foreach ($actual as $shortName => $fields) {
        expect($fields)->toBe(
            $expected[$shortName] ?? [],
            "Fields readable without authentication changed for {$shortName}. If the change is "
            . 'intended, update ' . basename($snapshotFile) . '; if not, gate the new property with '
            . "#[ApiProperty(security: \"is_back_office('…')\")].",
        );
    }

    expect(array_keys($actual))->toBe(array_keys($expected), 'The set of API resources changed.');
});

/**
 * Every property carrying a security expression, keyed by `ShortName::property`.
 *
 * @return array<string, string>
 */
function propertyAuthzSecuredProperties(): array
{
    $secured = [];
    foreach (propertyAuthzResourceProperties() as $resourceClass => $properties) {
        $shortName = (new ReflectionClass($resourceClass))->getShortName();
        foreach ($properties as $property => $metadata) {
            foreach (['security' => $metadata->getSecurity(), 'securityPostDenormalize' => $metadata->getSecurityPostDenormalize()] as $kind => $expression) {
                if (is_string($expression) && trim($expression) !== '') {
                    $secured["{$shortName}::{$property} ({$kind})"] = $expression;
                }
            }
        }
    }
    return $secured;
}

/**
 * Readable properties per resource that survive an unauthenticated read, keyed
 * by short name and sorted, i.e. the public API surface.
 *
 * @return array<string, list<string>>
 */
function propertyAuthzPublicFields(): array
{
    propertyAuthzAuthenticate(null);
    $checker = propertyAuthzContainer()->get('api_platform.security.resource_access_checker');

    $public = [];
    foreach (propertyAuthzResourceProperties() as $resourceClass => $properties) {
        $fields = [];
        foreach ($properties as $property => $metadata) {
            if ($metadata->isReadable() === false) {
                continue;
            }
            $security = $metadata->getSecurity();
            if (is_string($security) && !$checker->isGranted($resourceClass, $security)) {
                continue;
            }
            $fields[] = $property;
        }
        sort($fields);
        $public[(new ReflectionClass($resourceClass))->getShortName()] = $fields;
    }

    ksort($public);
    return $public;
}

/**
 * Property metadata for every Maho API resource, keyed by class then property.
 *
 * @return array<class-string, array<string, ApiPlatform\Metadata\ApiProperty>>
 */
function propertyAuthzResourceProperties(): array
{
    static $properties = null;
    if ($properties !== null) {
        return $properties;
    }

    $container = propertyAuthzContainer();
    $nameFactory = $container->get(ResourceNameCollectionFactoryInterface::class);
    $propertyNames = $container->get(PropertyNameCollectionFactoryInterface::class);
    $propertyMetadata = $container->get(PropertyMetadataFactoryInterface::class);

    $properties = [];
    foreach ($nameFactory->create() as $resourceClass) {
        // API Platform's own Error/ConstraintViolation resources model response
        // shapes, not Maho data.
        if (str_starts_with($resourceClass, 'ApiPlatform\\')) {
            continue;
        }

        foreach ($propertyNames->create($resourceClass) as $property) {
            $properties[$resourceClass][$property] = $propertyMetadata->create($resourceClass, $property);
        }
    }

    return $properties;
}
