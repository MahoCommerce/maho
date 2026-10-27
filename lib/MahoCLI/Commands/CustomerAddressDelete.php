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
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Attribute\Option;
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
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Argument(description: 'The ID of the address, as customer:address:list shows it', name: 'address_id')]
        int $addressId,
        #[Option(description: 'The code of the website, when customers of more websites have the email')]
        ?string $website = null,
    ): int {
        $this->initMaho();

        $email = trim($email);
        $customers = $this->findCustomersByEmail($email, $website);
        if (!$customers) {
            $io->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        $customer = $this->pickCustomers($input, $io, $customers)[0] ?? null;
        if ($customer === null) {
            return Command::FAILURE;
        }

        // The storefront also deletes only an address of the customer that it names.
        $address = array_find($customer->getAddresses(), fn($address) => (int) $address->getId() === $addressId);
        if ($address === null) {
            $io->writeln("<error>Customer $email has no address with ID: $addressId</error>");
            return Command::FAILURE;
        }

        $address->delete();
        // The admin also removes a deleted address from the default addresses of the customer.
        foreach (['default_billing', 'default_shipping'] as $code) {
            if ((int) $customer->getData($code) === $addressId) {
                $customer->setData($code)->getResource()->saveAttribute($customer, $code);
            }
        }
        $io->writeln("<info>Address $addressId of customer $email deleted.</info>");
        return Command::SUCCESS;
    }

    #[Interact]
    public function askAddress(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('address_id') !== null) {
            return;
        }

        $this->initMaho();
        $customers = $this->findCustomersByEmail(trim((string) $input->getArgument('email')), $input->getOption('website'));
        $customer = $customers ? $this->pickCustomers($input, $io, $customers)[0] ?? null : null;
        if ($customer === null) {
            return;
        }
        $input->setOption('website', Mage::app()->getWebsite((int) $customer->getWebsiteId())->getCode());

        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[$address->getId()] = "{$address->getId()}: {$address->format('oneline')}";
        }
        if ($addresses) {
            $answer = $io->choice('Address:', $addresses);
            $input->setArgument('address_id', (string) array_search($answer, $addresses, true));
        }
    }
}
