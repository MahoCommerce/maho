<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Address State Processor - Handles address mutations.
 *
 * SECURITY: Customers can only modify their own addresses.
 * Admins can modify any customer's addresses.
 */
final class AddressProcessor extends \Maho\ApiPlatform\Processor
{
    public function __construct(Security $security)
    {
        parent::__construct($security);
    }

    /**
     * Process address mutations (create, update, delete)
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Address
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName() ?? '';

        // Handle GraphQL mutations
        if ($operationName === 'create') {
            return $this->handleGraphQlCreate($context);
        }
        if ($operationName === 'update') {
            return $this->handleGraphQlUpdate($context);
        }
        if ($operationName === 'delete') {
            $this->handleGraphQlDelete($context);
            return null;
        }

        // REST handling below
        $customerId = (int) ($uriVariables['customerId'] ?? 0);
        $addressId = (int) ($uriVariables['id'] ?? 0);

        // Check if this is a /customers/me/* route (uses authenticated customer)
        $isMeRoute = str_contains($operationName, '_me_') || str_starts_with($operationName, 'create_me') || str_starts_with($operationName, 'update_me') || str_starts_with($operationName, 'delete_me') || str_starts_with($operationName, 'create_my');

        // For /customers/me/* routes - always use authenticated customer
        if ($isMeRoute) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new NotFoundHttpException('Authentication required');
            }
        }

        // For PUT/DELETE on /addresses/{id} routes - load address first to get customerId
        if (!$customerId && $addressId && ($operation instanceof Put || $operation instanceof Delete)) {
            $address = \Mage::getModel('customer/address')->load($addressId);
            if (!$address->getId()) {
                throw new NotFoundHttpException('Address not found');
            }
            $customerId = (int) $address->getCustomerId();
        }

        // For POST on /addresses routes without customerId - use authenticated customer
        if (!$customerId && $operation instanceof Post) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new NotFoundHttpException('Customer ID is required');
            }
        }

        if (!$customerId) {
            throw new NotFoundHttpException('Customer ID is required');
        }

        // SECURITY: Verify the user can modify this customer's addresses
        $this->assertCustomerAccess($customerId);

        // Load and verify customer exists
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        if ($operation instanceof Post) {
            return $this->createAddress($data, $customer);
        }

        if ($operation instanceof Put) {
            return $this->updateAddress($data, $customer, $addressId);
        }

        if ($operation instanceof Delete) {
            $this->deleteAddress($customer, $addressId);
            return null;
        }

        return $data instanceof Address ? $data : new Address();
    }

    /**
     * Create a new address for a customer
     */
    private function createAddress(Address $data, \Mage_Customer_Model_Customer $customer): Address
    {
        $address = \Mage::getModel('customer/address');
        $address->setCustomerId($customer->getId());
        $this->populateAddressFromDto($address, $data);
        $this->validateAddress($address);

        try {
            $address->save();

            // Handle default billing/shipping
            if ($data->isDefaultBilling) {
                $customer->setDefaultBilling($address->getId());
            }
            if ($data->isDefaultShipping) {
                $customer->setDefaultShipping($address->getId());
            }
            if ($data->isDefaultBilling || $data->isDefaultShipping) {
                $customer->save();
            }
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new BadRequestHttpException('Failed to create address');
        }

        return Address::fromCustomerAddress($address, $customer);
    }

