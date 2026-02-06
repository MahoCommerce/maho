<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Address State Processor - Handles address mutations
 *
 * SECURITY: Customers can only modify their own addresses.
 * Admins can modify any customer's addresses.
 *
 * @implements ProcessorInterface<Address, Address|null>
 */
final class AddressProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Process address mutations (create, update, delete)
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Address
    {
        StoreContext::ensureStore();

        $customerId = (int) ($uriVariables['customerId'] ?? 0);
        $addressId = (int) ($uriVariables['id'] ?? 0);

        // Check if this is a /customers/me/* route (uses authenticated customer)
        $operationName = $operation->getName() ?? '';
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
        $this->authorizeCustomerAccess($customerId);

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
        $this->validateAddress($data);

        $address = \Mage::getModel('customer/address');
        $address->setCustomerId($customer->getId());
        $this->populateAddressFromDto($address, $data);

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

        return $this->mapToDto($address, $customer);
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

        // Verify address belongs to the customer
        if ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new AccessDeniedHttpException('Address does not belong to this customer');
        }

        $this->validateAddress($data);
        $this->populateAddressFromDto($address, $data);

        try {
            $address->save();

            // Handle default billing/shipping updates
            $needsCustomerSave = false;

            if ($data->isDefaultBilling && $customer->getDefaultBilling() != $addressId) {
                $customer->setDefaultBilling($addressId);
                $needsCustomerSave = true;
            } elseif (!$data->isDefaultBilling && $customer->getDefaultBilling() == $addressId) {
                $customer->setDefaultBilling(null);
                $needsCustomerSave = true;
            }

            if ($data->isDefaultShipping && $customer->getDefaultShipping() != $addressId) {
                $customer->setDefaultShipping($addressId);
                $needsCustomerSave = true;
            } elseif (!$data->isDefaultShipping && $customer->getDefaultShipping() == $addressId) {
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

        return $this->mapToDto($address, $customer);
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

        // Verify address belongs to the customer
        if ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new AccessDeniedHttpException('Address does not belong to this customer');
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
     * Validate address data
     */
    private function validateAddress(Address $data): void
    {
        $errors = [];

        if (empty($data->firstName)) {
            $errors[] = 'First name is required';
        }
        if (empty($data->lastName)) {
            $errors[] = 'Last name is required';
        }
        if (empty($data->street) || (is_array($data->street) && empty(array_filter($data->street)))) {
            $errors[] = 'Street address is required';
        }
        if (empty($data->city)) {
            $errors[] = 'City is required';
        }
        if (empty($data->postcode)) {
            $errors[] = 'Postcode is required';
        }
        if (empty($data->countryId)) {
            $errors[] = 'Country is required';
        }
        if (empty($data->telephone)) {
            $errors[] = 'Telephone is required';
        }

        if (!empty($errors)) {
            throw new BadRequestHttpException(implode(', ', $errors));
        }
    }

    /**
     * Populate Maho address model from Address DTO
     */
    private function populateAddressFromDto(\Mage_Customer_Model_Address $address, Address $data): void
    {
        $address->setFirstname($data->firstName);
        $address->setLastname($data->lastName);
        $address->setCompany($data->company);
        $address->setStreet($data->street);
        $address->setCity($data->city);
        $address->setRegion($data->region);
        $address->setRegionId($data->regionId);
        $address->setPostcode($data->postcode);
        $address->setCountryId($data->countryId);
        $address->setTelephone($data->telephone);
    }

    // TODO: Extract address mapping to a shared AddressMapper service to eliminate duplication across AuthController, AddressProcessor, AddressProvider, CustomerProvider, OrderProvider
    /**
     * Map Maho address model to Address DTO
     */
    private function mapToDto(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->customerId = (int) $address->getCustomerId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';
        $dto->isDefaultBilling = $address->getId() == $customer->getDefaultBilling();
        $dto->isDefaultShipping = $address->getId() == $customer->getDefaultShipping();

        return $dto;
    }
}
