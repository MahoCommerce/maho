<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'customer:address:list',
    description: 'List the addresses of a customer',
)]
class CustomerAddressList extends BaseMahoCommand
{
    use CustomerLookupTrait;

    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Option(description: 'The code of the website, when customers of more websites have the email')]
        ?string $website = null,
    ): int {
        $this->initMaho();

        $customers = $this->findCustomersByEmail(trim($email), $website);
        if (!$customers) {
            $io->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        $customer = $this->pickCustomers($input, $io, $customers)[0] ?? null;
        if ($customer === null) {
            return Command::FAILURE;
        }

        $addresses = $customer->getAddresses();
        if (!$addresses) {
            $io->writeln('No addresses found.');
            return Command::SUCCESS;
        }

        $table = new Table($io);
        $table->setHeaders(['id', 'firstname', 'lastname', 'street', 'city', 'region', 'postcode', 'country_id', 'telephone', 'default_billing', 'default_shipping']);
        foreach ($addresses as $address) {
            $table->addRow([
                $address->getId(),
                $address->getFirstname(),
                $address->getLastname(),
                $address->getStreetFull(),
                $address->getCity(),
                $address->getRegion(),
                $address->getPostcode(),
                $address->getCountryId(),
                $address->getTelephone(),
                (int) $address->getId() === $customer->getDefaultBilling() ? 'yes' : 'no',
                (int) $address->getId() === $customer->getDefaultShipping() ? 'yes' : 'no',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
