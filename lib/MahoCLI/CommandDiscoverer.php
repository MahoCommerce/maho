<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI;

use Symfony\Component\Console\Command\Command;

class CommandDiscoverer
{
    private string $baseDir;
    private string $namespace;

    public function discover(string $baseDir, string $namespace = 'MahoCLI\\Commands\\'): array
    {
        $this->namespace = $namespace;

        $commands = [];
        $baseDirs = glob($baseDir) ?: [$baseDir];

        foreach ($baseDirs as $dir) {
            $this->baseDir = $dir;
            $commandsDir = "{$dir}/lib/MahoCLI/Commands";

            if (!is_dir($commandsDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($commandsDir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = $file->getPathname();

                if (str_contains($path, 'vendor/mahocommerce/maho')) {
                    continue;
                }

                if (str_ends_with($path, 'BaseMahoCommand.php')) {
                    continue;
                }

                $className = $this->getFullyQualifiedClassName($path);
                if (is_subclass_of($className, Command::class)) {
                    $commands[] = new $className();
                }
            }
        }

        return $commands;
    }

    private function getFullyQualifiedClassName(string $file): string
    {
        $className = str_replace(
            [$this->baseDir, '.php', '/'],
            ['', '', '\\'],
            realpath($file),
        );

        $parts = explode('lib\\MahoCLI\\Commands\\', $className);
        $className = end($parts);

        return $this->namespace . $className;
    }
}
