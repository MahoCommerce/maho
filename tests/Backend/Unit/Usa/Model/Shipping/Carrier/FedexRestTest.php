<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Exposes the carrier's protected REST plumbing so payload building and response
 * parsing can be asserted without touching the network, and lets a test pin
 * carrier config without writing to core_config_data.
 */
final class FedexRestProbe extends Mage_Usa_Model_Shipping_Carrier_Fedex
{
    /** @var array<string, mixed> */
    public array $config = [];

    #[\Override]
    public function getConfigData($field)
    {
        return array_key_exists($field, $this->config) ? $this->config[$field] : parent::getConfigData($field);
    }

    #[\Override]
    public function getConfigFlag($field)
    {
        return array_key_exists($field, $this->config) ? (bool) $this->config[$field] : parent::getConfigFlag($field);
    }

    public function formRateRequest(string $purpose): array
    {
        return $this->_formRateRequest($purpose);
    }

    public function prepareRateResponse(array $response): Mage_Shipping_Model_Rate_Result
    {
        return $this->_prepareRateResponse($response);
    }

    public function parseTrackingResponse(string $trackingValue, array $response): Mage_Shipping_Model_Tracking_Result
    {
        $this->_parseTrackingResponse($trackingValue, $response);
        return $this->_result;
    }

    public function formShipmentRequest(\Maho\DataObject $request): array
    {
        return $this->_formShipmentRequest($request);
    }

    public function setRawRequest(\Maho\DataObject $request): void
    {
        $this->_rawRequest = $request;
    }

    /** Canned cancel responses, popped one per cancelShipment() call. */
    public array $cancelResponses = [];

    /** @var list<array> */
    public array $cancelRequests = [];

    #[\Override]
    protected function _getRestClient(): Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient
    {
        $probe = $this;

        return new class ($probe) extends Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient {
            public function __construct(private FedexRestProbe $probe)
            {
                // Deliberately not calling parent::__construct: nothing here touches the network.
            }

            #[\Override]
            public function cancelShipment(array $requestData): array
            {
                $this->probe->cancelRequests[] = $requestData;

                return array_shift($this->probe->cancelResponses) ?? [];
            }
        };
    }
}

function fedexProbe(array $config = []): FedexRestProbe
{
    $probe = new FedexRestProbe();
    $probe->config = array_merge([
        'account' => '510087020',
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'sandbox_mode' => 1,
        'dropoff' => 'USE_SCHEDULED_PICKUP',
        'packaging' => 'YOUR_PACKAGING',
        'unit_of_measure' => 'LB',
        'residence_delivery' => 0,
        'smartpost_hubid' => '5531',
        'title' => 'Federal Express',
        'specificerrmsg' => 'This shipping method is currently unavailable.',
        'allowed_methods' => 'FEDEX_GROUND,FEDEX_2_DAY,PRIORITY_OVERNIGHT,FEDEX_INTERNATIONAL_PRIORITY,SMART_POST',
    ], $config);

    return $probe;
}

function fedexRawRateRequest(array $overrides = []): \Maho\DataObject
{
    $r = new \Maho\DataObject();
    $r->addData(array_merge([
        'account' => '510087020',
        'dropoff_type' => 'USE_SCHEDULED_PICKUP',
        'packaging' => 'YOUR_PACKAGING',
        'orig_country' => 'US',
        'orig_postal' => '38017',
        'dest_country' => 'US',
        'dest_postal' => '90210',
        'weight' => 10.0,
        'value' => 100.0,
    ], $overrides));

    return $r;
}

function fedexFixture(string $name): array
{
    $json = file_get_contents(__DIR__ . '/_fixtures/' . $name . '.json');

    return Mage::helper('core')->jsonDecode($json);
}

function fedexTrackFixture(): array
{
    return fedexFixture('track-response');
}

/**
 * The FedEx defaults as shipped in Mage_Usa's config.xml.
 *
 * Read from the file rather than Mage::getConfig(), which merges core_config_data over the
 * XML: a store with FedEx configured would otherwise make these assertions read back its
 * own saved values instead of the shipped defaults.
 */
