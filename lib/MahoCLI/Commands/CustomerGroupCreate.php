<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Core_Exception_Input;
use Mage_Customer_Model_Group;
use Mage_Customer_Service_Group;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:group:create',
    description: 'Create a customer group',
)]
class CustomerGroupCreate extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The code of the customer group')]
        #[Ask('Code:')]
        string $code,
        #[Argument(description: 'The ID of the customer tax class', name: 'tax_class_id')]
        int $taxClassId,
    ): int {
        $this->initMaho();

        /** @var Mage_Customer_Model_Group $group */
        $group = Mage::getModel('customer/group');
        $group->setCode($code)->setTaxClassId($taxClassId);
        try {
            $this->groupService()->save($group);
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $output->writeln('<error>' . $error['message'] . '</error>');
            }
            return Command::INVALID;
        } catch (Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer group {$group->getCode()} created with ID {$group->getId()}.</info>");
        return Command::SUCCESS;
    }

    #[Interact]
    public function askTaxClass(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('tax_class_id') !== null) {
            return;
        }

        $this->initMaho();
        $taxClasses = $this->groupService()->getTaxClasses();
        $answer = $io->choice('Tax class:', $taxClasses);
        $input->setArgument('tax_class_id', (string) array_search($answer, $taxClasses, true));
    }

    private function groupService(): Mage_Customer_Service_Group
    {
        return Mage::getService('customer/group');
    }
}
