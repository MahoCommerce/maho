<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Address State Provider - Fetches customer address data
 *
 * SECURITY: Customers can only access their own addresses.
 * Admins can access any customer's addresses.
 */
final class AddressProvider extends \Maho\ApiPlatform\Provider
{
    public function __construct(\Symfony\Bundle\SecurityBundle\Security $security)
    {
        parent::__construct($security);
    }

    /**
     * Provide address data based on operation type
     *
     * @return TraversablePaginator<Address>|Address|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Address|null
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName() ?? '';

        // Handle GraphQL operations
        if ($operationName === 'myAddresses') {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new NotFoundHttpException('Authentication required');
            }
            $this->authorizeCustomerAccess($customerId);
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new NotFoundHttpException('Customer not found');
            }
            return $this->getCollection($customer);
        }

        if ($operationName === 'address') {
            $addressId = (int) ($context['args']['id'] ?? $uriVariables['id'] ?? 0);
            if (!$addressId) {
                throw new NotFoundHttpException('Address ID is required');
            }
            $address = \Mage::getModel('customer/address')->load($addressId);
            if (!$address->getId()) {
                return null;
            }
            $customerId = (int) $address->getCustomerId();
            $this->authorizeCustomerAccess($customerId);
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            return $this->mapToDto($address, $customer);
        }

        $customerId = (int) ($uriVariables['customerId'] ?? 0);
        $addressId = (int) ($uriVariables['id'] ?? 0);

        // Check if this is a /customers/me/* route (uses authenticated customer)
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
     * @return TraversablePaginator<Address>
     */
    private function getCollection(\Mage_Customer_Model_Customer $customer): TraversablePaginator
    {
        $addresses = [];

        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapToDto($address, $customer);
        }

        $total = count($addresses);

        return new TraversablePaginator(new \ArrayIterator($addresses), 1, max($total, 50), $total);
    }

    /**
     * Map Maho address model to Address DTO
     */
    public function mapToDto(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer): Address
    {
        return Address::fromCustomerAddress($address, $customer);
    }
}
