<?php

namespace MahoCLI;

use Symfony\Component\Console\Command\Command;

class CommandDiscoverer
{
    private string $baseDir;
    private string $namespace;

    public function discover(string $baseDir, string $namespace = 'MahoCLI\\Commands\\'): array
    {
        $this->baseDir = $baseDir;
        $this->namespace = $namespace;

        $commands = [];
        $files = glob("{$this->baseDir}/lib/MahoCLI/Commands/*.php");

        foreach ($files as $file) {
            if (str_contains($file, "vendor/mahocommerce/maho")) {
                continue;
            }

            $className = $this->getFullyQualifiedClassName($file);
            if (is_subclass_of($className, Command::class)) {
                $commands[] = new $className();
            }
        }

        return $commands;
    }

    private function getFullyQualifiedClassName($file): string
    {
        $className = str_replace(
            [$this->baseDir, '.php', '/'],
            ['', '', '\\'],
            realpath($file)
        );

        $parts = explode('lib\\MahoCLI\\Commands\\', $className);
        $className = end($parts);

        return $this->namespace . $className;
    }
}
