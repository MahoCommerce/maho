<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Mage\Tax\Api\TaxClass;
use Mage\Tax\Api\TaxClassProcessor;
use Mage\Tax\Api\TaxRate;
use Mage\Tax\Api\TaxRateProcessor;
use Mage\Tax\Api\TaxRule;
use Mage\Tax\Api\TaxRuleProcessor;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin pages and the API save and delete the tax classes, the tax rates and the tax rules through the
 * services of Mage_Tax (#1348). Each test gives the same input to both transports and compares the outcome.
 */

const TAX_PARITY_PREFIX = 'Parity Tax ';

/**
 * @param array<string, mixed> $properties
 */
function taxParityApi(string $processorClass, string $resourceClass, array $properties, ?int $deleteId = null): array
{
    $dto = new $resourceClass();
    foreach ($properties as $property => $value) {
        $dto->{$property} = $value;
    }
    $request = AdminApiParity::jsonRequest($deleteId === null ? 'POST' : 'DELETE', '/api/rest/v2/tax');
    return AdminApiParity::api($request, fn() => new $processorClass(AdminApiParity::adminSecurity())->process(
        $dto,
        $deleteId === null ? new Post(class: $resourceClass) : new Delete(class: $resourceClass),
        $deleteId === null ? [] : ['id' => $deleteId],
        ['request' => $request],
    ));
}

function taxParityClassId(string $type): int
{
    return (int) Mage::getResourceModel('tax/class_collection')->setClassTypeFilter($type)->getFirstItem()->getId();
}

function taxParityCleanup(): void
{
    foreach (Mage::getResourceModel('tax/calculation_rule_collection')->addFieldToFilter('code', ['like' => TAX_PARITY_PREFIX . '%']) as $rule) {
        $rule->delete();
    }
    foreach (Mage::getResourceModel('tax/calculation_rate_collection')->addFieldToFilter('code', ['like' => TAX_PARITY_PREFIX . '%']) as $rate) {
        $rate->delete();
    }
    foreach (Mage::getResourceModel('tax/class_collection')->addFieldToFilter('class_name', ['like' => TAX_PARITY_PREFIX . '%']) as $class) {
        Mage::getResourceModel('customer/group_collection')->addFieldToFilter('tax_class_id', $class->getId())->walk('delete');
        $class->delete();
    }
}

function taxParityRate(string $code): Mage_Tax_Model_Calculation_Rate
{
    return Mage::getService('tax/rate')->save(
        Mage::getModel('tax/calculation_rate')->setCode($code)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(5.0),
    );
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    taxParityCleanup();
});

afterEach(function (): void {
    taxParityCleanup();
});

describe('tax classes', function (): void {
    it('refuses a class without a name on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_ClassController::class, 'save', ['class_name' => ' ', 'class_type' => 'PRODUCT']);
        $api = taxParityApi(TaxClassProcessor::class, TaxClass::class, ['className' => ' ', 'classType' => 'PRODUCT']);

        expect($admin['errors'])->toBe(['Tax class name is required.'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['Tax class name is required.'], 'fields' => ['className']]);
    });

    it('refuses to delete a class that a customer group uses on both transports', function (): void {
        $class = Mage::getService('tax/class')->save(
            Mage::getModel('tax/class')->setClassName(TAX_PARITY_PREFIX . 'Customer')->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER),
        );
        Mage::getModel('customer/group')->setCode(substr(TAX_PARITY_PREFIX . uniqid(), 0, 32))->setTaxClassId((int) $class->getId())->save();
        $message = 'You cannot delete this tax class as it is used for 1 customer groups.';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_Class_CustomerController::class, 'delete', ['id' => $class->getId()]);
        $api = taxParityApi(TaxClassProcessor::class, TaxClass::class, [], (int) $class->getId());

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]])
            ->and(Mage::getModel('tax/class')->load($class->getId())->getId())->not->toBeNull();
    });

    it('does not show a class of the other type on the admin page', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_Class_CustomerController::class, 'delete', [
            'id' => taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT),
        ]);

        expect($admin['errors'])->toBe(['This class no longer exists']);
    });
});

