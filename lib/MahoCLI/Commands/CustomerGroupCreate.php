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
use Mage_Tax_Model_Class;
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

        $code = trim($code);
        if (!strlen($code)) {
            $output->writeln('<error>Code cannot be empty</error>');
            return Command::INVALID;
        }
        if (strlen($code) > Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH) {
            $output->writeln('<error>Code cannot be longer than ' . Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH . ' bytes</error>');
            return Command::INVALID;
        }

        if (!array_key_exists($taxClassId, $this->getTaxClasses())) {
            $output->writeln("<error>Customer tax class not found with ID: $taxClassId</error>");
            return Command::INVALID;
        }

        try {
            $group = Mage::getModel('customer/group')
                ->setCode($code)
                ->setTaxClassId($taxClassId)
                ->save();
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
        $taxClasses = $this->getTaxClasses();
        $answer = $io->choice('Tax class:', $taxClasses);
        $input->setArgument('tax_class_id', (string) array_search($answer, $taxClasses, true));
    }

    /**
     * @return array<int, string>
     */
    private function getTaxClasses(): array
    {
        return Mage::getResourceModel('tax/class_collection')
            ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
            ->toOptionHash();
    }
}
