<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'admin:user:list',
    description: 'List all admin users'
)]
class AdminUserList extends BaseMahoCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $userAttributes = ['id', 'username', 'email', 'status'];
        $users = Mage::getResourceModel('admin/user_collection');
        if ($users->getSize() == 0) {
            $output->writeln('No users found.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders($userAttributes);

        foreach ($users as $user) {
            $table->addRow([
                $user->getUserId(),
                $user->getUsername(),
                $user->getEmail(),
                $user->getIsActive() ? 'active' : 'inactive'
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
