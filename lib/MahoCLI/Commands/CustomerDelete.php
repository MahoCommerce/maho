<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:delete',
    description: 'Delete customers',
)]
class CustomerDelete extends BaseMahoCommand
{
    use CustomerLookupTrait;

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Option(description: 'The code of the website, when customers of more websites have the email')]
        ?string $website = null,
        #[Option(description: 'Delete every customer that has the email')]
        bool $all = false,
    ): int {
        $this->initMaho();

        $email = trim($email);
        if (!strlen($email)) {
            $io->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $customers = $this->findCustomersByEmail($email, $website);
        if (!$customers) {
            $io->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        if (!$all) {
            $customers = $this->pickCustomers($input, $io, $customers, true);
            if (!$customers) {
                return Command::FAILURE;
            }
        }

        foreach ($customers as $customer) {
            $customer->delete();
        }
        $io->writeln(count($customers) === 1 ? "<info>Customer $email deleted.</info>" : '<info>Customers deleted.</info>');
        return Command::SUCCESS;
    }
}
