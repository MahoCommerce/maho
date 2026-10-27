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
    name: 'customer:address:create',
    description: 'Create an address for a customer',
)]
class CustomerAddressCreate extends BaseMahoCommand
{
    use CustomerFormTrait;
    use CustomerLookupTrait;

    private const FORM_CODE = 'adminhtml_customer_address';

    /**
     * The attributes that the arguments and the options of the command give.
     */
    private const KNOWN_ATTRIBUTES = ['firstname', 'lastname', 'company', 'street', 'city', 'postcode', 'country_id', 'region', 'region_id', 'telephone'];

    /**
     * @param list<string> $attribute
     */
    public function __invoke(
        InputInterface $input,
        SymfonyStyle $io,
        #[Argument(description: 'The email of the customer')]
        #[Ask('Email:')]
        #[\SensitiveParameter]
        string $email,
        #[Argument(description: 'The street')]
        #[Ask('Street:')]
        string $street,
        #[Argument(description: 'The city')]
        #[Ask('City:')]
        string $city,
        #[Argument(description: 'The zip/postal code')]
        #[Ask('Zip/postal code:')]
        string $postcode,
        #[Argument(description: 'The two-letter code of the country')]
        string $country,
        #[Argument(description: 'The telephone number')]
        #[Ask('Telephone:')]
        string $telephone,
        #[Option(description: 'The code or the name of the state/province')]
        ?string $region = null,
        #[Option(description: 'The first name (default: the first name of the customer)')]
        ?string $firstname = null,
        #[Option(description: 'The last name (default: the last name of the customer)')]
        ?string $lastname = null,
        #[Option(description: 'The company')]
        ?string $company = null,
        #[Option(description: 'The value of another address attribute, as code=value. Give the option once for each attribute')]
        array $attribute = [],
        #[Option(description: 'The code of the website, when customers of more websites have the email')]
        ?string $website = null,
        #[Option(description: 'Make it the default billing address (the first address of a customer always is)', name: 'default-billing')]
        bool $defaultBilling = false,
        #[Option(description: 'Make it the default shipping address (the first address of a customer always is)', name: 'default-shipping')]
        bool $defaultShipping = false,
    ): int {
        $this->initMaho();

        $email = trim($email);
        if (!strlen($email)) {
            $io->writeln('<error>Email cannot be empty</error>');
            return Command::INVALID;
        }

        $country = strtoupper(trim($country));
        if (!array_key_exists($country, $this->getCountries())) {
            $io->writeln("<error>Country not found with code: $country</error>");
            return Command::INVALID;
        }

        $address = Mage::getModel('customer/address');
        $form = $this->getCustomerForm(self::FORM_CODE, $address);
        try {
            $data = $this->parseAttributeValues($attribute, $form);
        } catch (\InvalidArgumentException $e) {
            $io->writeln("<error>{$e->getMessage()}</error>");
            return Command::INVALID;
        }

        $region = trim((string) $region);
        if ($region !== '') {
            $regionModel = Mage::getModel('directory/region')->loadByCodeOrName($region, $country);
            if ($regionModel->getId()) {
                $data['region_id'] = (int) $regionModel->getId();
            } elseif (Mage::getModel('directory/country')->load($country)->getRegionCollection()->getSize()) {
                $io->writeln("<error>State/province not found in country $country: $region</error>");
                return Command::INVALID;
            } else {
                $data['region'] = $region;
            }
        }

        $customers = $this->findCustomersByEmail($email, $website);
        if (!$customers) {
            $io->writeln('<error>Customer not found.</error>');
            return Command::FAILURE;
        }

        $customer = $this->pickCustomers($input, $io, $customers)[0] ?? null;
        if ($customer === null) {
            return Command::FAILURE;
        }

        $data = [
            ...$data,
            'firstname' => $firstname ?? $customer->getFirstname(),
            'lastname' => $lastname ?? $customer->getLastname(),
            'street' => [$street],
            'city' => $city,
            'postcode' => $postcode,
            'country_id' => $country,
            'telephone' => $telephone,
        ];
        if ($company !== null) {
            $data['company'] = $company;
        }

        // The storefront checks an address with its form, then with validate(), and shows both lists.
        $errors = $this->validateAndCompactData($form, $data);
        $modelErrors = $address->validate();
        if (is_array($modelErrors)) {
            $errors = array_values(array_unique([...$errors, ...$modelErrors]));
        }
        if ($errors) {
            foreach ($errors as $error) {
                $io->writeln("<error>$error</error>");
            }
            return Command::INVALID;
        }

        // The storefront makes the first address of a customer the default billing and shipping address.
        $isFirst = $customer->getAddresses() === [];
        $address->setCustomerId($customer->getId())
            ->setIsDefaultBilling($defaultBilling || $isFirst)
            ->setIsDefaultShipping($defaultShipping || $isFirst)
            ->save();

        $io->writeln("<info>Address created with ID {$address->getId()} for customer $email.</info>");
        return Command::SUCCESS;
    }

    #[Interact]
    public function askCountryAndAttributes(InputInterface $input, SymfonyStyle $io): void
    {
        $this->initMaho();

        if ($input->getArgument('country') === null) {
            $input->setArgument('country', (string) $io->choice('Country:', $this->getCountries(), Mage::helper('core')->getDefaultCountry()));
        }

        $country = strtoupper((string) $input->getArgument('country'));
        if ($input->getOption('region') === null && Mage::helper('directory')->isRegionRequired($country)) {
            $regions = [];
            foreach (Mage::getModel('directory/country')->load($country)->getRegions() as $region) {
                $regions[$region->getCode()] = $region->getName();
            }
            if ($regions) {
                $input->setOption('region', (string) $io->choice('State/province:', $regions));
            }
        }

        $this->askRequiredAttributes($input, $io, $this->getCustomerForm(self::FORM_CODE, Mage::getModel('customer/address')), self::KNOWN_ATTRIBUTES);
    }

    /**
     * @return array<string, string>
     */
    private function getCountries(): array
    {
        return Mage::getResourceModel('directory/country_collection')->loadByStore()->toOptionHash();
    }
}
