<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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
    name: 'admin:user:twofa-reset',
    description: 'Remove the two-factor authentication of an admin user who lost their device',
)]
class AdminUserTwofaReset extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new Question('username: ', '');
        $username = trim((string) $questionHelper->ask($input, $output, $question));
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

        $user->setTwofaEnabled(0);
        $user->setTwofaSecret(null);
        $user->save();

        $output->writeln("<info>2FA removed for user {$username}</info>");
        $output->writeln('<comment>The user must enroll again from System > My Account.</comment>');

        return Command::SUCCESS;
    }
}
