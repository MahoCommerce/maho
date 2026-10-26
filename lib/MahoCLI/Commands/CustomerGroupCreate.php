<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Mage_Tax_Model_Class;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'customer:group:create',
    description: 'Create a customer group',
)]
class CustomerGroupCreate extends BaseMahoCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new Question('Code: ', '');
        $code = $questionHelper->ask($input, $output, $question);
        $code = trim($code);
        if (!strlen($code)) {
            $output->writeln('<error>Code cannot be empty</error>');
            return Command::INVALID;
        }

        $taxClasses = Mage::getResourceModel('tax/class_collection')
            ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
            ->toOptionHash();
        if (!$taxClasses) {
            $output->writeln('<error>No customer tax class found</error>');
            return Command::FAILURE;
        }
        $question = new ChoiceQuestion('Tax class: ', $taxClasses);
        $taxClass = $questionHelper->ask($input, $output, $question);

        try {
            $group = Mage::getModel('customer/group')
                ->setCode($code)
                ->setTaxClassId((int) array_search($taxClass, $taxClasses, true))
                ->save();
        } catch (Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer group {$group->getCode()} created with ID {$group->getId()}.</info>");
        return Command::SUCCESS;
    }
}
