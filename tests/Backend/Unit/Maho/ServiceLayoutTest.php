<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

/**
 * Tripwire for the service layout of #1348. A service lives in the Service/ folder of its module, its
 * module declares the class group under <global><services>, and code gets it with Mage::getService(),
 * never with new, so that a <rewrite> reaches every caller.
 */

uses(MahoBackendTestCase::class);

/** Classes whose names end in Service but that are not services. */
const NOT_SERVICES = [
    'Mage/Adminhtml/Model/System/Config/Source/Currency/Service.php', // The config source of the currency rate services
    'Mage/Cms/Model/Resource/Page/Service.php', // A resource model
];

/**
 * @return list<string> the paths under app/code/core of the PHP files that match $pattern
 */
function coreFiles(string $pattern): array
{
    $root = Mage::getBaseDir() . '/app/code/core/';
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        $path = substr($file->getPathname(), strlen($root));
        if (str_ends_with($path, '.php') && preg_match($pattern, $path)) {
            $files[] = $path;
        }
    }
    sort($files);
    return $files;
}

it('keeps every class named Service in a Service folder', function () {
    $outside = array_filter(
        coreFiles('#Service\.php$#'),
        fn(string $path): bool => !str_contains($path, '/Service/') && !in_array($path, NOT_SERVICES, true),
    );

    expect(array_values($outside))->toBe([]);
});

it('declares the class group of every Service folder', function () {
    $declared = [];
    foreach (Mage::getConfig()->getNode('global/services')?->children() ?? [] as $group) {
        $declared[] = (string) $group->class;
    }

    $missing = [];
    foreach (coreFiles('#^(Mage|Maho)/[A-Za-z]+/Service/[A-Za-z]+\.php$#') as $path) {
        [$pool, $module] = explode('/', $path);
        $prefix = "{$pool}_{$module}_Service";
        if (!in_array($prefix, $declared, true)) {
            $missing[] = $prefix;
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

it('never creates a service with new', function () {
    $offenders = [];
    foreach (coreFiles('#\.php$#') as $path) {
        $code = (string) file_get_contents(Mage::getBaseDir() . '/app/code/core/' . $path);
        if (preg_match('#new \\\\?(Mage|Maho)_[A-Za-z]+_Service_[A-Za-z_]+\(#', $code)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('resolves each moved service through its alias', function (string $alias, string $class) {
    expect(Mage::getService($alias))->toBeInstanceOf($class);
})->with([
    ['checkout/cart', Mage_Checkout_Service_Cart::class],
    ['customer/customer', Mage_Customer_Service_Customer::class],
    ['sales/order', Mage_Sales_Service_Order::class],
    ['revocation/request', Maho_Revocation_Service_Request::class],
    ['sociallogin/identity', Maho_SocialLogin_Service_Identity::class],
    ['customersegmentation/segment', Maho_CustomerSegmentation_Service_Segment::class],
]);
