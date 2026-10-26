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
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'customer:group:create',
    description: 'Create a customer group',
)]
class CustomerGroupCreate extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The code of the customer group', name: 'code')]
        string $code,
        #[Option(description: 'The ID of the customer tax class (default: the tax class of the default group)', name: 'tax-class')]
        ?int $taxClassId = null,
    ): int {
        $this->initMaho();

        $code = trim($code);
        if (!strlen($code)) {
            $output->writeln('<error>Code cannot be empty</error>');
            return Command::INVALID;
        }

        $taxClassId ??= (int) Mage::getModel('customer/group')->getTaxClassId(Mage::helper('customer')->getDefaultCustomerGroupId());
        $taxClass = Mage::getModel('tax/class')->load($taxClassId);
        if ($taxClass->getId() === null || $taxClass->getClassType() !== Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER) {
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
}
