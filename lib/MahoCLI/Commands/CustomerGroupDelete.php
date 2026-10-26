<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Customer_Model_Group;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'customer:group:delete',
    description: 'Delete a customer group',
)]
class CustomerGroupDelete extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The ID of the customer group to delete', name: 'group_id')]
        int $groupId,
        #[Option(description: 'Move the customers of the group to the default group', name: 'move-customers')]
        bool $moveCustomers = false,
    ): int {
        $this->initMaho();

        $group = Mage::getModel('customer/group')->load($groupId);
        if ($group->getId() === null) {
            $output->writeln("<error>Customer group not found with ID: $groupId</error>");
            return Command::FAILURE;
        }
        $code = $group->getCode();
        if ($groupId === Mage_Customer_Model_Group::NOT_LOGGED_IN_ID) {
            $output->writeln("<error>Cannot delete customer group $code, because the store uses it for guests.</error>");
            return Command::FAILURE;
        }
        if ($group->usesAsDefault()) {
            $output->writeln("<error>Cannot delete customer group $code, because it is the Default Group of a scope.</error>");
            return Command::FAILURE;
        }

        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToFilter('group_id', $groupId)
            ->getSize();
        if ($customers && !$moveCustomers) {
            $output->writeln("<error>Cannot delete customer group $code, because it has customers: $customers. Use --move-customers to move them to the default group.</error>");
            return Command::FAILURE;
        }

        try {
            $group->delete();
        } catch (Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer group $code deleted.</info>");
        if ($customers) {
            $output->writeln("<info>Customers moved to the default group: $customers.</info>");
        }
        return Command::SUCCESS;
    }
}
