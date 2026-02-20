<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

trait DatabaseCliTrait
{
    private function getEngine(mixed $connConfig): string
    {
        if (!empty($connConfig->engine)) {
            return (string) $connConfig->engine;
        }

        if (!empty($connConfig->type)) {
            $type = (string) $connConfig->type;
            if (str_starts_with($type, 'pdo_')) {
                return substr($type, 4);
            }
            return $type;
        }

        if (!empty($connConfig->model)) {
            return (string) $connConfig->model;
        }

        return 'mysql';
    }

    private function createTempMySQLConfig(
        #[\SensitiveParameter]
        string $host,
        #[\SensitiveParameter]
        string $user,
        #[\SensitiveParameter]
        string $password,
    ): string {
        $configContent = "[client]\nhost=\"$host\"\nuser=\"$user\"\npassword=\"$password\"\n";
        $configFile = tempnam(sys_get_temp_dir(), '.maho_temp_config_');
        chmod($configFile, 0600);
        file_put_contents($configFile, $configContent);

        $this->scheduleFileDeletion($configFile);

        return $configFile;
    }

    private function createTempPgpassFile(
        #[\SensitiveParameter]
        string $host,
        #[\SensitiveParameter]
        string $port,
        #[\SensitiveParameter]
        string $dbname,
        #[\SensitiveParameter]
        string $user,
        #[\SensitiveParameter]
        string $password,
    ): string {
        // pgpass format: hostname:port:database:username:password
        $configContent = sprintf("%s:%s:%s:%s:%s\n", $host, $port, $dbname, $user, $password);
        $configFile = tempnam(sys_get_temp_dir(), '.maho_pgpass_');
        chmod($configFile, 0600);
        file_put_contents($configFile, $configContent);

        $this->scheduleFileDeletion($configFile);

        return $configFile;
    }

    private function scheduleFileDeletion(string $filename): void
    {
        exec(sprintf(
            '(sleep 1 && rm -f %s) > /dev/null 2>&1 &',
            escapeshellarg($filename),
        ));
    }
}