describe('tax rates', function (): void {
    it('refuses an unknown country on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RateController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Admin', 'tax_country_id' => 'XX', 'tax_postcode' => '*', 'rate' => '5', 'zip_is_range' => '0',
        ]);
        $api = taxParityApi(TaxRateProcessor::class, TaxRate::class, [
            'code' => TAX_PARITY_PREFIX . 'Api', 'taxCountryId' => 'XX', 'taxPostcode' => '*', 'rate' => 5.0,
        ]);

        expect($admin['errors'])->toBe(['One of the country has invalid code.'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['One of the country has invalid code.'], 'fields' => ['taxCountryId']]);
    });

    it('refuses a zip range whose end is lower than its start on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RateController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Admin', 'tax_country_id' => 'US', 'rate' => '5', 'zip_is_range' => '1', 'zip_from' => '200', 'zip_to' => '100',
        ]);
        $api = taxParityApi(TaxRateProcessor::class, TaxRate::class, [
            'code' => TAX_PARITY_PREFIX . 'Api', 'taxCountryId' => 'US', 'rate' => 5.0, 'zipIsRange' => true, 'zipFrom' => '200', 'zipTo' => '100',
        ]);

        expect($admin['errors'])->toBe(['Range To should be equal or greater than Range From.'])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => ['Range To should be equal or greater than Range From.']]);
    });

    it('keeps the titles of a rate that a save without titles writes', function (): void {
        $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $rate = taxParityRate(TAX_PARITY_PREFIX . 'Titles');
        $rate->setTitle([$storeId => 'Parity VAT'])->save();

        Mage::getModel('tax/calculation_rate')->load($rate->getId())->setRate(6.0)->save();

        $titles = Mage::getModel('tax/calculation_rate')->load($rate->getId())->getTitles();
        expect(array_map(fn($title) => $title->getValue(), array_values($titles->getItems())))->toBe(['Parity VAT']);
    });

    it('reports a row with an unknown country and imports the others', function (): void {
        $headers = array_values(Mage::getService('tax/rate')->getCsvHeaders());
        $errors = Mage::getService('tax/rate')->import([
            $headers,
            [TAX_PARITY_PREFIX . 'Import 1', 'US', '*', '*', '7', '0', '', ''],
            [TAX_PARITY_PREFIX . 'Import 2', 'XX', '*', '*', '7', '0', '', ''],
        ]);

        expect($errors)->toBe(['One of the country has invalid code.'])
            ->and(Mage::getModel('tax/calculation_rate')->loadByCode(TAX_PARITY_PREFIX . 'Import 1')->getId())->not->toBeNull()
            ->and(Mage::getModel('tax/calculation_rate')->loadByCode(TAX_PARITY_PREFIX . 'Import 2')->getId())->toBeNull();
    });
});

describe('tax rules', function (): void {
    it('refuses a second rule for the same rate and classes on both transports', function (): void {
        $rate = taxParityRate(TAX_PARITY_PREFIX . 'Rule rate');
        $customerClassId = taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER);
        $productClassId = taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode(TAX_PARITY_PREFIX . 'First')
            ->setPriority(0)->setPosition(0)
            ->setTaxCustomerClass([$customerClassId])->setTaxProductClass([$productClassId])->setTaxRate([(int) $rate->getId()]));
        $message = 'Rules (' . TAX_PARITY_PREFIX . 'First) already exist for the specified Tax Rate, Customer Tax Class and Product Tax Class combinations';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RuleController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Admin', 'priority' => '0', 'position' => '0',
            'tax_customer_class' => [$customerClassId], 'tax_product_class' => [$productClassId], 'tax_rate' => [$rate->getId()],
        ]);
        $api = taxParityApi(TaxRuleProcessor::class, TaxRule::class, [
            'code' => TAX_PARITY_PREFIX . 'Api',
            'customerTaxClassIds' => [$customerClassId], 'productTaxClassIds' => [$productClassId], 'taxRateIds' => [(int) $rate->getId()],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });

    it('refuses a product tax class in the customer classes on both transports', function (): void {
        $rate = taxParityRate(TAX_PARITY_PREFIX . 'Rule rate');
        $productClassId = taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
        $message = "Tax class not found with ID: {$productClassId}";

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RuleController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Admin', 'priority' => '0', 'position' => '0',
            'tax_customer_class' => [$productClassId], 'tax_product_class' => [$productClassId], 'tax_rate' => [$rate->getId()],
        ]);
        $api = taxParityApi(TaxRuleProcessor::class, TaxRule::class, [
            'code' => TAX_PARITY_PREFIX . 'Api',
            'customerTaxClassIds' => [$productClassId], 'productTaxClassIds' => [$productClassId], 'taxRateIds' => [(int) $rate->getId()],
        ]);

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 422, 'errors' => [$message]]);
    });

    it('refuses to delete a rate that a rule uses on both transports', function (): void {
        $rate = taxParityRate(TAX_PARITY_PREFIX . 'Used rate');
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode(TAX_PARITY_PREFIX . 'Uses rate')
            ->setPriority(0)->setPosition(0)
            ->setTaxCustomerClass([taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)])
            ->setTaxProductClass([taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)])
            ->setTaxRate([(int) $rate->getId()]));
        $message = 'Tax rate cannot be removed. It exists in tax rule';

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RateController::class, 'delete', ['rate' => $rate->getId()]);
        $api = taxParityApi(TaxRateProcessor::class, TaxRate::class, [], (int) $rate->getId());

        expect($admin['errors'])->toBe([$message])
            ->and($api)->toMatchArray(['status' => 409, 'errors' => [$message]]);
    });
});
