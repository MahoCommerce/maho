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
    name: 'admin:user:create',
    description: 'Create an admin user',
)]
class AdminUserCreate extends BaseMahoCommand
{
    use AdminUserCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The username of the admin user')]
        #[Ask('Username:')]
        #[\SensitiveParameter]
        string $username,
        #[Argument(description: 'The password of the admin user')]
        #[Ask('Password:', hidden: true)]
        #[\SensitiveParameter]
        string $password,
        #[Argument(description: 'The email of the admin user')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Argument(description: 'The first name of the admin user')]
        #[Ask('Firstname:')]
        string $firstname,
        #[Argument(description: 'The last name of the admin user')]
        #[Ask('Lastname:')]
        string $lastname,
    ): int {
        $this->initMaho();

        // A new user gets the first top-level role, which an install names Administrators
        $roleId = Mage::getService('admin/user')->getDefaultRoleId();
        if ($roleId === null) {
            $output->writeln('<error>No admin role found</error>');
            return Command::FAILURE;
        }
        $role = Mage::getModel('admin/roles')->load($roleId);

        $user = Mage::getModel('admin/user')
            ->setUsername(trim($username))
            ->setPassword(trim($password))
            ->setEmail(trim($email))
            ->setFirstname(trim($firstname))
            ->setLastname(trim($lastname))
            ->setIsActive();

        return $this->runUserAction(
            $output,
            fn(\Mage_Admin_Service_User $service) => $service->save($user, $roleId),
            "User {$user->getUsername()} created with {$role->getName()} role",
        );
    }
}
