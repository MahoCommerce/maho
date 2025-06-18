<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// Require the autoloader if not already loaded
if (!class_exists('Mage')) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require __DIR__ . '/../vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
        require __DIR__ . '/../../../autoload.php';
    } else {
        throw new Exception('Autoloader not found. Please run \'composer install\'.');
    }
}

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('PS') || define('PS', PATH_SEPARATOR);
defined('BP') || define('BP', Maho::getBasePath());

/** @deprecated */
defined('MAGENTO_ROOT') || define('MAGENTO_ROOT', BP);

if (!empty($_SERVER['MAGE_IS_DEVELOPER_MODE']) || !empty($_ENV['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);

    ini_set('display_errors', '1');
    ini_set('error_prepend_string', '<pre>');
    ini_set('error_append_string', '</pre>');

    // Fix for overriding zf1-future during development
    ini_set('opcache.revalidate_path', 1);

    // Update Composer's autoloader during development in case new files are added
    Maho::updateComposerAutoloader();

    // Check if we used `composer dump --optimize-autoloader` in development
    if (Maho::isComposerAutoloaderOptimized()) {
        Mage::addBootupWarning('Optimized autoloader detected in developer mode.');
    }
}

if (!function_exists('dd')) {
    function dd(...$vars) {
        foreach ($vars as $var) {
            \Symfony\Component\VarDumper\VarDumper::dump($var);
        }
        die();
    }
}
