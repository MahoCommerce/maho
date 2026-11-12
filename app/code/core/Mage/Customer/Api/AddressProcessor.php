<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Address State Processor - Handles address mutations.
 *
 * SECURITY: Customers can only modify their own addresses.
 * An admin token or a service token can modify the addresses of a customer of a website in its allowlist.
 */
final class AddressProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Address
    {
        StoreContext::ensureStore();
        $service = $this->getService();
        $operationName = $operation->getName() ?? '';

        if ($operationName === 'create') {
            $customerId = $this->requireCustomerId();
            return $this->save($this->fromGraphQlArgs(new Address(), $context['args']['input'] ?? []), $customerId, null);
        }
        if ($operationName === 'update' || $operationName === 'delete') {
            $args = $context['args']['input'] ?? [];
            $addressId = $this->parseGraphQlId(RequestInput::of($args)->string('id'));
            if (!$addressId) {
                throw new \Mage_Core_Exception_Input()->addError('id', 'Address ID is required');
            }
            $address = $service->getById($addressId);
            $customerId = (int) $address->getCustomerId();
            $customer = $this->getCustomerService()->getById($customerId);
            $this->assertCustomerReachable($customer);
            if ($operationName === 'delete') {
                $service->delete($address);
                return null;
            }
            $dto = Address::fromCustomerAddress($address, $customer);
            return $this->save($this->fromGraphQlArgs($dto, $args), $customerId, $address);
        }

        $addressId = (int) ($uriVariables['id'] ?? 0);
        $address = $addressId ? $service->getById($addressId) : null;
        $customerId = match (true) {
            str_contains($operationName, '_me_') || str_starts_with($operationName, 'create_my') => $this->requireCustomerId(),
            isset($uriVariables['customerId']) => (int) $uriVariables['customerId'],
            $address !== null => (int) $address->getCustomerId(),
            default => $this->requireCustomerId(),
        };
        $this->assertCustomerReachable($this->getCustomerService()->getById($customerId));
        if ($address !== null) {
            $address = $service->getByCustomer($addressId, $customerId);
        }

        if ($operation instanceof Delete) {
            if ($address !== null) {
                $service->delete($address);
            }
            return null;
        }
        return $this->save($data, $customerId, $operation instanceof Post ? null : $address);
    }

    private function save(Address $data, int $customerId, ?\Mage_Customer_Model_Address $address): Address
    {
        $address ??= \Mage::getModel('customer/address');
        // The custom attributes of the address follow the forms of the customer group
        $address->setCustomerId($customerId);
        $this->populateAddressFromDto($address, $data);
        $address->setIsDefaultBilling($data->isDefaultBilling)
            ->setIsDefaultShipping($data->isDefaultShipping);
        $this->getService()->save($address);

        return Address::fromCustomerAddress($address, $this->getCustomerService()->getById($customerId));
    }

    /**
     * Normalize address data types from frontend input
     * - street: string -> array
     * - regionId: string -> int|null
     * - region code or name -> regionId, when regionId is empty
     */
    private function normalizeAddressData(Address $data): void
    {
        if (is_string($data->street)) {
            $data->street = [$data->street];
        }

        if ($data->regionId !== null && !is_int($data->regionId)) {
            $data->regionId = $data->regionId !== '' ? (int) $data->regionId : null;
        }

        if (!$data->regionId && $data->region) {
            $regionId = \Mage::getModel('directory/region')->loadByCodeOrName($data->region, $data->countryId)->getId();
            $data->regionId = $regionId ? (int) $regionId : null;
        }
    }

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

        // A new address gets the required attributes of its form, as in the address book
        if ($data->customAttributes !== null || !$address->getId()) {
            CustomAttributes::apply(
                $address,
                $this->hasBackendAccess('addresses') ? 'adminhtml_customer_address' : 'customer_address_edit',
                $data->customAttributes ?? [],
            );
        }
    }

    /**
     * Write the arguments of the GraphQL createAddress or updateAddress mutation into $dto. An argument that the
     * mutation leaves out keeps the value of $dto.
     */
    private function fromGraphQlArgs(Address $dto, array $args): Address
    {
        $input = RequestInput::of($args);
        $strings = [
            'firstName' => 'firstname', 'lastName' => 'lastname', 'city' => 'city',
            'postcode' => 'postcode', 'countryId' => 'countryId', 'telephone' => 'telephone',
        ];
        foreach ($strings as $arg => $property) {
            $value = $input->string($arg);
            if ($value !== null) {
                $dto->{$property} = $value;
            }
        }
        if (isset($args['street'])) {
            $dto->street = is_array($args['street']) ? $input->stringList('street') : $input->string('street');
        }
        foreach (['region', 'company'] as $property) {
            if (array_key_exists($property, $args)) {
                $dto->{$property} = $input->string($property);
            }
        }
        if (array_key_exists('regionId', $args)) {
            $dto->regionId = $input->int('regionId');
        }
        foreach (['isDefaultBilling', 'isDefaultShipping'] as $flag) {
            $value = $input->bool($flag);
            if ($value !== null) {
                $dto->{$flag} = $value;
            }
        }
        // Without the argument the stored values stay: apply() would check them against the form of the current group
        $dto->customAttributes = $input->array('customAttributes');
        return $dto;
    }

    private function getService(): \Mage_Customer_Service_Address
    {
        return \Mage::getService('customer/address');
    }

    private function getCustomerService(): \Mage_Customer_Service_Customer
    {
        return \Mage::getService('customer/customer');
    }
}
