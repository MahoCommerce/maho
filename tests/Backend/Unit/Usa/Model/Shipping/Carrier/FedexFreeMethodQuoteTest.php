<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Keep the real rate collector, response parser and inherited free-method helper.
 * Only carrier config and the remote transport are replaced for this fixture.
 */
final class FedexFreeMethodQuoteProbe extends Mage_Usa_Model_Shipping_Carrier_Fedex
{
    /** @var list<array{purpose: string, weight: float}> */
    public array $calls = [];

    /** @param list<array{purpose: string, response: array}> $responses */
    public function __construct(private array $responses)
    {
        parent::__construct();
    }

    #[\Override]
    public function getConfigData($field)
    {
        $values = [
            'client_id' => 'synthetic-id',
            'client_secret' => 'synthetic-secret',
            'account' => 'synthetic-account',
            'dropoff' => 'USE_SCHEDULED_PICKUP',
            'packaging' => 'YOUR_PACKAGING',
            'max_package_weight' => 0,
            'allowed_methods' => 'SMART_POST,FEDEX_GROUND',
            'free_method' => 'FEDEX_GROUND',
            'title' => 'Synthetic FedEx',
            'specificerrmsg' => 'Synthetic unavailable',
            'handling_fee' => 0,
            'handling_type' => 'F',
            'handling_action' => 'O',
        ];

        if (!array_key_exists($field, $values)) {
            throw new RuntimeException('Unexpected FedEx config: ' . $field);
        }
        return $values[$field];
    }

    #[\Override]
    public function getConfigFlag($field)
    {
        return match ($field) {
            'active' => true,
            'free_shipping_enable' => false,
            default => throw new RuntimeException('Unexpected FedEx flag: ' . $field),
        };
    }

    #[\Override]
    protected function _doRatesRequest($purpose)
    {
        $this->calls[] = [
            'purpose' => (string) $purpose,
            'weight' => (float) $this->_rawRequest->getWeight(),
        ];

        if ($this->responses === []) {
            throw new RuntimeException('Finite FedEx response queue exhausted');
        }
        $next = array_shift($this->responses);
        if ($next['purpose'] !== $purpose) {
            throw new RuntimeException('Unexpected FedEx request purpose: ' . $purpose);
        }
        return $next['response'];
    }

    public function remainingResponses(): int
    {
        return count($this->responses);
    }
}

function fedexFreeMethodResponse(string $purpose, array $body): array
{
    return ['purpose' => $purpose, 'response' => $body];
}

function fedexFreeMethodRate(string $method, float $amount): array
{
    return ['output' => ['rateReplyDetails' => [[
        'serviceType' => $method,
        'ratedShipmentDetails' => [[
            'rateType' => 'ACCOUNT',
            'totalNetCharge' => $amount,
        ]],
    ]]]];
}

function fedexFreeMethodError(): array
{
    return ['errors' => [['code' => 'SYNTHETIC_ERROR']]];
}

function fedexFreeMethodRequest(float $weight): Mage_Shipping_Model_Rate_Request
{
    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setOrigCountry('US');
    $request->setOrigPostcode('38017');
    $request->setDestCountryId('US');
    $request->setDestPostcode('90210');
    $request->setPackageWeight(10.0);
    $request->setFreeMethodWeight($weight);
    $request->setPackagePhysicalValue(100.0);
    $request->setBaseSubtotalInclTax(0.0);
    return $request;
}

function fedexFreeMethodExpectedCalls(?float $partialWeight = null): array
{
    $calls = [
        ['purpose' => 'SMART_POST', 'weight' => 10.0],
        ['purpose' => 'general', 'weight' => 10.0],
    ];
    if ($partialWeight !== null) {
        $calls[] = ['purpose' => 'SMART_POST', 'weight' => $partialWeight];
        $calls[] = ['purpose' => 'general', 'weight' => $partialWeight];
    }
    return $calls;
}

