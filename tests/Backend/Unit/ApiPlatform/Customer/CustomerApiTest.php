<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Customer DTO', function (): void {
    it('has correct default values for all properties', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\Customer();

        expect($dto->id)->toBeNull();
        expect($dto->email)->toBe('');
        expect($dto->firstName)->toBeNull();
        expect($dto->lastName)->toBeNull();
        expect($dto->fullName)->toBeNull();
        expect($dto->isSubscribed)->toBeFalse();
        expect($dto->groupId)->toBe(1);
        expect($dto->defaultBillingAddress)->toBeNull();
        expect($dto->defaultShippingAddress)->toBeNull();
        expect($dto->addresses)->toBe([]);
        expect($dto->createdAt)->toBeNull();
        expect($dto->updatedAt)->toBeNull();
        expect($dto->password)->toBeNull();
        expect($dto->currentPassword)->toBeNull();
        expect($dto->newPassword)->toBeNull();
    });
});

describe('Customer DTO - property assignment', function (): void {
    it('accepts and returns all scalar property values', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\Customer();
        $dto->id = 123;
        $dto->email = 'customer@example.com';
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->fullName = 'John Doe';
        $dto->isSubscribed = true;
        $dto->groupId = 2;
        $dto->createdAt = '2025-01-15 10:00:00';
        $dto->updatedAt = '2025-01-16 11:30:00';
        $dto->password = 'secret123';
        $dto->currentPassword = 'oldpassword';
        $dto->newPassword = 'newpassword';

        expect($dto->id)->toBe(123);
        expect($dto->email)->toBe('customer@example.com');
        expect($dto->firstName)->toBe('John');
        expect($dto->lastName)->toBe('Doe');
        expect($dto->fullName)->toBe('John Doe');
        expect($dto->isSubscribed)->toBeTrue();
        expect($dto->groupId)->toBe(2);
        expect($dto->createdAt)->toBe('2025-01-15 10:00:00');
        expect($dto->updatedAt)->toBe('2025-01-16 11:30:00');
        expect($dto->password)->toBe('secret123');
        expect($dto->currentPassword)->toBe('oldpassword');
        expect($dto->newPassword)->toBe('newpassword');
    });

    it('accepts and returns nested Address objects', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\Customer();

        $billingAddress = new \Maho\ApiPlatform\ApiResource\Address();
        $billingAddress->id = 1;
        $billingAddress->firstName = 'John';
        $billingAddress->lastName = 'Doe';
        $billingAddress->city = 'Melbourne';
        $billingAddress->countryId = 'AU';
        $billingAddress->isDefaultBilling = true;

        $shippingAddress = new \Maho\ApiPlatform\ApiResource\Address();
        $shippingAddress->id = 2;
        $shippingAddress->firstName = 'John';
        $shippingAddress->lastName = 'Doe';
        $shippingAddress->city = 'Sydney';
        $shippingAddress->countryId = 'AU';
        $shippingAddress->isDefaultShipping = true;

        $dto->defaultBillingAddress = $billingAddress;
        $dto->defaultShippingAddress = $shippingAddress;
        $dto->addresses = [$billingAddress, $shippingAddress];

        expect($dto->defaultBillingAddress)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->defaultBillingAddress->id)->toBe(1);
        expect($dto->defaultBillingAddress->city)->toBe('Melbourne');
        expect($dto->defaultBillingAddress->isDefaultBilling)->toBeTrue();

        expect($dto->defaultShippingAddress)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->defaultShippingAddress->id)->toBe(2);
        expect($dto->defaultShippingAddress->city)->toBe('Sydney');
        expect($dto->defaultShippingAddress->isDefaultShipping)->toBeTrue();

        expect($dto->addresses)->toHaveCount(2);
        expect($dto->addresses[0])->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->addresses[1])->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
    });
});

