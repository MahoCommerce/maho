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

describe('Address DTO', function () {
    it('has correct default values', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();

        expect($dto->id)->toBeNull();
        expect($dto->customerId)->toBeNull();
        expect($dto->firstName)->toBe('');
        expect($dto->lastName)->toBe('');
        expect($dto->company)->toBeNull();
        expect($dto->street)->toBe([]);
        expect($dto->city)->toBe('');
        expect($dto->region)->toBeNull();
        expect($dto->regionId)->toBeNull();
        expect($dto->postcode)->toBe('');
        expect($dto->countryId)->toBe('');
        expect($dto->telephone)->toBe('');
        expect($dto->isDefaultBilling)->toBeFalse();
        expect($dto->isDefaultShipping)->toBeFalse();
    });
});

describe('Address DTO - property assignment', function () {
    it('accepts all property values', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->id = 123;
        $dto->customerId = 456;
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->company = 'Acme Corp';
        $dto->street = ['123 Main St', 'Apt 4'];
        $dto->city = 'Sydney';
        $dto->region = 'New South Wales';
        $dto->regionId = 1;
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';
        $dto->isDefaultBilling = true;
        $dto->isDefaultShipping = true;

        expect($dto->id)->toBe(123);
        expect($dto->customerId)->toBe(456);
        expect($dto->firstName)->toBe('John');
        expect($dto->lastName)->toBe('Doe');
        expect($dto->company)->toBe('Acme Corp');
        expect($dto->street)->toBe(['123 Main St', 'Apt 4']);
        expect($dto->city)->toBe('Sydney');
        expect($dto->region)->toBe('New South Wales');
        expect($dto->regionId)->toBe(1);
        expect($dto->postcode)->toBe('2000');
        expect($dto->countryId)->toBe('AU');
        expect($dto->telephone)->toBe('0412345678');
        expect($dto->isDefaultBilling)->toBeTrue();
        expect($dto->isDefaultShipping)->toBeTrue();
    });
});

describe('AddressProvider - mapToDto', function () {
    it('maps address model to DTO correctly', function () {
        // Load a real customer with addresses from the database
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1);

        if ($customerCollection->getSize() === 0) {
            $this->markTestSkipped('No customers found in database');
        }

        $customer = $customerCollection->getFirstItem();

        // Get or create an address for the customer
        $addresses = $customer->getAddresses();
        if (empty($addresses)) {
            // Create a test address if none exists
            $address = Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId())
                ->setFirstname('Test')
                ->setLastname('User')
                ->setCompany('Test Company')
                ->setStreet(['123 Test St', 'Suite 100'])
                ->setCity('Test City')
                ->setRegion('Test Region')
                ->setRegionId(1)
                ->setPostcode('12345')
                ->setCountryId('US')
                ->setTelephone('555-1234')
                ->save();
        } else {
            $address = reset($addresses);
        }

        // Use reflection to access the private mapToDto method
        $provider = new \Maho\ApiPlatform\State\Provider\AddressProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        $dto = $method->invoke($provider, $address, $customer);

        // Verify all fields are mapped correctly
        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Address::class);
        expect($dto->id)->toBe((int) $address->getId());
        expect($dto->customerId)->toBe((int) $address->getCustomerId());
        expect($dto->firstName)->toBe($address->getFirstname() ?? '');
        expect($dto->lastName)->toBe($address->getLastname() ?? '');
        expect($dto->company)->toBe($address->getCompany());
        expect($dto->street)->toBe($address->getStreet());
        expect($dto->city)->toBe($address->getCity() ?? '');
        expect($dto->region)->toBe($address->getRegion());
        expect($dto->regionId)->toBe($address->getRegionId() ? (int) $address->getRegionId() : null);
        expect($dto->postcode)->toBe($address->getPostcode() ?? '');
        expect($dto->countryId)->toBe($address->getCountryId() ?? '');
        expect($dto->telephone)->toBe($address->getTelephone() ?? '');
        expect($dto->isDefaultBilling)->toBe($address->getId() == $customer->getDefaultBilling());
        expect($dto->isDefaultShipping)->toBe($address->getId() == $customer->getDefaultShipping());

        // Clean up if we created a test address
        if ($address->getFirstname() === 'Test' && $address->getLastname() === 'User') {
            $address->delete();
        }
    });
});

