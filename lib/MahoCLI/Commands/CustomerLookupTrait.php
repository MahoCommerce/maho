<?php

/**
 * Find the customers that a customer command names by email.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Customer_Model_Customer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait CustomerLookupTrait
{
    /**
     * @return list<Mage_Customer_Model_Customer>
     */
    private function findCustomersByEmail(#[\SensitiveParameter]
        string $email, ?string $websiteCode): array
    {
        $customers = Mage::getResourceModel('customer/customer_collection')
            ->addAttributeToSelect(['firstname', 'lastname', 'default_billing', 'default_shipping'])
            ->addAttributeToFilter('email', ['eq' => $email]);
        if ($websiteCode !== null) {
            $website = Mage::getModel('core/website')->load($websiteCode, 'code');
            $customers->addAttributeToFilter('website_id', (int) $website->getId());
        }
        return array_values($customers->getItems());
    }

    /**
     * Return the customers to act on. When $customers holds more than one, ask for the website in an
     * interactive terminal. Otherwise print the websites and return an empty list.
     *
     * @param list<Mage_Customer_Model_Customer> $customers
     * @return list<Mage_Customer_Model_Customer>
     */
    private function pickCustomers(InputInterface $input, SymfonyStyle $io, array $customers, bool $allowAll = false): array
    {
        if (count($customers) === 1) {
            return $customers;
        }

        $byWebsite = [];
        $choices = [];
        foreach ($customers as $customer) {
            $website = Mage::app()->getWebsite((int) $customer->getWebsiteId());
            $byWebsite[$website->getCode()] = $customer;
            $choices[$website->getCode()] = "{$website->getName()}: {$customer->getFirstname()} {$customer->getLastname()}";
        }

        if (!$input->isInteractive()) {
            $hint = $allowAll ? ' or use --all' : '';
            $io->writeln('<error>More than one customer has this email. Use --website with one of these codes' . $hint . ': ' . implode(', ', array_keys($byWebsite)) . '</error>');
            return [];
        }

        if ($allowAll) {
            $choices['all'] = 'All of them';
        }
        $code = (string) $io->choice('More than one customer has this email. Website:', $choices);
        return $code === 'all' && $allowAll ? $customers : [$byWebsite[$code]];
    }
}
