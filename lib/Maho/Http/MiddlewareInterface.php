<?php

/**
 * Maho
 *
 * @package    Maho_Http
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Http;

interface MiddlewareInterface
{
    /**
     * Process the request through this middleware.
     *
     * Call $next to pass control to the next middleware in the pipeline.
     * To short-circuit (e.g. for redirects), set the response and return without calling $next.
     */
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void;
}
