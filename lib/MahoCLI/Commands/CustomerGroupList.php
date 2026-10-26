<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'customer:group:list',
    description: 'List all customer groups',
)]
class CustomerGroupList extends BaseMahoCommand
{
    public function __invoke(OutputInterface $output): int
    {
        $this->initMaho();

        $groups = Mage::getResourceModel('customer/group_collection')
            ->addTaxClass()
            ->setOrder('customer_group_id', 'ASC');

        $table = new Table($output);
        $table->setHeaders(['id', 'code', 'tax_class']);
        foreach ($groups as $group) {
            $table->addRow([
                $group->getId(),
                $group->getCode(),
                $group->getData('class_name'),
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
