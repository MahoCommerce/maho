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

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Address State Provider - Fetches customer address data
 *
 * SECURITY: Customers can only access their own addresses.
 * Admins can access any customer's addresses.
 *
 * @implements ProviderInterface<Address>
 */
final class AddressProvider implements ProviderInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Provide address data based on operation type
     *
     * @return Address[]|Address|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|Address|null
    {
        StoreContext::ensureStore();

        $customerId = (int) ($uriVariables['customerId'] ?? 0);
        $addressId = (int) ($uriVariables['id'] ?? 0);

        // Check if this is a /customers/me/* route (uses authenticated customer)
        $operationName = $operation->getName() ?? '';
        $isMeRoute = str_contains($operationName, '_me_') || str_starts_with($operationName, 'get_me') || str_starts_with($operationName, 'get_my');

        // For simple /addresses/{id} routes - load address first to get customerId
        if (!$customerId && $addressId && !$isMeRoute) {
            $address = \Mage::getModel('customer/address')->load($addressId);
            if (!$address->getId()) {
                throw new NotFoundHttpException('Address not found');
            }
            $customerId = (int) $address->getCustomerId();
        }

        // For /addresses or /customers/me/addresses routes - use authenticated customer
        if (!$customerId && ($operation instanceof CollectionOperationInterface || $isMeRoute)) {
            $customerId = $this->getAuthenticatedCustomerId();
        }

        if (!$customerId) {
            throw new NotFoundHttpException('Customer ID is required');
        }

        // SECURITY: Verify the user can access this customer's addresses
        $this->authorizeCustomerAccess($customerId);

        // Load customer to verify they exist
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($customer);
        }

        return $this->getItem($customer, $addressId);
    }

    /**
     * Get a single address by ID
     */
    private function getItem(\Mage_Customer_Model_Customer $customer, int $addressId): ?Address
    {
        $address = \Mage::getModel('customer/address')->load($addressId);

        if (!$address->getId()) {
            return null;
        }

        // Verify address belongs to the customer
        if ((int) $address->getCustomerId() !== (int) $customer->getId()) {
            throw new AccessDeniedHttpException('Address does not belong to this customer');
        }

        return $this->mapToDto($address, $customer);
    }

    /**
     * Get all addresses for a customer
     *
     * @return Address[]
     */
    private function getCollection(\Mage_Customer_Model_Customer $customer): array
    {
        $addresses = [];

        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapToDto($address, $customer);
        }

        return $addresses;
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