describe('Customer - password never exposed', function (): void {
    it('marks password field as write-only via ApiProperty attribute', function (): void {
        $reflection = new ReflectionClass(\Maho\ApiPlatform\ApiResource\Customer::class);
        $passwordProperty = $reflection->getProperty('password');
        $attributes = $passwordProperty->getAttributes(\ApiPlatform\Metadata\ApiProperty::class);

        expect($attributes)->not->toBeEmpty();

        $apiPropertyAttribute = $attributes[0]->newInstance();
        expect($apiPropertyAttribute->isWritable())->toBeTrue();
        expect($apiPropertyAttribute->isReadable())->toBeFalse();
    });

    it('marks currentPassword field as write-only via ApiProperty attribute', function (): void {
        $reflection = new ReflectionClass(\Maho\ApiPlatform\ApiResource\Customer::class);
        $property = $reflection->getProperty('currentPassword');
        $attributes = $property->getAttributes(\ApiPlatform\Metadata\ApiProperty::class);

        expect($attributes)->not->toBeEmpty();

        $apiPropertyAttribute = $attributes[0]->newInstance();
        expect($apiPropertyAttribute->isWritable())->toBeTrue();
        expect($apiPropertyAttribute->isReadable())->toBeFalse();
    });

    it('marks newPassword field as write-only via ApiProperty attribute', function (): void {
        $reflection = new ReflectionClass(\Maho\ApiPlatform\ApiResource\Customer::class);
        $property = $reflection->getProperty('newPassword');
        $attributes = $property->getAttributes(\ApiPlatform\Metadata\ApiProperty::class);

        expect($attributes)->not->toBeEmpty();

        $apiPropertyAttribute = $attributes[0]->newInstance();
        expect($apiPropertyAttribute->isWritable())->toBeTrue();
        expect($apiPropertyAttribute->isReadable())->toBeFalse();
    });
});

describe('CustomerProvider - mapToDto', function (): void {
    it('correctly maps a real customer to DTO', function (): void {
        // Load any existing customer from the database
        $mahoCustomer = \Mage::getModel('customer/customer')
            ->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no customers exist in test database
        if (!$mahoCustomer->getId()) {
            $this->markTestSkipped('No customers found in test database');
        }

        // Use reflection to test private mapToDto method
        $securityMock = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $provider = new \Maho\ApiPlatform\State\Provider\CustomerProvider($securityMock);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        $dto = $method->invoke($provider, $mahoCustomer);

        // Verify DTO is correctly populated
        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Customer::class);
        expect($dto->id)->toBe((int) $mahoCustomer->getId());
        expect($dto->email)->toBe($mahoCustomer->getEmail());
        expect($dto->firstName)->toBe($mahoCustomer->getFirstname());
        expect($dto->lastName)->toBe($mahoCustomer->getLastname());
        expect($dto->groupId)->toBe((int) $mahoCustomer->getGroupId());
        expect($dto->isSubscribed)->toBe((bool) $mahoCustomer->getIsSubscribed());
        expect($dto->createdAt)->toBe($mahoCustomer->getCreatedAt());
        expect($dto->updatedAt)->toBe($mahoCustomer->getUpdatedAt());

        // Verify fullName is constructed correctly
        $expectedFullName = trim(($mahoCustomer->getFirstname() ?? '') . ' ' . ($mahoCustomer->getLastname() ?? ''));
        expect($dto->fullName)->toBe($expectedFullName);

        // Verify addresses array is populated
        expect($dto->addresses)->toBeArray();

        // If customer has a default billing address, verify it's mapped
        if ($mahoCustomer->getDefaultBilling()) {
            expect($dto->defaultBillingAddress)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
            expect($dto->defaultBillingAddress->isDefaultBilling)->toBeTrue();
        }

        // If customer has a default shipping address, verify it's mapped
        if ($mahoCustomer->getDefaultShipping()) {
            expect($dto->defaultShippingAddress)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
            expect($dto->defaultShippingAddress->isDefaultShipping)->toBeTrue();
        }

        // Password should always be null (write-only)
        expect($dto->password)->toBeNull();
    });
});

describe('CustomerProcessor - mapToDto', function (): void {
    it('correctly maps a real customer to DTO', function (): void {
        // Load any existing customer from the database
        $mahoCustomer = \Mage::getModel('customer/customer')
            ->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no customers exist in test database
        if (!$mahoCustomer->getId()) {
            $this->markTestSkipped('No customers found in test database');
        }

        // Use reflection to test private mapToDto method
        $securityMock = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $processor = new \Maho\ApiPlatform\State\Processor\CustomerProcessor($securityMock);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        $dto = $method->invoke($processor, $mahoCustomer);

        // Verify DTO is correctly populated
        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Customer::class);
        expect($dto->id)->toBe((int) $mahoCustomer->getId());
        expect($dto->email)->toBe($mahoCustomer->getEmail());
        expect($dto->firstName)->toBe($mahoCustomer->getFirstname());
        expect($dto->lastName)->toBe($mahoCustomer->getLastname());
        expect($dto->groupId)->toBe((int) $mahoCustomer->getGroupId());
        expect($dto->isSubscribed)->toBe((bool) $mahoCustomer->getIsSubscribed());
        expect($dto->createdAt)->toBe($mahoCustomer->getCreatedAt());
        expect($dto->updatedAt)->toBe($mahoCustomer->getUpdatedAt());

        // Verify fullName is constructed correctly
        $expectedFullName = trim(($mahoCustomer->getFirstname() ?? '') . ' ' . ($mahoCustomer->getLastname() ?? ''));
        expect($dto->fullName)->toBe($expectedFullName);

        // Password should always be null (write-only, never exposed)
        expect($dto->password)->toBeNull();
    });

    it('maps customer with minimal data correctly', function (): void {
        // Create a test customer with minimal required data
        $mahoCustomer = \Mage::getModel('customer/customer');
        $mahoCustomer->setId(999);
        $mahoCustomer->setEmail('test@example.com');
        $mahoCustomer->setGroupId(1);

        $securityMock = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $processor = new \Maho\ApiPlatform\State\Processor\CustomerProcessor($securityMock);

        $reflection = new ReflectionClass($processor);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        $dto = $method->invoke($processor, $mahoCustomer);

        expect($dto->id)->toBe(999);
        expect($dto->email)->toBe('test@example.com');
        expect($dto->groupId)->toBe(1);
        expect($dto->firstName)->toBeNull();
        expect($dto->lastName)->toBeNull();
        expect($dto->fullName)->toBe(''); // trim of null + space + null
        expect($dto->password)->toBeNull();
    });
});

