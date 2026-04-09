<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Test Bootstrap
 *
 * Loads Composer autoloader and registers test case classes.
 */

declare(strict_types=1);

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Register test case classes
spl_autoload_register(function ($class): void {
    // Only handle Tests\ namespace
    if (strpos($class, 'Tests\\') !== 0) {
        return;
    }

    // Convert namespace to file path
    $file = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($class, 6)) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
