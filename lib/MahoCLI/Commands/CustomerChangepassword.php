<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:change-password',
    description: 'Change password of a customers',
)]
class CustomerChangepassword extends BaseMahoCommand
{
    use CustomerLookupTrait;

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Argument(description: 'The new password')]
        #[Ask('New password:', hidden: true)]
        #[\SensitiveParameter]
        string $password,
        #[Option(description: 'The code of the website, when customers of more websites have the email')]
        ?string $website = null,
    ): int {
        $this->initMaho();

        $email = trim($email);
        if (!strlen($email)) {
            $io->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $password = trim($password);
        if (!strlen($password)) {
            $io->writeln('<error>New password cannot be empty</error>');
            return Command::INVALID;
        }

        $customers = $this->findCustomersByEmail($email, $website);
        if (!$customers) {
            $io->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        $customer = $this->pickCustomers($input, $io, $customers)[0] ?? null;
        if ($customer === null) {
            return Command::FAILURE;
        }

        $customer->setPassword($password);
        $customer->save();
        $io->writeln("<info>Password changed for customer $email.</info>");
        return Command::SUCCESS;
    }
}