describe('CustomerProvider - mapToDtoForSearch', function (): void {
    it('correctly maps customer for search results with pre-loaded address', function (): void {
        // Load any existing customer from the database with a billing address
        $mahoCustomer = \Mage::getModel('customer/customer')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('default_billing', ['notnull' => true])
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no customers with billing address exist
        if (!$mahoCustomer->getId() || !$mahoCustomer->getDefaultBilling()) {
            $this->markTestSkipped('No customers with billing address found in test database');
        }

        // Load the billing address
        $billingAddressId = (int) $mahoCustomer->getDefaultBilling();
        $billingAddress = \Mage::getModel('customer/address')->load($billingAddressId);

        // Prepare the maps as the provider would
        $defaultBillingIds = [(int) $mahoCustomer->getId() => $billingAddressId];
        $addressMap = [$billingAddressId => $billingAddress];

        // Use reflection to test private mapToDtoForSearch method
        $securityMock = $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class);
        $provider = new \Maho\ApiPlatform\State\Provider\CustomerProvider($securityMock);

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapToDtoForSearch');
        $method->setAccessible(true);

        $dto = $method->invoke($provider, $mahoCustomer, $defaultBillingIds, $addressMap);

        // Verify basic customer fields
        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Customer::class);
        expect($dto->id)->toBe((int) $mahoCustomer->getId());
        expect($dto->email)->toBe($mahoCustomer->getEmail());
        expect($dto->firstName)->toBe($mahoCustomer->getFirstname());
        expect($dto->lastName)->toBe($mahoCustomer->getLastname());
        expect($dto->groupId)->toBe((int) $mahoCustomer->getGroupId());

        // Verify default billing address is included
        expect($dto->defaultBillingAddress)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->defaultBillingAddress->id)->toBe($billingAddressId);
        expect($dto->defaultBillingAddress->isDefaultBilling)->toBeTrue();

        // Verify other addresses are NOT included (search optimization)
        expect($dto->addresses)->toBe([]);
        expect($dto->defaultShippingAddress)->toBeNull();
    });
});

describe('AddressMapper - fromCustomerAddress', function (): void {
    it('correctly maps a Maho address to Address DTO', function (): void {
        // Load any existing address from the database
        $mahoAddress = \Mage::getModel('customer/address')
            ->getCollection()
            ->setPageSize(1)
            ->getFirstItem();

        // Skip test if no addresses exist
        if (!$mahoAddress->getId()) {
            $this->markTestSkipped('No customer addresses found in test database');
        }

        $mapper = new \Maho\ApiPlatform\Service\AddressMapper();
        /** @var \Mage_Customer_Model_Address $mahoAddress */
        $dto = $mapper->fromCustomerAddress($mahoAddress);

        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->id)->toBe((int) $mahoAddress->getId());
        expect($dto->firstName)->toBe($mahoAddress->getFirstname() ?? '');
        expect($dto->lastName)->toBe($mahoAddress->getLastname() ?? '');
        expect($dto->company)->toBe($mahoAddress->getCompany());
        expect($dto->street)->toBe($mahoAddress->getStreet());
        expect($dto->city)->toBe($mahoAddress->getCity() ?? '');
        expect($dto->region)->toBe($mahoAddress->getRegion());
        expect($dto->postcode)->toBe($mahoAddress->getPostcode() ?? '');
        expect($dto->countryId)->toBe($mahoAddress->getCountryId() ?? '');
        expect($dto->telephone)->toBe($mahoAddress->getTelephone() ?? '');

        if ($mahoAddress->getRegionId()) {
            expect($dto->regionId)->toBe((int) $mahoAddress->getRegionId());
        } else {
            expect($dto->regionId)->toBeNull();
        }
    });
});