describe('AddressProcessor - validation', function () {
    beforeEach(function () {
        $this->processor = new \Maho\ApiPlatform\State\Processor\AddressProcessor(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        $this->reflection = new ReflectionClass($this->processor);
        $this->validateMethod = $this->reflection->getMethod('validateAddress');
        $this->validateMethod->setAccessible(true);
    });

    it('throws exception when firstName is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = '';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when lastName is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = '';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when street is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = [];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when street contains only empty strings', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['', ''];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when city is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = '';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when postcode is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when countryId is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = '';
        $dto->telephone = '0412345678';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('throws exception when telephone is empty', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '';

        expect(fn() => $this->validateMethod->invoke($this->processor, $dto))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);
    });

    it('passes validation with all required fields', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->street = ['123 Main St'];
        $dto->city = 'Sydney';
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        // Should not throw exception
        $this->validateMethod->invoke($this->processor, $dto);

        expect(true)->toBeTrue(); // Test passes if no exception is thrown
    });

    it('passes validation with optional fields populated', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Address();
        $dto->firstName = 'John';
        $dto->lastName = 'Doe';
        $dto->company = 'Acme Corp';
        $dto->street = ['123 Main St', 'Apt 4'];
        $dto->city = 'Sydney';
        $dto->region = 'New South Wales';
        $dto->regionId = 1;
        $dto->postcode = '2000';
        $dto->countryId = 'AU';
        $dto->telephone = '0412345678';

        // Should not throw exception
        $this->validateMethod->invoke($this->processor, $dto);

        expect(true)->toBeTrue(); // Test passes if no exception is thrown
    });
});

describe('AddressProcessor - mapToDto', function () {
    it('produces same output as AddressProvider mapToDto', function () {
        // Load a real customer with addresses
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1);

        if ($customerCollection->getSize() === 0) {
            $this->markTestSkipped('No customers found in database');
        }

        $customer = $customerCollection->getFirstItem();

        // Get or create an address
        $addresses = $customer->getAddresses();
        if (empty($addresses)) {
            $address = Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId())
                ->setFirstname('Test')
                ->setLastname('User')
                ->setCompany('Test Company')
                ->setStreet(['123 Test St', 'Suite 100'])
                ->setCity('Test City')
                ->setRegion('Test Region')
                ->setRegionId(1)
                ->setPostcode('12345')
                ->setCountryId('US')
                ->setTelephone('555-1234')
                ->save();
        } else {
            $address = reset($addresses);
        }

        // Call Provider's mapToDto
        $provider = new \Maho\ApiPlatform\State\Provider\AddressProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );
        $providerReflection = new ReflectionClass($provider);
        $providerMethod = $providerReflection->getMethod('mapToDto');
        $providerMethod->setAccessible(true);
        $providerDto = $providerMethod->invoke($provider, $address, $customer);

        // Call Processor's mapToDto
        $processor = new \Maho\ApiPlatform\State\Processor\AddressProcessor(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );
        $processorReflection = new ReflectionClass($processor);
        $processorMethod = $processorReflection->getMethod('mapToDto');
        $processorMethod->setAccessible(true);
        $processorDto = $processorMethod->invoke($processor, $address, $customer);

        // Compare all fields
        expect($processorDto->id)->toBe($providerDto->id);
        expect($processorDto->customerId)->toBe($providerDto->customerId);
        expect($processorDto->firstName)->toBe($providerDto->firstName);
        expect($processorDto->lastName)->toBe($providerDto->lastName);
        expect($processorDto->company)->toBe($providerDto->company);
        expect($processorDto->street)->toBe($providerDto->street);
        expect($processorDto->city)->toBe($providerDto->city);
        expect($processorDto->region)->toBe($providerDto->region);
        expect($processorDto->regionId)->toBe($providerDto->regionId);
        expect($processorDto->postcode)->toBe($providerDto->postcode);
        expect($processorDto->countryId)->toBe($providerDto->countryId);
        expect($processorDto->telephone)->toBe($providerDto->telephone);
        expect($processorDto->isDefaultBilling)->toBe($providerDto->isDefaultBilling);
        expect($processorDto->isDefaultShipping)->toBe($providerDto->isDefaultShipping);

        // Clean up if we created a test address
        if ($address->getFirstname() === 'Test' && $address->getLastname() === 'User') {
            $address->delete();
        }
    });
});

