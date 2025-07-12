<?php

namespace Laminas\Json\Server;

class Server
{
    public function addFunction(callable $function, string $namespace = ''): self
    {
    }

    public function setClass(string $class, string $namespace = '', $argv = null): self
    {
    }

    public function fault(string $fault = null, int $code = 404, $data = null): Error
    {
    }

    public function handle(Request|false $request = false): ?Response
    {
    }

    public function loadFunctions(array|Definition $definition): void
    {
    }

    public function setPersistence(int $mode): void
    {
    }

    public function setRequest(Request $request): self
    {
    }

    public function getRequest(): Request
    {
    }

    public function setResponse(Response $response): self
    {
    }

    public function getResponse(): Response
    {
    }

    public function setReturnResponse(bool $flag = true): self
    {
    }

    public function getReturnResponse(): bool
    {
    }

    public function getServiceMap(): Smd
    {
    }

    public function __call(string $method, array $args)
    {
    }
}
