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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'customer:delete',
    description: 'Delete customers',
)]
class CustomerDelete extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var QuestionHelper $questionHelper */
        $questionHelper = $this->getHelper('question');

        $question = new Question('email: ', '');
        $email = $questionHelper->ask($input, $output, $question);
        $email = trim($email);
        if (!strlen($email)) {
            $output->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $customerAttributes = ['entity_id', 'website_id', 'email', 'firstname', 'lastname'];
        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToSelect($customerAttributes)
            ->addAttributeToFilter('email', ['eq' => $email]);

        if ($customers->getSize() == 0) {
            $output->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        if ($customers->getSize() == 1) {
            foreach ($customers as $customer) {
                $customer->delete();
                $output->writeln("<info>Customer $email deleted.</info>");
                return Command::SUCCESS;
            }
        }

        $output->writeln('');
        $output->writeln('<comment>More than one customer matches your search:</comment>');
        $table = new Table($output);
        $table->setHeaders($customerAttributes);

        foreach ($customers as $customer) {
            $table->addRow([
                $customer->getEntityId(),
                $customer->getWebsiteId(),
                $customer->getEmail(),
                $customer->getFirstname(),
                $customer->getLastname(),
            ]);
        }
        $table->render();

        $question = new Question('Type the ID of the customer you wish to delete (or "A" to delete all): ', '');
        $customerId = $questionHelper->ask($input, $output, $question);
        $customerId = trim($customerId);

        if ($customerId === 'A') {
            foreach ($customers as $customer) {
                $customer->delete();
            }
            $output->writeln('<info>Customers deleted.</info>');
            return Command::SUCCESS;
        }

        $customer = Mage::getModel('customer/customer')
            ->load($customerId);
        if (!$customer->getId()) {
            $output->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        $customer->delete();
        $output->writeln("<info>Customer $email deleted.</info>");
        return Command::SUCCESS;
    }
}
