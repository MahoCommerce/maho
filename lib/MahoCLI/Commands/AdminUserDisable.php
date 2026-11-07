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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'admin:user:disable',
    description: 'Enable an admin user',
)]
class AdminUserDisable extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The username of the admin user')]
        #[Ask('Username:')]
        #[\SensitiveParameter]
        string $username,
    ): int {
        $this->initMaho();

        $username = trim($username);
        if (!strlen($username)) {
            $output->writeln('<error>Username cannot be empty</error>');
            return Command::INVALID;
        }

        $user = Mage::getModel('admin/user')
            ->loadByUsername($username);
        if (!$user->getUserId()) {
            $output->writeln('<error>User not found</error>');
            return Command::FAILURE;
        }

        $user->setIsActive(false);
        $user->save();
        $output->writeln("<info>User {$username} disabled</info>");

        return Command::SUCCESS;
    }
}
