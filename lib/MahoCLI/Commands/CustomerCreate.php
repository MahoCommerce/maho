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
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:create',
    description: 'Create a customer',
)]
class CustomerCreate extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Argument(description: 'The password of the customer')]
        #[Ask('Password:', hidden: true)]
        #[\SensitiveParameter]
        string $password,
        #[Argument(description: 'The first name of the customer')]
        #[Ask('Firstname:')]
        string $firstname,
        #[Argument(description: 'The last name of the customer')]
        #[Ask('Lastname:')]
        string $lastname,
        #[Argument(description: 'The code of the website (default: the default website)')]
        ?string $website = null,
        #[Argument(description: 'The ID of the customer group (default: the default group of the website)')]
        ?int $group = null,
    ): int {
        $this->initMaho();

        $email = trim($email);
        if (!strlen($email)) {
            $output->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $password = trim($password);
        if (!strlen($password)) {
            $output->writeln('<error>Password cannot be empty</error>');
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

        $websiteModel = Mage::getModel('core/website')->load($website ?? $this->getDefaultWebsiteCode(), 'code');
        if (!$websiteModel->getId()) {
            $output->writeln('<error>Website not found</error>');
            return Command::INVALID;
        }

        $group ??= $this->getDefaultGroupId($websiteModel->getCode());
        if (!array_key_exists($group, $this->getGroups())) {
            $output->writeln("<error>Customer group not found with ID: $group</error>");
            return Command::INVALID;
        }

        $customer = Mage::getModel('customer/customer')
            ->setEmail($email)
            ->setPassword($password)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setWebsiteId((int) $websiteModel->getId())
            ->setGroupId($group)
            ->save();

        if (!$customer->getId()) {
            $output->writeln('<error>Unable to create customer</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer $email created successfully.</info>");
        return Command::SUCCESS;
    }

    #[Interact]
    public function askWebsiteAndGroup(InputInterface $input, SymfonyStyle $io): void
    {
        $this->initMaho();

        if ($input->getArgument('website') === null) {
            $websites = [];
            foreach (Mage::getResourceModel('core/website_collection') as $website) {
                $websites[$website->getCode()] = $website->getName();
            }
            $input->setArgument('website', (string) $io->choice('Website:', $websites, $this->getDefaultWebsiteCode()));
        }

        if ($input->getArgument('group') === null) {
            $groups = $this->getGroups();
            $answer = $io->choice('Customer group:', $groups, $this->getDefaultGroupId((string) $input->getArgument('website')));
            $input->setArgument('group', (string) array_search($answer, $groups, true));
        }
    }

    private function getDefaultWebsiteCode(): string
    {
        return (string) Mage::app()->getDefaultStoreView()->getWebsite()->getCode();
    }

    private function getDefaultGroupId(string $websiteCode): int
    {
        $website = Mage::getModel('core/website')->load($websiteCode, 'code');
        return Mage::helper('customer')->getDefaultCustomerGroupId($website->getDefaultStore());
    }

    /**
     * @return array<int, string>
     */
    private function getGroups(): array
    {
        return Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->toOptionHash();
    }
}
