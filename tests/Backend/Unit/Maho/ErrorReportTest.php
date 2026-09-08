<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An error report stores request data an attacker controls. The file must never hold a PHP
 * tag, otherwise a later file include anywhere in the code base turns the report into a
 * web shell.
 */
test('an error report never stores a PHP tag', function () {
    $reportDir = Mage::getBaseDir('var') . DS . 'report';
    $before = glob($reportDir . DS . '*') ?: [];

    ob_start();
    Maho::errorReport([
        'url' => '/checkout/onepage?x=<?php system("id"); ?>',
        'script_name' => '<?= 1 ?>',
        'trace' => 'short tag <? echo 1; ?> in trace',
    ]);
    ob_end_clean();

    $created = array_values(array_diff(glob($reportDir . DS . '*') ?: [], $before));
    expect($created)->toHaveCount(1);

    $content = (string) file_get_contents($created[0]);
    unlink($created[0]);

    expect($content)->not->toContain('<?')
        ->and(unserialize($content))->toBe([
            'url' => '/checkout/onepage?x=',
            'script_name' => '',
            'trace' => 'short tag  in trace',
        ]);
});
