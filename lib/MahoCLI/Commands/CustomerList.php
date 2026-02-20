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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'customer:list',
    description: 'List all customers',
)]
class CustomerList extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $customerAttributes = ['entity_id', 'website_id', 'email', 'firstname', 'lastname'];
        $customers = Mage::getResourceModel('customer/customer_collection');
        $customers->addAttributeToSelect($customerAttributes);
        if ($customers->getSize() == 0) {
            $output->writeln('No customers found.');
            return Command::SUCCESS;
        }

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

        return Command::SUCCESS;
    }
}
