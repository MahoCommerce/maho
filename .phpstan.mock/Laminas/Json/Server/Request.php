<?php

namespace Laminas\Json\Server;

class Request
{
    public function setOptions(array $options): self
    {
    }

    public function addParam(mixed $value, $key = null): self
    {
    }

    public function addParams(array $params): self
    {
    }

    public function setParams(array $params): self
    {
    }

    public function getParam(int|string $index): mixed|null
    {
    }

    public function getParams(): array
    {
    }

    public function setMethod(string $name): self
    {
    }

    public function getMethod(): string
    {
    }

    public function isMethodError(): bool
    {
    }

    public function isParseError(): bool
    {
    }

    public function setId(mixed $name): self
    {
    }

    public function getId(): string
    {
    }

    public function setVersion(string $version): self
    {
    }

    public function getVersion(): string
    {
    }

    public function loadJson(string $json): void
    {
    }

    public function toJson(): string
    {
    }

    public function __toString(): string
    {
    }
}
