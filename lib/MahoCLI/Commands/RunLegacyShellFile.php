<?php

namespace Maho\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy:run-shell-file',
    description: 'Run legacy shell file'
)]
class RunLegacyShellFile extends BaseMahoCommand
{
    protected function configure(): void
    {
        $this->addArgument('filename', InputArgument::REQUIRED, 'The file you want to run, eg: indexer.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getArgument('filename');
        if (!str_ends_with($filename, '.php')) {
            $filename .= '.php';
        }

        $fileToRun = self::findFileInIncludePath("shell/{$filename}");

        $commandOutput = [];
        exec("php $fileToRun", $commandOutput, $return_var);

        $output->writeln($commandOutput);
        foreach ($output as $line) {
            $output->writeln($output);
        }

        return Command::SUCCESS;
    }

    protected static function getComposerInstallationData(): array
    {
        $packages = $packageDirectories = [];
        $installedVersions = \Composer\InstalledVersions::getAllRawData();
        foreach ($installedVersions as $datasets) {
            array_shift($datasets['versions']);
            foreach ($datasets['versions'] as $package => $version) {
                if (!isset($version['install_path'])) {
                    continue;
                }

                if (!in_array($version['type'], ['magento-source', 'magento-module'])) {
                    continue;
                }

                $packages[] = $package;
                $packageDirectories[] = realpath($version['install_path']);
            }
        }
        $packages = array_unique($packages);
        $packageDirectories = array_unique($packageDirectories);

        return [
            $packages,
            $packageDirectories
        ];
    }

    protected static function findFileInIncludePath(string $relativePath): string|false
    {
        list($packages, $packageDirectories) = self::getComposerInstallationData();
        $rootPackage = \Composer\InstalledVersions::getRootPackage();
        $baseDir = str_replace('/composer/../../', '', $rootPackage['install_path']);

        foreach ($packages as $package) {
            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $package, '', $relativePath);
        }

        // if file exists in the current folder, don't look elsewhere
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $relativePath;
        if (file_exists($fullPath)) {
            return $fullPath;
        }

        // search for the file in composer packages
        foreach ($packageDirectories as $basePath) {
            $fullPath = $basePath . DIRECTORY_SEPARATOR . $relativePath;
            if (file_exists($fullPath)) {
                return realpath($fullPath);
            }
        }

        return false;
    }
}