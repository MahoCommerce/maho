<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\HealthCheck;

uses(Tests\MahoBackendTestCase::class);

/**
 * A website pricing in another currency derives its prices, so a module reading the attribute data
 * directly gets the unconverted amount. The health check names the file and line rather than
 * leaving it to the release notes.
 */
/** The CLI entry point defines it; the health check's scanners read it. */
if (!defined('MAHO_ROOT_DIR')) {
    define('MAHO_ROOT_DIR', Maho::getBasePath());
}

function priceReadsScan(): array
{
    $command = new HealthCheck();
    $method = new ReflectionMethod($command, 'checkUnconvertedPriceReads');

    return $method->invoke($command);
}

function priceReadsWriteModule(string $body): string
{
    $dir = MAHO_ROOT_DIR . '/app/code/local/HealthCheckFixture/Shop/Model';
    mkdir($dir, 0o777, true);
    file_put_contents("$dir/Price.php", $body);

    return 'app/code/local/HealthCheckFixture/Shop/Model/Price.php';
}

/** A template in a project theme, which is where a storefront reads prices. */
function priceReadsWriteTemplate(string $body): string
{
    $dir = MAHO_ROOT_DIR . '/app/design/frontend/healthcheckfixture/default/template/catalog';
    mkdir($dir, 0o777, true);
    file_put_contents("$dir/price.phtml", $body);

    return 'app/design/frontend/healthcheckfixture/default/template/catalog/price.phtml';
}

function priceReadsRemoveTree(string $base): void
{
    if (!is_dir($base)) {
        return;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($base);
}

afterEach(function () {
    priceReadsRemoveTree(MAHO_ROOT_DIR . '/app/code/local/HealthCheckFixture');
    @rmdir(MAHO_ROOT_DIR . '/app/code/local');
    priceReadsRemoveTree(MAHO_ROOT_DIR . '/app/design/frontend/healthcheckfixture');
});

it('names a price model that reads the attribute data directly', function () {
    $path = priceReadsWriteModule(<<<'PHP'
        <?php

        class HealthCheckFixture_Shop_Model_Price extends Mage_Catalog_Model_Product_Type_Price
        {
            public function getPrice($product)
            {
                return $product->getData('price');
            }
        }
        PHP);

    $findings = priceReadsScan();

    expect($findings)->toHaveKey($path)
        ->and(array_keys($findings[$path]))->toContain('extends a core price model, check that its getPrice() derives')
        ->and(array_keys($findings[$path]))
        ->toContain("getData('price') instead of getPriceAttributeValue()");
});

it('says nothing about a module that reads the derived value', function () {
    $path = priceReadsWriteModule(<<<'PHP'
        <?php

        class HealthCheckFixture_Shop_Model_Price
        {
            public function forWebsite(Mage_Catalog_Model_Product $product): ?float
            {
                return $product->getPriceAttributeValue('price');
            }
        }
        PHP);

    expect(priceReadsScan())->not->toHaveKey($path);
});

it('reads templates in a project theme, not only module php', function () {
    $path = priceReadsWriteTemplate(
        '<?php echo $this->helper(\'core\')->currency($_product->getData(\'price\')) ?>' . "\n",
    );

    $findings = priceReadsScan();

    expect($findings)->toHaveKey($path)
        ->and(array_keys($findings[$path]))
        ->toContain("getData('price') instead of getPriceAttributeValue()");
});
