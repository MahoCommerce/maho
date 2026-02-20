<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'admin:user:create',
    description: 'Create an admin user',
)]
class AdminUserCreate extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $role = Mage::getModel('admin/roles')
            ->load('Administrators', 'role_name');
        if (!$role->getId()) {
            $output->writeln('<error>Role "Administrators" not found</error>');
            return Command::FAILURE;
        }

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new Question('Username: ', '');
        $username = $questionHelper->ask($input, $output, $question);
        $username = trim($username);
        if (!strlen($username)) {
            $output->writeln('<error>Username cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Password: ', '');
        $password = $questionHelper->ask($input, $output, $question);
        $password = trim($password);
        if (!strlen($password)) {
            $output->writeln('<error>Password cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Email: ', '');
        $email = $questionHelper->ask($input, $output, $question);
        $email = trim($email);
        if (!strlen($email)) {
            $output->writeln('<error>Username cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Firstname: ', '');
        $firstname = $questionHelper->ask($input, $output, $question);
        $firstname = trim($firstname);
        if (!strlen($firstname)) {
            $output->writeln('<error>Firstname cannot be empty</error>');
            return Command::INVALID;
        }

        $question = new Question('Lastname: ', '');
        $lastname = $questionHelper->ask($input, $output, $question);
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
            ->setIsActive(1)
            ->save();

        $user
            ->setRoleIds([$role->getId()])
            ->setRoleUserId($user->getId())
            ->saveRelations();

        $output->writeln("<info>User {$username} created with {$role->getName()} role</info>");
        return Command::SUCCESS;
    }
}
