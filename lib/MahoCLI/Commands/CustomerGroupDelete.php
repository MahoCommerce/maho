<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Customer_Model_Resource_Group;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:group:delete',
    description: 'Delete a customer group',
)]
class CustomerGroupDelete extends BaseMahoCommand
{
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The ID of the customer group to delete', name: 'group_id')]
        int $groupId,
        #[Option(description: 'Move the customers of the group to the default group', name: 'move-customers')]
        bool $moveCustomers = false,
        #[Option(description: 'Skip the confirmation for the rows that refer to the group', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->initMaho();

        // A lookup in PHP: PostgreSQL refuses a query with an ID that is out of range for the column.
        $groupIds = array_keys(Mage::getResourceModel('customer/group_collection')->toOptionHash());
        $group = Mage::getModel('customer/group');
        if (in_array($groupId, $groupIds, true)) {
            $group->load($groupId);
        }
        if ($group->getId() === null) {
            $io->writeln("<error>Customer group not found with ID: $groupId</error>");
            return Command::FAILURE;
        }
        $code = $group->getCode();
        /** @var Mage_Customer_Model_Resource_Group $resource */
        $resource = Mage::getResourceSingleton('customer/group');
        try {
            $resource->validateDelete($group);
        } catch (Mage_Core_Exception $e) {
            $io->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToFilter('group_id', $groupId)
            ->getSize();
        if ($customers && !$moveCustomers && $input->isInteractive()) {
            $moveCustomers = $io->confirm("Customer group $code has customers: $customers. Move them to the default group?", false);
        }
        if ($customers && !$moveCustomers) {
            $io->writeln("<error>Cannot delete customer group $code, because it has customers: $customers. Use --move-customers to move them to the default group.</error>");
            return Command::FAILURE;
        }

        $rows = $resource->getReferencingRows($groupId);
        if ($rows) {
            $io->writeln("Deleting customer group $code also changes these rows, by the ON DELETE rule of their foreign key:");
            new Table($io)->setHeaders(['table', 'column', 'on_delete', 'rows'])->setRows($rows)->render();
            if (!$force && $input->isInteractive()) {
                $force = $io->confirm('Delete the group and change these rows?', false);
            }
            if (!$force) {
                $io->writeln("<error>Cannot delete customer group $code, because other rows refer to it. Use --force to delete it.</error>");
                return Command::FAILURE;
            }
        }

        try {
            $group->delete();
        } catch (Mage_Core_Exception $e) {
            $io->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $io->writeln("<info>Customer group $code deleted.</info>");
        if ($customers) {
            $io->writeln("<info>Customers moved to the default group: $customers.</info>");
        }
        return Command::SUCCESS;
    }

    #[Interact]
    public function askGroup(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('group_id') !== null) {
            return;
        }

        $this->initMaho();
        $groups = Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->toOptionHash();
        $answer = $io->choice('Customer group:', $groups);
        $input->setArgument('group_id', (string) array_search($answer, $groups, true));
    }
}
