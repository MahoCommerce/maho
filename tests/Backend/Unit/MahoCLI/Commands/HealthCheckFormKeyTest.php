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
 * The storefront refuses a POST request without a form key. The health check names each
 * project template whose POST form sends none.
 */
if (!defined('MAHO_ROOT_DIR')) {
    define('MAHO_ROOT_DIR', Maho::getBasePath());
}

function formKeyScan(): array
{
    $command = new HealthCheck();
    $method = new ReflectionMethod($command, 'checkFormsWithoutFormKey');

    return $method->invoke($command);
}

function formKeyWriteTemplate(string $body): string
{
    $dir = MAHO_ROOT_DIR . '/app/design/frontend/healthcheckfixture/default/template/contact';
    mkdir($dir, 0o777, true);
    file_put_contents("$dir/form.phtml", $body);

    return 'app/design/frontend/healthcheckfixture/default/template/contact/form.phtml';
}

afterEach(function () {
    $base = MAHO_ROOT_DIR . '/app/design/frontend/healthcheckfixture';
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
});

it('names the line of a POST form without a form key', function () {
    $path = formKeyWriteTemplate(<<<'PHTML'
        <div>
        <form action="<?= $this->getUrl('contacts/index/post') ?>"
              method="post">
            <input name="comment">
        </form>
        </div>
        PHTML);

    expect(formKeyScan())->toHaveKey($path)
        ->and(formKeyScan()[$path])->toBe([2]);
});

it('says nothing about a POST form that renders the form key', function () {
    $path = formKeyWriteTemplate(<<<'PHTML'
        <form action="<?= $this->getUrl('contacts/index/post') ?>" method="post">
            <?= $this->getBlockHtml('formkey') ?>
        </form>
        PHTML);

    expect(formKeyScan())->not->toHaveKey($path);
});

it('says nothing about a GET form', function () {
    $path = formKeyWriteTemplate('<form action="<?= $this->getUrl(\'catalogsearch/result\') ?>" method="get"></form>');

    expect(formKeyScan())->not->toHaveKey($path);
});
