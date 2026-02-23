<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;
use Symfony\Component\HttpFoundation\Request;

// Maho should already be bootstrapped by the controller
// This file is called from within Maho context

// Set environment variables from Maho config
$_ENV['APP_SECRET'] = \Mage::getStoreConfig('maho_apiplatform/oauth2/secret')
    ?: md5(\Mage::getStoreConfig('web/cookie/cookie_domain') . 'api_platform');
$_ENV['CORS_ALLOW_ORIGIN'] = \Mage::getStoreConfig('maho_apiplatform/general/cors_origins') ?: '*';

// Boot Symfony kernel
// Always use prod mode with debug=false to prevent trace leakage in API responses
// Errors are still logged - just not exposed to API clients
$kernel = new Kernel('prod', false);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
