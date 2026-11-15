<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

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
    use AdminUserCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The username of the admin user')]
        #[Ask('Username:')]
        #[\SensitiveParameter]
        string $username,
    ): int {
        $this->initMaho();

        $result = $this->runUserAction(
            $output,
            fn(\Mage_Admin_Service_User $service) => $service->resetTwofa($service->getByUsername(trim($username))),
            "2FA removed for user {$username}",
        );
        if ($result === Command::SUCCESS) {
            $output->writeln('<comment>The user must enroll again from System > My Account.</comment>');
        }
        return $result;
    }
}
