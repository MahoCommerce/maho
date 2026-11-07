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
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'admin:user:change-password',
    description: 'Change password of an admin users',
)]
class AdminUserChangepassword extends BaseMahoCommand
{
    use AdminUserCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The username of the admin user')]
        #[Ask('Username:')]
        #[\SensitiveParameter]
        string $username,
        #[Argument(description: 'The new password')]
        #[Ask('New password:', hidden: true)]
        #[\SensitiveParameter]
        string $password,
    ): int {
        $this->initMaho();

        return $this->runUserAction(
            $output,
            fn(\Mage_Admin_Service_User $service) => $service->save(
                $service->getByUsername(trim($username))->setNewPassword(trim($password)),
            ),
            "Password changed for user {$username}",
        );
    }
}