function fedexShippedDefaults(): SimpleXMLElement
{
    $xml = simplexml_load_file(Mage::getModuleDir('etc', 'Mage_Usa') . DS . 'config.xml');

    return $xml->default->carriers->fedex;
}

describe('FedEx REST rate request payload', function () {
    it('builds the REST envelope and drops every SOAP-only block', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest());

        $payload = $probe->formRateRequest(Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL);

        expect($payload)
            ->toHaveKey('accountNumber')
            ->toHaveKey('requestedShipment')
            ->and($payload)->not->toHaveKey('WebAuthenticationDetail')
            ->and($payload)->not->toHaveKey('ClientDetail')
            ->and($payload)->not->toHaveKey('Version')
            ->and($payload)->not->toHaveKey('RequestedShipment');

        expect($payload['accountNumber']['value'])->toBe('510087020');
    });

    it('maps origin and destination onto the REST shipper/recipient addresses', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest());

        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment['shipper']['address'])->toBe(['postalCode' => '38017', 'countryCode' => 'US']);
        expect($shipment['recipient']['address'])->toBe([
            'postalCode' => '90210',
            'countryCode' => 'US',
            'residential' => false,
        ]);
    });

    it('sends pickupType, packagingType and a single weighted package line item', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest());

        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment['pickupType'])->toBe('USE_SCHEDULED_PICKUP');
        expect($shipment['packagingType'])->toBe('YOUR_PACKAGING');
        expect($shipment['rateRequestType'])->toBe(['ACCOUNT', 'LIST']);
        expect($shipment['totalPackageCount'])->toBe(1);
        expect($shipment['requestedPackageLineItems'])->toHaveCount(1);
        expect($shipment['requestedPackageLineItems'][0]['weight'])->toBe(['units' => 'LB', 'value' => 10.0]);
        expect($shipment['requestedPackageLineItems'][0]['groupPackageCount'])->toBe(1);
    });

    it('translates a legacy dropoff code to its REST pickupType', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest(['dropoff_type' => 'DROP_BOX']));

        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment['pickupType'])->toBe('DROPOFF_AT_FEDEX_LOCATION');
    });

    it('ships a shipDateStamp as a plain date, not a SOAP timestamp', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest());

        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment['shipDateStamp'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    it('adds customs clearance detail only when the shipment crosses a border', function () {
        $domestic = fedexProbe();
        $domestic->setRawRequest(fedexRawRateRequest());
        $domesticShipment = $domestic->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($domesticShipment)->not->toHaveKey('customsClearanceDetail');

        $international = fedexProbe();
        $international->setRawRequest(fedexRawRateRequest(['dest_country' => 'CA', 'dest_postal' => 'M5H 2N2']));
        $internationalShipment = $international->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($internationalShipment['customsClearanceDetail']['commodities'][0]['customsValue'])->toBe([
            'amount' => 100.0,
            'currency' => $international->getCurrencyCode(),
        ]);
    });

    it('switches to the SmartPost service with a weight-derived indicia', function () {
        $light = fedexProbe();
        $light->setRawRequest(fedexRawRateRequest(['weight' => 0.5]));
        $lightShipment = $light->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_SMARTPOST,
        )['requestedShipment'];

        expect($lightShipment['serviceType'])->toBe('SMART_POST');
        expect($lightShipment['smartPostInfoDetail'])->toBe([
            'indicia' => 'PRESORTED_STANDARD',
            'hubId' => '5531',
        ]);

        $heavy = fedexProbe();
        $heavy->setRawRequest(fedexRawRateRequest(['weight' => 4.0]));
        $heavyShipment = $heavy->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_SMARTPOST,
        )['requestedShipment'];

        expect($heavyShipment['smartPostInfoDetail']['indicia'])->toBe('PARCEL_SELECT');
    });

    it('leaves serviceType unset for a general quote so FedEx returns every service', function () {
        $probe = fedexProbe();
        $probe->setRawRequest(fedexRawRateRequest());

        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment)->not->toHaveKey('serviceType');
    });
});