function fedexFreeMethodRates(Mage_Shipping_Model_Rate_Result $result): array
{
    return array_map(static fn($rate) => $rate->getMethod(), $result->getAllRates());
}

function fedexFreeMethodAssertCalls(FedexFreeMethodQuoteProbe $probe, ?float $partialWeight = null): void
{
    expect($probe->calls)->toBe(fedexFreeMethodExpectedCalls($partialWeight));
    expect($probe->remainingResponses())->toBe(0);
}

it('keeps original rates and price when a partial-weight re-quote has two errors', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodRate('SMART_POST', 10.0)),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 20.0)),
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodError()),
        fedexFreeMethodResponse('general', fedexFreeMethodError()),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(4.0));

    expect($result)->toBeInstanceOf(Mage_Shipping_Model_Rate_Result::class);
    expect(fedexFreeMethodRates($result))->toBe(['SMART_POST', 'FEDEX_GROUND']);
    expect(array_map(static fn($rate) => $rate->getPrice(), $result->getAllRates()))->toBe([10.0, 20.0]);
    fedexFreeMethodAssertCalls($probe, 4.0);
});

it('returns an error result when the initial quote has two errors', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodError()),
        fedexFreeMethodResponse('general', fedexFreeMethodError()),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(4.0));

    expect($result)->toBeInstanceOf(Mage_Shipping_Model_Rate_Result::class);
    expect($result->getError())->toBeTrue();
    expect($result->getAllRates())->toHaveCount(1);
    expect($result->getAllRates()[0])->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Error::class);
    fedexFreeMethodAssertCalls($probe);
});

it('does not re-quote when free-method weight equals package weight', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodRate('SMART_POST', 10.0)),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 20.0)),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(10.0));

    expect(fedexFreeMethodRates($result))->toBe(['SMART_POST', 'FEDEX_GROUND']);
    expect(array_map(static fn($rate) => $rate->getPrice(), $result->getAllRates()))->toBe([10.0, 20.0]);
    fedexFreeMethodAssertCalls($probe);
});

it('sets only the free-method price to zero for zero free-method weight', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodRate('SMART_POST', 10.0)),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 20.0)),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(0.0));

    expect(fedexFreeMethodRates($result))->toBe(['SMART_POST', 'FEDEX_GROUND']);
    expect(array_map(static fn($rate) => $rate->getPrice(), $result->getAllRates()))->toBe([10.0, 0.0]);
    fedexFreeMethodAssertCalls($probe);
});

it('updates only the original free-method price after a successful re-quote', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodRate('SMART_POST', 10.0)),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 20.0)),
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodError()),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 8.0)),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(4.0));

    expect(fedexFreeMethodRates($result))->toBe(['SMART_POST', 'FEDEX_GROUND']);
    expect(array_map(static fn($rate) => $rate->getPrice(), $result->getAllRates()))->toBe([10.0, 8.0]);
    expect(array_map(static fn($rate) => $rate->getCost(), $result->getAllRates()))->toBe([10.0, 20.0]);
    fedexFreeMethodAssertCalls($probe, 4.0);
});

it('retains the original free method when the re-quote returns another method', function () {
    $probe = new FedexFreeMethodQuoteProbe([
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodError()),
        fedexFreeMethodResponse('general', fedexFreeMethodRate('FEDEX_GROUND', 20.0)),
        fedexFreeMethodResponse('SMART_POST', fedexFreeMethodRate('SMART_POST', 7.0)),
        fedexFreeMethodResponse('general', fedexFreeMethodError()),
    ]);

    $result = $probe->collectRates(fedexFreeMethodRequest(4.0));

    expect(fedexFreeMethodRates($result))->toBe(['FEDEX_GROUND']);
    expect($result->getAllRates()[0]->getPrice())->toBe(20.0);
    fedexFreeMethodAssertCalls($probe, 4.0);
});
