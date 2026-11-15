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
    name: 'admin:user:disable',
    description: 'Disable an admin user',
)]
class AdminUserDisable extends BaseMahoCommand
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

        return $this->runUserAction(
            $output,
            fn(\Mage_Admin_Service_User $service) => $service->disable($service->getByUsername(trim($username))),
            "User {$username} disabled",
        );
    }
}