describe('FedEx REST rate response parsing', function () {
    $reply = function (string $serviceType, array $ratedShipmentDetails): array {
        return ['serviceType' => $serviceType, 'ratedShipmentDetails' => $ratedShipmentDetails];
    };

    it('reads rates out of output.rateReplyDetails and filters to the allowed methods', function () use ($reply) {
        $response = ['output' => ['rateReplyDetails' => [
            $reply('FEDEX_GROUND', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 12.50, 'currency' => 'USD']]),
            $reply('FEDEX_2_DAY', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 30.00, 'currency' => 'USD']]),
            $reply('FEDEX_EXPRESS_SAVER', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 20.00, 'currency' => 'USD']]),
        ]]];

        $result = fedexProbe()->prepareRateResponse($response);
        $methods = array_map(fn($rate) => $rate->getMethod(), $result->getAllRates());

        expect($methods)->toBe(['FEDEX_GROUND', 'FEDEX_2_DAY']);
        expect($result->getAllRates()[0]->getCost())->toBe(12.50);
    });

    it('sorts the returned rates by price', function () use ($reply) {
        $response = ['output' => ['rateReplyDetails' => [
            $reply('PRIORITY_OVERNIGHT', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 55.00]]),
            $reply('FEDEX_GROUND', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 12.50]]),
            $reply('FEDEX_2_DAY', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 30.00]]),
        ]]];

        $result = fedexProbe()->prepareRateResponse($response);
        $methods = array_map(fn($rate) => $rate->getMethod(), $result->getAllRates());

        expect($methods)->toBe(['FEDEX_GROUND', 'FEDEX_2_DAY', 'PRIORITY_OVERNIGHT']);
    });

    it('prefers the negotiated ACCOUNT rate over the published LIST rate', function () use ($reply) {
        $response = ['output' => ['rateReplyDetails' => [
            $reply('FEDEX_GROUND', [
                ['rateType' => 'LIST', 'totalNetCharge' => 20.00],
                ['rateType' => 'ACCOUNT', 'totalNetCharge' => 12.50],
            ]),
        ]]];

        $result = fedexProbe()->prepareRateResponse($response);

        expect($result->getAllRates()[0]->getCost())->toBe(12.50);
    });

    it('falls back to shipmentRateDetail.totalNetCharge when the top-level charge is absent', function () use ($reply) {
        $response = ['output' => ['rateReplyDetails' => [
            $reply('FEDEX_GROUND', [
                ['rateType' => 'ACCOUNT', 'shipmentRateDetail' => ['totalNetCharge' => 17.25, 'currency' => 'USD']],
            ]),
        ]]];

        $result = fedexProbe()->prepareRateResponse($response);

        expect($result->getAllRates()[0]->getCost())->toBe(17.25);
    });

    it('keeps the two international priority services apart rather than collapsing them', function () use ($reply) {
        $probe = fedexProbe([
            'allowed_methods' => 'FEDEX_INTERNATIONAL_PRIORITY,FEDEX_INTERNATIONAL_PRIORITY_EXPRESS',
        ]);
        $response = ['output' => ['rateReplyDetails' => [
            $reply('FEDEX_INTERNATIONAL_PRIORITY', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 88.00]]),
            $reply('FEDEX_INTERNATIONAL_PRIORITY_EXPRESS', [['rateType' => 'ACCOUNT', 'totalNetCharge' => 129.00]]),
        ]]];

        $rates = $probe->prepareRateResponse($response)->getAllRates();

        expect(array_map(fn($rate) => $rate->getMethod(), $rates))
            ->toBe(['FEDEX_INTERNATIONAL_PRIORITY', 'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS']);
        expect(array_map(fn($rate) => $rate->getCost(), $rates))->toBe([88.00, 129.00]);
    });

    it('returns the configured error message when FedEx replies with errors', function () {
        $response = ['errors' => [['code' => 'RATE.PACKAGE.WEIGHT.INVALID', 'message' => 'Weight is invalid.']]];

        $result = fedexProbe()->prepareRateResponse($response);
        $rates = $result->getAllRates();

        expect($rates)->toHaveCount(1);
        expect($rates[0])->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Error::class);
        expect($rates[0]->getErrorMessage())->toBe('This shipping method is currently unavailable.');
    });

    it('returns an error result for an empty or malformed response', function () {
        expect(fedexProbe()->prepareRateResponse([])->getAllRates()[0])
            ->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Error::class);
    });

    it('parses a real sandbox rate response end to end', function () {
        $probe = fedexProbe([
            'allowed_methods' => 'FEDEX_GROUND,FEDEX_EXPRESS_SAVER,FEDEX_2_DAY,FEDEX_2_DAY_AM,'
                . 'STANDARD_OVERNIGHT,PRIORITY_OVERNIGHT,FIRST_OVERNIGHT',
        ]);

        $rates = $probe->prepareRateResponse(fedexFixture('rate-response'))->getAllRates();

        expect($rates)->toHaveCount(7);
        foreach ($rates as $rate) {
            expect($rate)->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Method::class);
        }

        // Cheapest first, and the amounts are the ACCOUNT charges FedEx actually returned.
        expect(array_map(fn($rate) => $rate->getMethod(), $rates))->toBe([
            'FEDEX_GROUND',
            'FEDEX_EXPRESS_SAVER',
            'FEDEX_2_DAY',
            'FEDEX_2_DAY_AM',
            'STANDARD_OVERNIGHT',
            'PRIORITY_OVERNIGHT',
            'FIRST_OVERNIGHT',
        ]);
        expect(array_map(fn($rate) => $rate->getCost(), $rates))->toBe([
            25.18, 100.71, 133.78, 150.43, 193.26, 204.19, 236.74,
        ]);
        expect($rates[0]->getMethodTitle())->toBe('Ground');
    });

    it('drops services missing from allowed_methods when parsing the real response', function () {
        $probe = fedexProbe(['allowed_methods' => 'FEDEX_GROUND']);

        $rates = $probe->prepareRateResponse(fedexFixture('rate-response'))->getAllRates();

        expect($rates)->toHaveCount(1);
        expect($rates[0]->getMethod())->toBe('FEDEX_GROUND');
    });
});

