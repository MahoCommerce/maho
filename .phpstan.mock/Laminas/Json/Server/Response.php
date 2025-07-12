<?php

namespace Laminas\Json\Server;

class Response
{
    public function setOptions(array $options): self
    {
    }

    public function loadJson(string $json): void
    {
    }

    public function setResult(mixed $value): self
    {
    }

    public function getResult()
    {
    }

    public function setError(?Error $error = null): self
    {
    }

    public function getError(): ?Error
    {
    }

    public function isError(): bool
    {
    }

    public function setId(mixed $name): self
    {
    }

    public function getId(): mixed
    {
    }

    public function setVersion(string $version): self
    {
    }

    public function getVersion(): ?string
    {
    }

    public function toJson(): string
    {
    }

    public function getArgs(): mixed
    {
    }

    public function setArgs(mixed $args): self
    {
    }

    public function setServiceMap(mixed $serviceMap): self
    {
    }

    public function getServiceMap(): ?Smd
    {
    }

    public function __toString(): string
    {
    }
}