describe('Address - default billing/shipping detection', function () {
    it('correctly detects default billing and shipping addresses', function () {
        // Load a customer
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1);

        if ($customerCollection->getSize() === 0) {
            $this->markTestSkipped('No customers found in database');
        }

        $customer = $customerCollection->getFirstItem();

        // Create test addresses
        $billingAddress = Mage::getModel('customer/address');
        $billingAddress->setCustomerId($customer->getId())
            ->setFirstname('Billing')
            ->setLastname('Address')
            ->setStreet(['100 Billing St'])
            ->setCity('Sydney')
            ->setPostcode('2000')
            ->setCountryId('AU')
            ->setTelephone('0400000001')
            ->save();

        $shippingAddress = Mage::getModel('customer/address');
        $shippingAddress->setCustomerId($customer->getId())
            ->setFirstname('Shipping')
            ->setLastname('Address')
            ->setStreet(['200 Shipping St'])
            ->setCity('Melbourne')
            ->setPostcode('3000')
            ->setCountryId('AU')
            ->setTelephone('0400000002')
            ->save();

        $regularAddress = Mage::getModel('customer/address');
        $regularAddress->setCustomerId($customer->getId())
            ->setFirstname('Regular')
            ->setLastname('Address')
            ->setStreet(['300 Regular St'])
            ->setCity('Brisbane')
            ->setPostcode('4000')
            ->setCountryId('AU')
            ->setTelephone('0400000003')
            ->save();

        // Set default addresses
        $customer->setDefaultBilling($billingAddress->getId());
        $customer->setDefaultShipping($shippingAddress->getId());
        $customer->save();

        // Reload customer to get updated defaults
        $customer = Mage::getModel('customer/customer')->load($customer->getId());

        // Use reflection to test mapToDto
        $provider = new \Maho\ApiPlatform\State\Provider\AddressProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        // Test billing address
        $billingDto = $method->invoke($provider, $billingAddress, $customer);
        expect($billingDto->isDefaultBilling)->toBeTrue();
        expect($billingDto->isDefaultShipping)->toBeFalse();

        // Test shipping address
        $shippingDto = $method->invoke($provider, $shippingAddress, $customer);
        expect($shippingDto->isDefaultBilling)->toBeFalse();
        expect($shippingDto->isDefaultShipping)->toBeTrue();

        // Test regular address
        $regularDto = $method->invoke($provider, $regularAddress, $customer);
        expect($regularDto->isDefaultBilling)->toBeFalse();
        expect($regularDto->isDefaultShipping)->toBeFalse();

        // Clean up test addresses
        $billingAddress->delete();
        $shippingAddress->delete();
        $regularAddress->delete();

        // Reset customer defaults
        $customer->setDefaultBilling(null);
        $customer->setDefaultShipping(null);
        $customer->save();
    });

    it('handles customer with no default addresses', function () {
        // Load a customer
        $customerCollection = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1);

        if ($customerCollection->getSize() === 0) {
            $this->markTestSkipped('No customers found in database');
        }

        $customer = $customerCollection->getFirstItem();

        // Create a test address
        $address = Mage::getModel('customer/address');
        $address->setCustomerId($customer->getId())
            ->setFirstname('Test')
            ->setLastname('NoDefaults')
            ->setStreet(['400 Test St'])
            ->setCity('Adelaide')
            ->setPostcode('5000')
            ->setCountryId('AU')
            ->setTelephone('0400000004')
            ->save();

        // Ensure customer has no defaults
        $customer->setDefaultBilling(null);
        $customer->setDefaultShipping(null);
        $customer->save();

        // Reload customer
        $customer = Mage::getModel('customer/customer')->load($customer->getId());

        // Use reflection to test mapToDto
        $provider = new \Maho\ApiPlatform\State\Provider\AddressProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('mapToDto');
        $method->setAccessible(true);

        $dto = $method->invoke($provider, $address, $customer);

        expect($dto->isDefaultBilling)->toBeFalse();
        expect($dto->isDefaultShipping)->toBeFalse();

        // Clean up
        $address->delete();
    });
});
