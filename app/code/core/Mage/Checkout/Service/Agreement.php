<?php

/**
 * Finds the terms and conditions that a customer must accept, checks them, and keeps the proof of each acceptance.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

use Mage_Checkout_Model_Agreement as Agreement;
use Mage_Checkout_Model_Agreement_Acceptance as Acceptance;
use Mage_Customer_Model_Customer as Customer;

class Mage_Checkout_Service_Agreement
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity when no agreement has $id
     */
    public function getById(int $id): Agreement
    {
        $agreement = Mage::getModel('checkout/agreement')->load($id);
        if (!$agreement->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('checkout')->__('The agreement does not exist.'));
        }
        return $agreement;
    }

    /**
     * The active agreements of $storeId that show at $location, in the order of their position. The list is empty
     * when the store does not ask for terms and conditions.
     *
     * @param string $location an Agreement::LOCATION_* value
     * @return list<Agreement>
     */
    public function getActive(int $storeId, string $location): array
    {
        if (!Mage::getStoreConfigFlag('checkout/options/enable_agreements', $storeId)) {
            return [];
        }
        $collection = Mage::getModel('checkout/agreement')->getCollection()
            ->addStoreFilter($storeId)
            ->addFieldToFilter('is_active', 1)
            ->addLocationFilter($location)
            ->setOrder('position', \Maho\Data\Collection::SORT_ORDER_ASC);
        return array_values($collection->getItems());
    }

    /**
     * Check that $agreementIds holds each agreement that $storeId requires at $location.
     *
     * @param list<int> $agreementIds
     * @throws Mage_Core_Exception_Input on the agreement_ids field
     */
    public function assertAccepted(array $agreementIds, int $storeId, string $location): void
    {
        $required = array_map(fn(Agreement $agreement): int => (int) $agreement->getId(), $this->getActive($storeId, $location));
        if (array_diff($required, $agreementIds) !== []) {
            throw new Mage_Core_Exception_Input()->addError(
                'agreement_ids',
                Mage::helper('checkout')->__('Please agree to all the terms and conditions.'),
            );
        }
    }

    /**
     * Save one acceptance of the current version of each agreement in $agreementIds. Each row takes the customer,
     * the email, the order, the store, the context and the client of $template. An ID with no agreement is skipped.
     *
     * @param list<int> $agreementIds
     * @return list<Acceptance>
     */
    public function recordAcceptances(array $agreementIds, Acceptance $template): array
    {
        $versionIds = Mage::getResourceSingleton('checkout/agreement')
            ->getCurrentVersionIds(array_values(array_unique(array_map(intval(...), $agreementIds))));
        $acceptances = [];
        foreach ($versionIds as $agreementId => $versionId) {
            $acceptance = Mage::getModel('checkout/agreement_acceptance')
                ->setData($template->getData())
                ->unsetData('acceptance_id')
                ->setAgreementId($agreementId)
                ->setVersionId($versionId);
            $acceptance->save();
            $acceptances[] = $acceptance;
        }
        return $acceptances;
    }

    /**
     * The registration agreements of $storeId that $customer did not accept. An agreement with requires_reconsent
     * needs an acceptance of its current version. Any other agreement needs an acceptance of any version.
     *
     * @return list<Agreement>
     */
    public function getPendingForCustomer(Customer $customer, int $storeId): array
    {
        $agreements = $this->getActive($storeId, Agreement::LOCATION_REGISTRATION);
        if ($agreements === [] || !$customer->getId()) {
            return $agreements;
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $resource = Mage::getSingleton('core/resource');
        $accepted = $adapter->fetchAll(
            $adapter->select()
                ->from(['acc' => $resource->getTableName('checkout/agreement_acceptance')], ['agreement_id'])
                ->join(['v' => $resource->getTableName('checkout/agreement_version')], 'v.version_id = acc.version_id', ['version'])
                ->where('acc.customer_id = ?', (int) $customer->getId()),
        );
        $versionsByAgreement = [];
        foreach ($accepted as $row) {
            $versionsByAgreement[(int) $row['agreement_id']][] = (int) $row['version'];
        }

        return array_values(array_filter($agreements, function (Agreement $agreement) use ($versionsByAgreement): bool {
            $versions = $versionsByAgreement[(int) $agreement->getId()] ?? [];
            if ($versions === []) {
                return true;
            }
            return $agreement->getRequiresReconsent() && !in_array((int) $agreement->getVersion(), $versions, true);
        }));
    }

    /**
     * Save the acceptance of the pending agreements in $agreementIds, from the account of $customer.
     *
     * @param list<int> $agreementIds
     * @return list<Acceptance>
     * @throws Mage_Core_Exception_Input when an ID is not a pending agreement of $customer
     */
    public function acceptForCustomer(Customer $customer, int $storeId, array $agreementIds, Acceptance $template): array
    {
        $pending = array_map(fn(Agreement $agreement): int => (int) $agreement->getId(), $this->getPendingForCustomer($customer, $storeId));
        $unknown = array_diff($agreementIds, $pending);
        if ($agreementIds === [] || $unknown !== []) {
            throw new Mage_Core_Exception_Input()->addError(
                'agreement_ids',
                Mage::helper('checkout')->__('Select the terms and conditions that wait for your acceptance.'),
            );
        }
        $template->setCustomerId((int) $customer->getId())
            ->setEmail($customer->getEmail())
            ->setStoreId($storeId)
            ->setContext(Acceptance::CONTEXT_ACCOUNT);
        return $this->recordAcceptances($agreementIds, $template);
    }

    /**
     * The acceptances of the customer with $customerId, or of $email when $customerId is null, newest first.
     */
    public function getAcceptances(?int $customerId, #[\SensitiveParameter]
        ?string $email = null): Mage_Checkout_Model_Resource_Agreement_Acceptance_Collection
    {
        $resource = Mage::getSingleton('core/resource');
        $collection = Mage::getResourceModel('checkout/agreement_acceptance_collection');
        $collection->getSelect()->joinLeft(
            ['v' => $resource->getTableName('checkout/agreement_version')],
            'v.version_id = main_table.version_id',
            ['version', 'name', 'checkbox_text'],
        );
        if ($customerId !== null) {
            $collection->addFieldToFilter('main_table.customer_id', $customerId);
        } else {
            $collection->getSelect()->where(
                (string) $collection->getConnection()->getCaseInsensitiveEqualsSql('main_table.email', '?'),
                (string) $email,
            );
        }
        return $collection->setOrder('main_table.created_at', \Maho\Data\Collection::SORT_ORDER_DESC);
    }
}
