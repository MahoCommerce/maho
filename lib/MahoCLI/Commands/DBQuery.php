<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:query',
    description: 'Execute a SQL query using the database credentials from your local.xml file',
)]
class DBQuery extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument(
            'query',
            InputArgument::REQUIRED,
            'The SQL query to execute',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $host = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/host');
        $dbname = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/dbname');
        $user = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/username');
        $password = (string) Mage::getConfig()->getNode('global/resources/default_setup/connection/password');

        $configFile = $this->createTempMySQLConfig($host, $user, $password);

        $query = $input->getArgument('query');
        $mysqlCommand = sprintf(
            'mysql --defaults-extra-file=%s %s -e %s',
            escapeshellarg($configFile),
            escapeshellarg($dbname),
            escapeshellarg($query),
        );

        $descriptorspec = [
            0 => STDIN,
            1 => STDOUT,
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($mysqlCommand, $descriptorspec, $pipes);
        if (is_resource($process)) {
            // Set error pipe to non-blocking mode
            stream_set_blocking($pipes[2], false);

            // Transfer control to the MySQL client
            while (true) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                usleep(100000); // Sleep for 0.1 seconds
            }

            fclose($pipes[2]);
            proc_close($process);

            return Command::SUCCESS;
        }

        unlink($configFile);
        return Command::FAILURE;
    }

    /**
     * @return string Path to the temporary file
     */
    private function createTempMySQLConfig(#[\SensitiveParameter] string $host, #[\SensitiveParameter] string $user, #[\SensitiveParameter] string $password): string
    {
        $configContent = "[client]\nhost=\"$host\"\nuser=\"$user\"\npassword=\"$password\"\n";
        $configFile = tempnam(sys_get_temp_dir(), '.maho_temp_config_');
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
