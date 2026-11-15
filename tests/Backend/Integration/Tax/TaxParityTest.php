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

describe('successful writes', function (): void {
    it('saves and deletes a class with the same row on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_ClassController::class, 'save', ['class_name' => TAX_PARITY_PREFIX . 'Class Admin', 'class_type' => 'PRODUCT']);
        $api = taxParityApi(TaxClassProcessor::class, TaxClass::class, ['className' => TAX_PARITY_PREFIX . 'Class Api', 'classType' => 'PRODUCT']);

        $rows = [];
        foreach (['Admin', 'Api'] as $transport) {
            $class = Mage::getResourceModel('tax/class_collection')->addFieldToFilter('class_name', TAX_PARITY_PREFIX . 'Class ' . $transport)->getFirstItem();
            $rows[$transport] = ['id' => (int) $class->getId(), 'type' => $class->getClassType()];
        }
        expect($admin)->toBe(['success' => ['The tax class has been saved.'], 'errors' => []])
            ->and($api['status'])->toBe(200)
            ->and($rows['Admin']['id'])->toBeGreaterThan(0)
            ->and($rows['Api']['id'])->toBeGreaterThan(0)
            ->and($rows['Admin']['type'])->toBe('PRODUCT')
            ->and($rows['Api']['type'])->toBe('PRODUCT');

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_Class_ProductController::class, 'delete', ['id' => $rows['Admin']['id']]);
        $api = taxParityApi(TaxClassProcessor::class, TaxClass::class, [], $rows['Api']['id']);

        expect($admin)->toBe(['success' => ['The tax class has been deleted.'], 'errors' => []])
            ->and($api['status'])->toBe(200)
            ->and(Mage::getModel('tax/class')->load($rows['Admin']['id'])->getId())->toBeNull()
            ->and(Mage::getModel('tax/class')->load($rows['Api']['id'])->getId())->toBeNull();
    });

    it('saves and deletes a rate with the same row on both transports', function (): void {
        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RateController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Rate Admin', 'tax_country_id' => 'US', 'tax_region_id' => '0', 'tax_postcode' => '*', 'rate' => '7.5', 'zip_is_range' => '0',
        ]);
        $api = taxParityApi(TaxRateProcessor::class, TaxRate::class, [
            'code' => TAX_PARITY_PREFIX . 'Rate Api', 'taxCountryId' => 'US', 'taxRegionId' => 0, 'taxPostcode' => '*', 'rate' => 7.5,
        ]);

        $rows = [];
        $ids = [];
        foreach (['Admin', 'Api'] as $transport) {
            $rate = Mage::getModel('tax/calculation_rate')->loadByCode(TAX_PARITY_PREFIX . 'Rate ' . $transport);
            $ids[$transport] = (int) $rate->getId();
            $rows[$transport] = [
                'country' => $rate->getTaxCountryId(),
                'region' => (int) $rate->getTaxRegionId(),
                'postcode' => $rate->getTaxPostcode(),
                'rate' => (float) $rate->getData('rate'),
                'zip_is_range' => (int) $rate->getZipIsRange(),
            ];
        }
        expect($admin)->toBe(['success' => ['The tax rate has been saved.'], 'errors' => []])
            ->and($api['status'])->toBe(200)
            ->and($ids['Admin'])->toBeGreaterThan(0)
            ->and($rows['Admin'])->toBe(['country' => 'US', 'region' => 0, 'postcode' => '*', 'rate' => 7.5, 'zip_is_range' => 0])
            ->and($rows['Api'])->toBe($rows['Admin']);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RateController::class, 'delete', ['rate' => $ids['Admin']]);
        $api = taxParityApi(TaxRateProcessor::class, TaxRate::class, [], $ids['Api']);

        expect($admin['errors'])->toBe([])
            ->and($api['status'])->toBe(200)
            ->and(Mage::getModel('tax/calculation_rate')->load($ids['Admin'])->getId())->toBeNull()
            ->and(Mage::getModel('tax/calculation_rate')->load($ids['Api'])->getId())->toBeNull();
    });

    it('saves and deletes a rule with the same rows on both transports', function (): void {
        $customerClassId = taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER);
        $productClassId = taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT);
        $rateIds = [
            'Admin' => (int) taxParityRate(TAX_PARITY_PREFIX . 'Rule rate admin')->getId(),
            'Api' => (int) taxParityRate(TAX_PARITY_PREFIX . 'Rule rate api')->getId(),
        ];

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RuleController::class, 'save', [
            'code' => TAX_PARITY_PREFIX . 'Rule Admin', 'priority' => '2', 'position' => '3',
            'tax_customer_class' => [$customerClassId], 'tax_product_class' => [$productClassId], 'tax_rate' => [$rateIds['Admin']],
        ]);
        $api = taxParityApi(TaxRuleProcessor::class, TaxRule::class, [
            'code' => TAX_PARITY_PREFIX . 'Rule Api', 'priority' => 2, 'position' => 3,
            'customerTaxClassIds' => [$customerClassId], 'productTaxClassIds' => [$productClassId], 'taxRateIds' => [$rateIds['Api']],
        ]);

        $rows = [];
        $ids = [];
        foreach (['Admin', 'Api'] as $transport) {
            $rule = Mage::getResourceModel('tax/calculation_rule_collection')->addFieldToFilter('code', TAX_PARITY_PREFIX . 'Rule ' . $transport)->getFirstItem();
            $ids[$transport] = (int) $rule->getId();
            $rows[$transport] = [
                'priority' => (int) $rule->getPriority(),
                'position' => (int) $rule->getPosition(),
                'customer_classes' => array_map(intval(...), array_values($rule->getCustomerTaxClasses())),
                'product_classes' => array_map(intval(...), array_values($rule->getProductTaxClasses())),
                'rates' => array_map(intval(...), array_values($rule->getRates())),
            ];
        }
        expect($admin)->toBe(['success' => ['The tax rule has been saved.'], 'errors' => []])
            ->and($api['status'])->toBe(200)
            ->and($rows['Admin'])->toBe([
                'priority' => 2, 'position' => 3, 'customer_classes' => [$customerClassId], 'product_classes' => [$productClassId], 'rates' => [$rateIds['Admin']],
            ])
            ->and(['rates' => [$rateIds['Admin']]] + $rows['Api'])->toEqual($rows['Admin']);

        $admin = AdminApiParity::admin(Mage_Adminhtml_Tax_RuleController::class, 'delete', ['rule' => $ids['Admin']]);
        $api = taxParityApi(TaxRuleProcessor::class, TaxRule::class, [], $ids['Api']);

        expect($admin)->toBe(['success' => ['The tax rule has been deleted.'], 'errors' => []])
            ->and($api['status'])->toBe(200)
            ->and(Mage::getModel('tax/calculation_rule')->load($ids['Admin'])->getId())->toBeNull()
            ->and(Mage::getModel('tax/calculation_rule')->load($ids['Api'])->getId())->toBeNull();
    });
});

