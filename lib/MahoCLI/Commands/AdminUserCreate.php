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

        $role = Mage::getModel('admin/roles')->getCollection()
            ->addFieldToFilter('role_type', 'G')
            ->addFieldToFilter('tree_level', 1)
            ->setOrder('role_id', 'ASC')
            ->getFirstItem();
        if (!$role->getId()) {
            $output->writeln('<error>No admin role found</error>');
            return Command::FAILURE;
        }

        $username = trim($username);
        if (!strlen($username)) {
            $output->writeln('<error>Username cannot be empty</error>');
            return Command::INVALID;
        }

        $password = trim($password);
        if (!strlen($password)) {
            $output->writeln('<error>Password cannot be empty</error>');
            return Command::INVALID;
        }

        $email = trim($email);
        if (!strlen($email)) {
            $output->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $firstname = trim($firstname);
        if (!strlen($firstname)) {
            $output->writeln('<error>Firstname cannot be empty</error>');
            return Command::INVALID;
        }

        $lastname = trim($lastname);
        if (!strlen($lastname)) {
            $output->writeln('<error>Lastname cannot be empty</error>');
            return Command::INVALID;
        }

        $user = Mage::getModel('admin/user')
            ->setUsername($username)
            ->setPassword($password)
            ->setEmail($email)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setIsActive()
            ->save();

        $user
            ->setRoleIds([$role->getId()])
            ->setRoleUserId($user->getId())
            ->saveRelations();

        $output->writeln("<info>User {$username} created with {$role->getName()} role</info>");
        return Command::SUCCESS;
    }
}
