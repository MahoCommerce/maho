<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:address:delete',
    description: 'Delete an address of a customer',
)]
class CustomerAddressDelete extends BaseMahoCommand
{
    use CustomerLookupTrait;

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The ID of the address, as customer:address:list shows it', name: 'address_id')]
        int $addressId,
    ): int {
        $this->initMaho();

        $address = Mage::getModel('customer/address');
        // PostgreSQL refuses a query with an ID above the largest value of an INTEGER column.
        if ($addressId > 0 && $addressId <= 2147483647) {
            $address->load($addressId);
        }
        if (!$address->getId()) {
            $io->writeln("<error>Address not found with ID: $addressId</error>");
            return Command::FAILURE;
        }

        $customer = Mage::getModel('customer/customer')->load($address->getCustomerId());
        $address->delete();
        // The admin also removes a deleted address from the default addresses of the customer.
        foreach (['default_billing', 'default_shipping'] as $code) {
            if ((int) $customer->getData($code) === $addressId) {
                $customer->setData($code)->getResource()->saveAttribute($customer, $code);
            }
        }
        $io->writeln("<info>Address $addressId of customer {$customer->getEmail()} deleted.</info>");
        return Command::SUCCESS;
    }

    #[Interact]
    public function askAddress(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('address_id') !== null) {
            return;
        }

        $this->initMaho();
        $customers = $this->findCustomersByEmail(trim((string) $io->ask('Email:')), null);
        $customer = $customers ? $this->pickCustomers($input, $io, $customers)[0] ?? null : null;
        if ($customer === null) {
            $io->writeln('<error>Customer not found.</error>');
            return;
        }

        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[$address->getId()] = "{$address->getId()}: {$address->format('oneline')}";
        }
        if (!$addresses) {
            $io->writeln('No addresses found.');
            return;
        }

        $answer = $io->choice('Address:', $addresses);
        $input->setArgument('address_id', (string) array_search($answer, $addresses, true));
    }
}
