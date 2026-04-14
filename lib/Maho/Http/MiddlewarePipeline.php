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

class MiddlewarePipeline
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /** @var callable */
    private $terminalHandler;

    public function __construct(callable $terminalHandler)
    {
        $this->terminalHandler = $terminalHandler;
    }

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function run(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
    ): void {
        $handler = $this->buildChain($request, $response, 0);
        $handler();
    }

    private function buildChain(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        int $index,
    ): callable {
        if ($index >= count($this->middlewares)) {
            return fn() => ($this->terminalHandler)($request, $response);
        }

        $middleware = $this->middlewares[$index];

        return function () use ($middleware, $request, $response, $index): void {
            if ($response->isRedirect()) {
                return;
            }
            $middleware->process(
                $request,
                $response,
                $this->buildChain($request, $response, $index + 1),
            );
        };
    }
}