describe('FedEx REST rate endpoint selection', function () {
    $client = function (string $rateEndpoint): Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient {
        $oauth = new Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient('id', 'secret', 'https://example.test');

        return new Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient($oauth, true, false, $rateEndpoint);
    };

    it('uses the standard Rate endpoint by default, matching other platforms', function () use ($client) {
        expect($client(Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_STANDARD)->getRateEndpoint())
            ->toBe('/rate/v1/rates/quotes');
    });

    it('switches to the comprehensive endpoint for Integrator Provider accounts', function () use ($client) {
        expect($client(Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_COMPREHENSIVE)->getRateEndpoint())
            ->toBe('/rate/v1/comprehensiverates/quotes');
    });

    it('falls back to the standard endpoint for an unrecognised config value', function () use ($client) {
        expect($client('')->getRateEndpoint())->toBe('/rate/v1/rates/quotes');
    });

    it('resolves the sandbox and production hosts', function () {
        expect(Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::getBaseUrl(true))
            ->toBe('https://apis-sandbox.fedex.com');
        expect(Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::getBaseUrl(false))
            ->toBe('https://apis.fedex.com');
    });
});

describe('FedEx REST tracking response parsing', function () {
    it('maps a real sandbox track response onto the tracking result', function () {
        $result = fedexProbe()->parseTrackingResponse('122816215025810', fedexTrackFixture());
        $trackings = $result->getAllTrackings();

        expect($trackings)->toHaveCount(1);

        $data = $trackings[0]->getAllData();

        expect($data['status'])->toBe('Delivered');
        expect($data['service'])->toBe('FedEx Ground');
        expect($data['signedby'])->toBe('ROLLINS');
        expect($data['weight'])->toBe('21.5 LB');
        expect($data['deliverylocation'])->toBe('Norton, VA, US');
        // FedEx stamps scan times with the local offset (13:31:00-05:00); like every other
        // carrier the parser renders them in the process timezone, which Maho forces to UTC.
        expect($data['deliverydate'])->toBe('2014-01-09');
        expect($data['deliverytime'])->toBe('18:31:00');
        expect($data['shippeddate'])->toBe('2020-08-15');
    });

    it('turns every scan event into a progress detail row', function () {
        $result = fedexProbe()->parseTrackingResponse('122816215025810', fedexTrackFixture());
        $progress = $result->getAllTrackings()[0]->getAllData()['progressdetail'];

        expect($progress)->toHaveCount(11);
        expect($progress[0]['activity'])->toBe('Delivered');
        expect($progress[0]['deliverydate'])->toBe('2014-01-09');
        expect($progress[0]['deliverytime'])->toBe('18:31:00');
        expect($progress[0]['deliverylocation'])->toBe('Norton, VA, US');
        expect($progress[1]['activity'])->toBe('On FedEx vehicle for delivery');
        expect($progress[1]['deliverylocation'])->toBe('KINGSPORT, TN, US');
    });

    it('appends a tracking error when FedEx reports one for the number', function () {
        $response = ['output' => ['completeTrackResults' => [[
            'trackingNumber' => '999999999999',
            'trackResults' => [[
                'error' => ['code' => 'TRACKING.TRACKINGNUMBER.NOTFOUND', 'message' => 'Tracking number not found'],
            ]],
        ]]]];

        $result = fedexProbe()->parseTrackingResponse('999999999999', $response);
        $errors = $result->getAllTrackings();

        expect($errors[0])->toBeInstanceOf(Mage_Shipping_Model_Tracking_Result_Error::class);
        // getErrorMessage() on the error model is hardcoded to a generic storefront string,
        // so the carrier-specific reason is only observable in the raw data.
        expect($errors[0]->getData('error_message'))->toBe('Tracking number not found');
    });

    it('appends a tracking error for a top-level REST error payload', function () {
        $response = ['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'We could not authorize your credentials.']]];

        $result = fedexProbe()->parseTrackingResponse('122816215025810', $response);

        expect($result->getAllTrackings()[0])->toBeInstanceOf(Mage_Shipping_Model_Tracking_Result_Error::class);
        expect($result->getAllTrackings()[0]->getData('error_message'))
            ->toBe('We could not authorize your credentials.');
    });
});

describe('FedEx REST shipment request payload', function () {
    $shipmentRequest = function (array $overrides = []): \Maho\DataObject {
        $request = new \Maho\DataObject();
        $request->addData(array_merge([
            'store_id' => 1,
            'package_id' => 1,
            'reference_data' => 'Order #100000001 P1',
            'packaging_type' => 'YOUR_PACKAGING',
            'shipping_method' => 'FEDEX_GROUND',
            'package_weight' => 10.0,
            'base_currency_code' => 'USD',
            'shipper_contact_person_name' => 'Jane Shipper',
            'shipper_contact_company_name' => 'Maho',
            'shipper_contact_phone_number' => '9012638716',
            'shipper_address_street1' => '10 FedEx Parkway',
            'shipper_address_street2' => '',
            'shipper_address_city' => 'Collierville',
            'shipper_address_state_or_province_code' => 'TN',
            'shipper_address_postal_code' => '38017',
            'shipper_address_country_code' => 'US',
            'recipient_contact_person_name' => 'John Recipient',
            'recipient_contact_company_name' => 'Acme',
            'recipient_contact_phone_number' => '9012637890',
            'recipient_address_street1' => '20 Rodeo Drive',
            'recipient_address_street2' => '',
            'recipient_address_city' => 'Beverly Hills',
            'recipient_address_state_or_province_code' => 'CA',
            'recipient_address_postal_code' => '90210',
            'recipient_address_country_code' => 'US',
            'package_items' => [],
            'package_params' => new \Maho\DataObject([
                'weight_units' => Mage_Core_Model_Locale::WEIGHT_POUND,
                'dimension_units' => Mage_Core_Model_Locale::LENGTH_INCH,
                'customs_value' => 100.0,
                'delivery_confirmation' => 'ADULT',
                'length' => 12,
                'width' => 10,
                'height' => 8,
            ]),
        ], $overrides));

        return $request;
    };

    it('builds the REST ship envelope with no SOAP auth block', function () use ($shipmentRequest) {
        $payload = fedexProbe()->formShipmentRequest($shipmentRequest());

        expect($payload)->toHaveKey('accountNumber')
            ->and($payload)->toHaveKey('requestedShipment')
            ->and($payload)->not->toHaveKey('WebAuthenticationDetail')
            ->and($payload)->not->toHaveKey('TransactionDetail');

        expect($payload['accountNumber']['value'])->toBe('510087020');
        expect($payload['labelResponseOptions'])->toBe('LABEL');
    });

    it('maps shipper and recipient onto REST contact/address objects', function () use ($shipmentRequest) {
        $shipment = fedexProbe()->formShipmentRequest($shipmentRequest())['requestedShipment'];

        expect($shipment['shipper']['contact']['personName'])->toBe('Jane Shipper');
        expect($shipment['shipper']['address']['city'])->toBe('Collierville');
        expect($shipment['recipients'])->toHaveCount(1);
        expect($shipment['recipients'][0]['contact']['personName'])->toBe('John Recipient');
        expect($shipment['recipients'][0]['address']['postalCode'])->toBe('90210');
    });

    it('requests a label and carries the package weight, dimensions and signature option', function () use ($shipmentRequest) {
        $shipment = fedexProbe()->formShipmentRequest($shipmentRequest())['requestedShipment'];
        $lineItem = $shipment['requestedPackageLineItems'][0];

        expect($shipment['serviceType'])->toBe('FEDEX_GROUND');
        expect($shipment['pickupType'])->toBe('USE_SCHEDULED_PICKUP');
        expect($shipment['shippingChargesPayment']['paymentType'])->toBe('SENDER');
        expect($shipment['labelSpecification']['imageType'])->toBe('PDF');
        expect($lineItem['weight'])->toBe(['units' => 'LB', 'value' => 10.0]);
        expect($lineItem['dimensions'])->toBe(['length' => 12, 'width' => 10, 'height' => 8, 'units' => 'IN']);
        expect($lineItem['packageSpecialServices']['signatureOptionType'])->toBe('ADULT');
    });

    it('bills the recipient for a return shipment', function () use ($shipmentRequest) {
        $shipment = fedexProbe()->formShipmentRequest($shipmentRequest(['is_return' => true]))['requestedShipment'];

        expect($shipment['shippingChargesPayment']['paymentType'])->toBe('RECIPIENT');
    });
});

describe('FedEx REST shipment rollback', function () {
    $ok = ['output' => ['cancelledShipment' => true, 'message' => 'Shipment is successfully cancelled']];
    $refused = ['output' => ['cancelledShipment' => false, 'message' => 'We are unable to process this request.']];

    it('cancels every package it is handed', function () use ($ok) {
        $probe = fedexProbe(['account' => '510087020']);
        $probe->cancelResponses = [$ok, $ok];

        expect($probe->rollBack([
            ['tracking_number' => '794846961014'],
            ['tracking_number' => '794846963407'],
        ]))->toBeTrue();

        expect($probe->cancelRequests)->toHaveCount(2);
        expect($probe->cancelRequests[0])->toBe([
            'accountNumber' => ['value' => '510087020'],
            'trackingNumber' => '794846961014',
            'deletionControl' => 'DELETE_ONE_PACKAGE',
        ]);
        expect($probe->cancelRequests[1]['trackingNumber'])->toBe('794846963407');
    });

    it('reports failure when FedEx refuses a cancel despite answering HTTP 200', function () use ($refused) {
        $probe = fedexProbe();
        $probe->cancelResponses = [$refused];

        expect($probe->rollBack([['tracking_number' => '794846961014']]))->toBeFalse();
    });

    it('still attempts the remaining packages after one is refused', function () use ($ok, $refused) {
        $probe = fedexProbe();
        $probe->cancelResponses = [$refused, $ok];

        expect($probe->rollBack([
            ['tracking_number' => '111111111111'],
            ['tracking_number' => '222222222222'],
        ]))->toBeFalse();

        // A label left live because we gave up early is a label the merchant gets billed for.
        expect($probe->cancelRequests)->toHaveCount(2);
    });

    it('treats an empty or error response as a failed cancel', function () {
        $probe = fedexProbe();
        $probe->cancelResponses = [['errors' => [['code' => 'FORBIDDEN.ERROR', 'message' => 'nope']]]];

        expect($probe->rollBack([['tracking_number' => '794846961014']]))->toBeFalse();
    });
});

describe('FedEx REST credential guard', function () {
    it('collects no rates while the carrier is inactive', function () {
        $probe = fedexProbe(['active' => 0]);

        expect($probe->collectRates(new Mage_Shipping_Model_Rate_Request()))->toBeFalse();
    });

    it('collects no rates when enabled without OAuth credentials', function () {
        $probe = fedexProbe(['active' => 1, 'client_id' => '', 'client_secret' => '']);

        expect($probe->collectRates(new Mage_Shipping_Model_Rate_Request()))->toBeFalse();
    });
});

describe('FedEx REST configuration', function () {
    it('replaces the SOAP credentials with encrypted OAuth credentials', function () {
        $fedex = fedexShippedDefaults();

        expect($fedex->meter_number)->toBeEmpty();
        expect($fedex->key)->toBeEmpty();
        expect($fedex->password)->toBeEmpty();

        foreach (['client_id', 'client_secret'] as $field) {
            expect((string) $fedex->{$field}['backend_model'])
                ->toBe('adminhtml/system_config_backend_encrypted');
        }
    });

    it('exposes Client ID and Client Secret as obscured admin fields', function () {
        $fields = Mage::getConfig()
            ->loadModulesConfiguration('system.xml')
            ->getNode('sections/carriers/groups/fedex/fields');

        expect((string) $fields->client_id->frontend_type)->toBe('obscure');
        expect((string) $fields->client_secret->frontend_type)->toBe('obscure');
        expect($fields->meter_number)->toBeEmpty();
        expect($fields->key)->toBeEmpty();
        expect($fields->password)->toBeEmpty();
    });

    it('defaults dropoff to a REST pickup type and offers only REST pickup types', function () {
        expect((string) fedexShippedDefaults()->dropoff)->toBe('USE_SCHEDULED_PICKUP');

        expect(array_keys(Mage::getModel('usa/shipping_carrier_fedex')->getCode('dropoff')))
            ->toBe([
                'USE_SCHEDULED_PICKUP', 'CONTACT_FEDEX_TO_SCHEDULE', 'DROPOFF_AT_FEDEX_LOCATION',
                'ON_CALL', 'PACKAGE_RETURN_PROGRAM', 'REGULAR_STOP', 'TAG',
            ]);
    });

    it('ships canonical REST service codes in the default allowed_methods', function () {
        $allowed = explode(',', (string) fedexShippedDefaults()->allowed_methods);

        expect($allowed)->toContain('FEDEX_INTERNATIONAL_PRIORITY');
        expect($allowed)->toContain('FEDEX_INTERNATIONAL_PRIORITY_EXPRESS');
        expect($allowed)->not->toContain('INTERNATIONAL_PRIORITY');
        // Rate API does not quote freight, and FedEx Freight is a separate company since 2026.
        expect($allowed)->not->toContain('FEDEX_FREIGHT');
        expect($allowed)->not->toContain('FEDEX_NATIONAL_FREIGHT');
    });

    it('defaults to the standard rate endpoint and offers the comprehensive one', function () {
        expect((string) fedexShippedDefaults()->rate_endpoint)
            ->toBe(Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_ENDPOINT_STANDARD);

        expect(array_keys(Mage::getModel('usa/shipping_carrier_fedex')->getCode('rate_endpoint')))
            ->toBe(['standard', 'comprehensive']);
    });

    it('defaults the weight unit so the rate payload never sends a null unit', function () {
        // FedEx rejects weight.units: null outright, and this node had no default at all,
        // so an unsaved FedEx config produced an unquotable request.
        expect((string) fedexShippedDefaults()->unit_of_measure)->toBe('LB');

        $probe = fedexProbe(['unit_of_measure' => (string) fedexShippedDefaults()->unit_of_measure]);
        $probe->setRawRequest(fedexRawRateRequest());
        $shipment = $probe->formRateRequest(
            Mage_Usa_Model_Shipping_Carrier_Fedex::RATE_REQUEST_GENERAL,
        )['requestedShipment'];

        expect($shipment['requestedPackageLineItems'][0]['weight']['units'])->toBe('LB');
    });

    it('no longer ships the SOAP WSDL files', function () {
        expect(is_dir(Mage::getModuleDir('etc', 'Mage_Usa') . DS . 'wsdl' . DS . 'FedEx'))->toBeFalse();
    });
});