describe('the rules of the form stay out of the model', function (): void {
    it('lets a direct save of the model write a rate for a country that the store does not have', function (): void {
        $rate = Mage::getModel('tax/calculation_rate')->setCode(TAX_PARITY_PREFIX . 'Model')->setTaxCountryId('XX')->setTaxPostcode('*')->setRate(5.0);

        expect(fn() => Mage::getService('tax/rate')->save(clone $rate))->toThrow(Mage_Core_Exception_Input::class, 'One of the country has invalid code.');

        $rate->save();
        expect($rate->getId())->not->toBeNull();
    });

    it('still refuses a rate without a code in a direct save of the model', function (): void {
        $rate = Mage::getModel('tax/calculation_rate')->setTaxCountryId('US')->setTaxPostcode('*')->setRate(5.0);

        expect(fn() => $rate->save())->toThrow(Mage_Core_Exception_Input::class, 'Tax rate code is required.');
    });

    it('lets a direct save of the model write a second rule for the same rate and classes', function (): void {
        $rate = taxParityRate(TAX_PARITY_PREFIX . 'Rule rate');
        $newRule = fn(string $code): Mage_Tax_Model_Calculation_Rule => Mage::getModel('tax/calculation_rule')->setCode(TAX_PARITY_PREFIX . $code)
            ->setPriority(0)->setPosition(0)
            ->setTaxCustomerClass([taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)])
            ->setTaxProductClass([taxParityClassId(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)])
            ->setTaxRate([(int) $rate->getId()]);
        Mage::getService('tax/rule')->save($newRule('First'));

        expect(fn() => Mage::getService('tax/rule')->save($newRule('Service')))->toThrow(Mage_Core_Exception_Conflict::class);

        $second = $newRule('Model');
        $second->save();
        expect($second->getId())->not->toBeNull();
    });
});
