<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
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
    name: 'admin:user:twofa-reset',
    description: 'Remove the two-factor authentication of an admin user who lost their device',
)]
class AdminUserTwofaReset extends BaseMahoCommand
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

        $user->setTwofaEnabled(false);
        $user->setTwofaSecret(null);
        $user->save();

        $output->writeln("<info>2FA removed for user {$username}</info>");
        $output->writeln('<comment>The user must enroll again from System > My Account.</comment>');

        return Command::SUCCESS;
    }
}
