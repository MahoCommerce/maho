<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use MahoCLI\Helper\ServiceScaffold;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:service:create',
    description: 'Create a service class in a module, and declare its class group in the config.xml of the module',
)]
class CreateService extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Module name (e.g., Mage_Contacts)')]
        #[Ask('Module name (e.g., Mage_Contacts)')]
        string $module,
        #[Argument(description: 'Noun of the service in StudlyCase, with _ for a subfolder (e.g., Message or Product_Option)')]
        #[Ask('Noun of the service (e.g., Message or Product_Option)')]
        string $noun,
        #[Option(description: 'Alias of the model that the service loads, saves and deletes (e.g., contacts/message)', name: 'model', shortcut: 'm')]
        ?string $modelAlias = null,
        #[Option(description: 'Write the service class again when it exists', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->initMaho();

        try {
            $service = new ServiceScaffold($module, $noun, $modelAlias);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        try {
            $service->write($force);
            $io->writeln("  <info>created</info> {$service->file}");
            if ($service->declareGroup()) {
                $io->writeln("  <info>updated</info> {$service->configFile}");
            }
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success("Get the service with Mage::getService('{$service->alias}').");
        $next = ['./maho cache:flush   (loads the new <services> entry)'];
        if ($service->modelClass !== null) {
            $next[] = "Add \"{$service->notFoundMessage}\" to the locale CSV file of {$module} in app/locale/en_US/.";
        }
        $io->listing($next);

        return Command::SUCCESS;
    }
}