    /**
     * Update an existing address
     */
    private function updateAddress(Address $data, \Mage_Customer_Model_Customer $customer, int $addressId): Address
    {
        $address = \Mage::getModel('customer/address')->load($addressId);

        if (!$address->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        // Another customer's address is reported exactly like a missing one: a
        // 403 here and a 404 there would make the endpoint an address-id oracle.
        if ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        $this->populateAddressFromDto($address, $data);
        $this->validateAddress($address);

        try {
            $address->save();

            // Handle default billing/shipping updates
            $needsCustomerSave = false;

            // Only touch the customer's default pointers when the flag was
            // explicitly provided. A null value means the field was omitted from
            // a partial update and must not silently clear an existing default.
            if ($data->isDefaultBilling === true && $customer->getDefaultBilling() != $addressId) {
                $customer->setDefaultBilling($addressId);
                $needsCustomerSave = true;
            } elseif ($data->isDefaultBilling === false && $customer->getDefaultBilling() == $addressId) {
                $customer->setDefaultBilling(null);
                $needsCustomerSave = true;
            }

            if ($data->isDefaultShipping === true && $customer->getDefaultShipping() != $addressId) {
                $customer->setDefaultShipping($addressId);
                $needsCustomerSave = true;
            } elseif ($data->isDefaultShipping === false && $customer->getDefaultShipping() == $addressId) {
                $customer->setDefaultShipping(null);
                $needsCustomerSave = true;
            }

            if ($needsCustomerSave) {
                $customer->save();
            }
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new BadRequestHttpException('Failed to update address');
        }

        // Reload customer to get updated defaults
        $customer = \Mage::getModel('customer/customer')->load($customer->getId());

        return Address::fromCustomerAddress($address, $customer);
    }

    /**
     * Delete an address
     */
    private function deleteAddress(\Mage_Customer_Model_Customer $customer, int $addressId): void
    {
        $address = \Mage::getModel('customer/address')->load($addressId);

        if (!$address->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        // Another customer's address is reported exactly like a missing one: a
        // 403 here and a 404 there would make the endpoint an address-id oracle.
        if ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        try {
            // Clear default references if this was a default address
            $needsCustomerSave = false;
            if ($customer->getDefaultBilling() == $addressId) {
                $customer->setDefaultBilling(null);
                $needsCustomerSave = true;
            }
            if ($customer->getDefaultShipping() == $addressId) {
                $customer->setDefaultShipping(null);
                $needsCustomerSave = true;
            }
            if ($needsCustomerSave) {
                $customer->save();
            }

            $address->delete();
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new BadRequestHttpException('Failed to delete address');
        }
    }

    /**
     * Normalize address data types from frontend input
     * - street: string -> array
     * - regionId: string -> int|null
     * - region code or name -> regionId, when regionId is empty
     */
    private function normalizeAddressData(Address $data): void
    {
        // Normalize street to array
        if (is_string($data->street)) {
            $data->street = [$data->street];
        }

        // Normalize regionId to int or null
        if ($data->regionId !== null && !is_int($data->regionId)) {
            $data->regionId = $data->regionId !== '' ? (int) $data->regionId : null;
        }

        if (!$data->regionId && $data->region) {
            $regionId = \Mage::getModel('directory/region')->loadByCodeOrName($data->region, $data->countryId)->getId();
            $data->regionId = $regionId ? (int) $regionId : null;
        }
    }

    private function validateAddress(\Mage_Customer_Model_Address $address): void
    {
        $errors = $address->validate();
        if ($errors !== true) {
            throw new BadRequestHttpException(implode(' ', $errors));
        }
    }

    /**
     * Populate Maho address model from Address DTO
     */
    private function populateAddressFromDto(\Mage_Customer_Model_Address $address, Address $data): void
    {
        $this->normalizeAddressData($data);
        $address->setFirstname($data->firstname);
        $address->setLastname($data->lastname);
        $address->setCompany($data->company);
        $address->setStreet($data->street);
        $address->setCity($data->city);
        $address->setRegion($data->region);
        $address->setRegionId($data->regionId);
        $address->setPostcode($data->postcode);
        $address->setCountryId($data->countryId);
        $address->setTelephone($data->telephone);

        // Optional attributes: null means untouched (partial update), '' clears
        $optional = [
            'prefix' => $data->prefix,
            'middlename' => $data->middlename,
            'suffix' => $data->suffix,
            'fax' => $data->fax,
            'vat_id' => $data->vatId,
        ];
        foreach ($optional as $field => $value) {
            if ($value !== null) {
                $address->setData($field, $value === '' ? null : $value);
            }
        }
    }

    /**
     * Handle GraphQL createAddress mutation
     */
    private function handleGraphQlCreate(array $context): Address
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if (!$customerId) {
            throw new NotFoundHttpException('Authentication required');
        }
        $this->assertCustomerAccess($customerId);
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        // Build Address DTO from GraphQL args
        $args = $context['args']['input'] ?? [];
        $addressDto = new Address();
        $addressDto->firstname = $args['firstName'] ?? '';
        $addressDto->lastname = $args['lastName'] ?? '';
        $addressDto->street = $args['street'] ?? [];
        $addressDto->city = $args['city'] ?? '';
        $addressDto->region = $args['region'] ?? null;
        $addressDto->regionId = isset($args['regionId']) ? (int) $args['regionId'] : null;
        $addressDto->postcode = $args['postcode'] ?? '';
        $addressDto->countryId = $args['countryId'] ?? '';
        $addressDto->telephone = $args['telephone'] ?? '';
        $addressDto->company = $args['company'] ?? null;
        $addressDto->isDefaultBilling = $args['isDefaultBilling'] ?? false;
        $addressDto->isDefaultShipping = $args['isDefaultShipping'] ?? false;

        return $this->createAddress($addressDto, $customer);
    }

    /**
     * Handle GraphQL updateAddress mutation
     */
    private function handleGraphQlUpdate(array $context): Address
    {
        $args = $context['args']['input'] ?? [];
        $addressId = (int) ($args['id'] ?? 0);

        if (!$addressId) {
            throw new BadRequestHttpException('Address ID is required');
        }

        // Load address to get customerId
        $existingAddress = \Mage::getModel('customer/address')->load($addressId);
        if (!$existingAddress->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        $customerId = (int) $existingAddress->getCustomerId();
        $this->assertCustomerAccess($customerId);
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        // Build Address DTO from existing data + GraphQL args (partial update)
        $addressDto = Address::fromCustomerAddress($existingAddress, $customer);
        if (isset($args['firstName'])) {
            $addressDto->firstname = $args['firstName'];
        }
        if (isset($args['lastName'])) {
            $addressDto->lastname = $args['lastName'];
        }
        if (isset($args['street'])) {
            $addressDto->street = $args['street'];
        }
        if (isset($args['city'])) {
            $addressDto->city = $args['city'];
        }
        if (array_key_exists('region', $args)) {
            $addressDto->region = $args['region'];
        }
        if (array_key_exists('regionId', $args)) {
            $addressDto->regionId = isset($args['regionId']) ? (int) $args['regionId'] : null;
        }
        if (isset($args['postcode'])) {
            $addressDto->postcode = $args['postcode'];
        }
        if (isset($args['countryId'])) {
            $addressDto->countryId = $args['countryId'];
        }
        if (isset($args['telephone'])) {
            $addressDto->telephone = $args['telephone'];
        }
        if (array_key_exists('company', $args)) {
            $addressDto->company = $args['company'];
        }
        if (isset($args['isDefaultBilling'])) {
            $addressDto->isDefaultBilling = (bool) $args['isDefaultBilling'];
        }
        if (isset($args['isDefaultShipping'])) {
            $addressDto->isDefaultShipping = (bool) $args['isDefaultShipping'];
        }

        return $this->updateAddress($addressDto, $customer, $addressId);
    }

    /**
     * Handle GraphQL deleteAddress mutation
     */
    private function handleGraphQlDelete(array $context): void
    {
        $args = $context['args']['input'] ?? [];
        $addressId = (int) ($args['id'] ?? 0);

        if (!$addressId) {
            throw new BadRequestHttpException('Address ID is required');
        }

        $existingAddress = \Mage::getModel('customer/address')->load($addressId);
        if (!$existingAddress->getId()) {
            throw new NotFoundHttpException('Address not found');
        }

        $customerId = (int) $existingAddress->getCustomerId();
        $this->assertCustomerAccess($customerId);
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        $this->deleteAddress($customer, $addressId);
    }

}
