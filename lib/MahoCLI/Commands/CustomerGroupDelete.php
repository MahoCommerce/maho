<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

#[AsCommand(
    name: 'customer:group:delete',
    description: 'Delete a customer group',
)]
class CustomerGroupDelete extends BaseMahoCommand
{
    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $groups = Mage::getResourceModel('customer/group_collection')->setRealGroupsFilter()->toOptionHash();
        $question = new ChoiceQuestion('Customer group: ', $groups);
        $code = $questionHelper->ask($input, $output, $question);
        $group = Mage::getModel('customer/group')->load((int) array_search($code, $groups, true));

        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToFilter('group_id', $group->getId())
            ->getSize();

        try {
            $group->delete();
        } catch (Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Customer group $code deleted. Customers moved to the default group: $customers.</info>");
        return Command::SUCCESS;
    }
}
