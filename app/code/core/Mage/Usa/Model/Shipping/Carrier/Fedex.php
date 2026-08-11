<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

class Mage_Usa_Model_Shipping_Carrier_Fedex extends Mage_Usa_Model_Shipping_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    public const CODE = 'fedex';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    public const RATE_REQUEST_GENERAL = 'general';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    public const RATE_REQUEST_SMARTPOST = 'SMART_POST';

    /**
     * REST renamed this SOAP service code; pre-migration orders still hold the
     * old one in shipping_method, and order rows are never rewritten.
     */
    protected const SERVICE_TYPE_ALIASES = [
        'INTERNATIONAL_PRIORITY' => 'FEDEX_INTERNATIONAL_PRIORITY',
    ];

    /**
     * Rate endpoints. The standard one suits a normal shipping account; FedEx requires
     * registered Integrator Providers to use the comprehensive one instead, and answers
     * 403 on the other. Both take the same payload and return the same response shape.
     */
    public const RATE_ENDPOINT_STANDARD = 'standard';
    public const RATE_ENDPOINT_COMPREHENSIVE = 'comprehensive';

    /**
     * Preference order for the rate flavours FedEx returns per service, best first.
     * ACCOUNT is the negotiated rate, LIST the published one.
     */
    protected const RATE_TYPE_PREFERENCE = ['ACCOUNT', 'PREFERRED', 'INCENTIVE', 'LIST'];

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * Rate request data
     *
     * @var Mage_Shipping_Model_Rate_Request|null
     */
    protected $_request = null;

    /**
     * Raw rate request data
     *
     * @var \Maho\DataObject|null
     */
    protected $_rawRequest = null;

    /**
     * Rate result data
     *
     * @var Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result|null
     */
    protected $_result = null;

    /**
     * Container types that could be customized for FedEx carrier
     *
     * @var array
     */
    protected $_customizableContainerTypes = ['YOUR_PACKAGING'];

    protected ?Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient $_restClient = null;

    /**
     * Collect and get rates
     *
     * @return Mage_Shipping_Model_Rate_Result|bool|null
     */
    #[\Override]
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag($this->_activeFlag)) {
            return false;
        }
        // Without credentials every quote would pay for a doomed OAuth round trip, so bail
        // out here instead of rendering the generic carrier error on each cart.
        if (!$this->getConfigData('client_id') || !$this->getConfigData('client_secret')) {
            Mage::log('FedEx is enabled but has no Client ID/Secret configured; skipping rates.', Mage::LOG_WARNING);
            return false;
        }
        $this->setRequest($request);

        $this->_getQuotes();

        $this->_updateFreeMethodQuote($request);

        return $this->getResult();
    }

    /**
     * Prepare and set request to this instance
     *
     * @return $this
     */
    public function setRequest(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->_request = $request;

        $r = new \Maho\DataObject();

        if ($request->getLimitMethod()) {
            $r->setService($request->getLimitMethod());
        }

        if ($request->getFedexAccount()) {
            $account = $request->getFedexAccount();
        } else {
            $account = $this->getConfigData('account');
        }
        $r->setAccount($account);

        if ($request->getFedexDropoff()) {
            $dropoff = $request->getFedexDropoff();
        } else {
            $dropoff = $this->getConfigData('dropoff');
        }
        $r->setDropoffType($dropoff);

        if ($request->getFedexPackaging()) {
            $packaging = $request->getFedexPackaging();
        } else {
            $packaging = $this->getConfigData('packaging');
        }
        $r->setPackaging($packaging);

        if ($request->getOrigCountry()) {
            $origCountry = $request->getOrigCountry();
        } else {
            $origCountry = Mage::getStoreConfig(
                Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID,
                $request->getStoreId(),
            );
        }
        $r->setOrigCountry(Mage::getModel('directory/country')->load($origCountry)->getIso2Code());

        if ($request->getOrigPostcode()) {
            $r->setOrigPostal($request->getOrigPostcode());
        } else {
            $r->setOrigPostal(Mage::getStoreConfig(
                Mage_Shipping_Model_Shipping::XML_PATH_STORE_ZIP,
                $request->getStoreId(),
            ));
        }

        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = self::USA_COUNTRY_ID;
        }
        $r->setDestCountry(Mage::getModel('directory/country')->load($destCountry)->getIso2Code());

        if ($request->getDestPostcode()) {
            $r->setDestPostal($request->getDestPostcode());
        }

        $weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
        $r->setWeight($weight);
        if ($request->getFreeMethodWeight() != $request->getPackageWeight()) {
            $r->setFreeMethodWeight($request->getFreeMethodWeight());
        }

        $r->setValue($request->getPackagePhysicalValue());
        $r->setValueWithDiscount($request->getPackageValueWithDiscount());

        $r->setIsReturn($request->getIsReturn());

        $r->setBaseSubtotalInclTax($request->getBaseSubtotalInclTax());

        $this->_rawRequest = $r;

        return $this;
    }

    /**
     * Get result of request
     *
     * @return Mage_Shipping_Model_Rate_Result|null
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Get a REST client bound to the configured credentials and environment
     */
    protected function _getRestClient(): Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient
    {
        if ($this->_restClient === null) {
            $sandbox = (bool) $this->getConfigFlag('sandbox_mode');
            $oauthClient = new Mage_Usa_Model_Shipping_Carrier_Fedex_OAuthClient(
                (string) $this->getConfigData('client_id'),
                (string) $this->getConfigData('client_secret'),
                Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::getBaseUrl($sandbox),
            );
            $this->_restClient = new Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient(
                $oauthClient,
                $sandbox,
                (bool) $this->getConfigFlag('debug'),
                (string) ($this->getConfigData('rate_endpoint') ?: self::RATE_ENDPOINT_STANDARD),
            );
        }

        return $this->_restClient;
    }

    /**
     * Resolve the REST pickupType for a configured dropoff value
     */
    protected function _getPickupType(?string $dropoffType): string
    {
        return $dropoffType ?: 'USE_SCHEDULED_PICKUP';
    }

    /**
     * Forming request for rate estimation depending to the purpose
     *
     * @param string $purpose
     * @return array
     */
    protected function _formRateRequest($purpose)
    {
        $r = $this->_rawRequest;
        $currencyCode = $this->getCurrencyCode();
        $weight = (float) $r->getWeight();
        $value = (float) $r->getValue();

        $requestedShipment = [
            'shipper' => [
                'address' => [
                    'postalCode'  => $r->getOrigPostal(),
                    'countryCode' => $r->getOrigCountry(),
                ],
            ],
            'recipient' => [
                'address' => [
                    'postalCode'  => $r->getDestPostal(),
                    'countryCode' => $r->getDestCountry(),
                    'residential' => (bool) $this->getConfigData('residence_delivery'),
                ],
            ],
            // FedEx reads the ship date in the shipper's timezone, not UTC
            'shipDateStamp' => Mage::app()->getLocale()->utcToStore()
                ->format(Mage_Core_Model_Locale::DATE_FORMAT),
            'pickupType' => $this->_getPickupType($r->getDropoffType()),
            'packagingType' => $r->getPackaging(),
            'rateRequestType' => ['ACCOUNT', 'LIST'],
            'totalPackageCount' => 1,
            'requestedPackageLineItems' => [
                [
                    'groupPackageCount' => 1,
                    'weight' => [
                        'units' => $this->getConfigData('unit_of_measure'),
                        'value' => $weight,
                    ],
                ],
            ],
        ];

        if ($r->getOrigCountry() !== $r->getDestCountry()) {
            $requestedShipment['customsClearanceDetail'] = [
                'commodities' => [
                    [
                        'customsValue' => [
                            'amount' => $value,
                            'currency' => $currencyCode,
                        ],
                    ],
                ],
            ];
        }

        if ($purpose == self::RATE_REQUEST_GENERAL) {
            $requestedShipment['requestedPackageLineItems'][0]['declaredValue'] = [
                'amount' => $value,
                'currency' => $currencyCode,
            ];
        } elseif ($purpose == self::RATE_REQUEST_SMARTPOST) {
            $requestedShipment['serviceType'] = self::RATE_REQUEST_SMARTPOST;
            $requestedShipment['smartPostInfoDetail'] = [
                'indicia' => $weight >= 1 ? 'PARCEL_SELECT' : 'PRESORTED_STANDARD',
                'hubId' => $this->getConfigData('smartpost_hubid'),
            ];
        }

        return [
            'accountNumber' => ['value' => $r->getAccount()],
            'rateRequestControlParameters' => ['returnTransitTimes' => false],
            'requestedShipment' => $requestedShipment,
        ];
    }

    /**
     * Makes remote request to the carrier and returns a response
     *
     * @param string $purpose
     * @return array
     */
    protected function _doRatesRequest($purpose)
    {
        $ratesRequest = $this->_formRateRequest($purpose);
        $requestString = serialize($ratesRequest);
        $cached = $this->_getCachedQuotes($requestString);

        if ($cached === null) {
            $response = $this->_getRestClient()->getRates($ratesRequest);
            if ($response !== [] && !isset($response['errors'])) {
                $this->_setCachedQuotes($requestString, serialize($response));
            }
        } else {
            $response = unserialize($cached, ['allowed_classes' => false]);
            if (!is_array($response)) {
                $response = [];
            }
        }

        return $response;
    }

    /**
     * Do remote request for and handle errors
     *
     * @return Mage_Shipping_Model_Rate_Result|bool
     */
    protected function _getQuotes()
    {
        $this->_result = Mage::getModel('shipping/rate_result');
        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));
        if (in_array(self::RATE_REQUEST_SMARTPOST, $allowedMethods)) {
            $response = $this->_doRatesRequest(self::RATE_REQUEST_SMARTPOST);
            $preparedSmartpost = $this->_prepareRateResponse($response);
            $this->_result->append($preparedSmartpost);
        }
        $response = $this->_doRatesRequest(self::RATE_REQUEST_GENERAL);
        $preparedGeneral = $this->_prepareRateResponse($response);
        if ($this->_result->getError() && $preparedGeneral->getError()) {
            return $this->_result->getError();
        }
        $this->_result->append($preparedGeneral);
        $this->_removeErrorsIfRateExist();

        return $this->_result;
    }

    /**
     * Remove Errors in Case When Rate Exist
     *
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function _removeErrorsIfRateExist()
    {
        $rateResultExist = false;
        $rates           = [];
        foreach ($this->_result->getAllRates() as $rate) {
            if (!($rate instanceof Mage_Shipping_Model_Rate_Result_Error)) {
                $rateResultExist = true;
                $rates[] = $rate;
            }
        }

        if ($rateResultExist) {
            $this->_result->reset();
            $this->_result->setError(false);
            foreach ($rates as $rate) {
                $this->_result->append($rate);
            }
        }

        return $this->_result;
    }

    /**
     * Prepare shipping rate result based on response
     *
     * @param array $response
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function _prepareRateResponse($response)
    {
        $costArr = [];
        $priceArr = [];

        if (is_array($response) && !isset($response['errors'])) {
            $allowedMethods = explode(',', (string) $this->getConfigData('allowed_methods'));

            foreach ($response['output']['rateReplyDetails'] ?? [] as $rate) {
                if (!is_array($rate) || empty($rate['serviceType'])) {
                    continue;
                }
                $serviceName = (string) $rate['serviceType'];
                if (!in_array($serviceName, $allowedMethods)) {
                    continue;
                }
                $amount = $this->_getRateAmountOriginBased($rate);
                if ($amount === null) {
                    continue;
                }
                $costArr[$serviceName]  = $amount;
                $priceArr[$serviceName] = $this->getMethodPrice($amount, $serviceName);
            }
            asort($priceArr);
        }

        $result = Mage::getModel('shipping/rate_result');
        if (empty($priceArr)) {
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            $result->append($error);
        } else {
            foreach ($priceArr as $method => $price) {
                $rate = Mage::getModel('shipping/rate_result_method');
                $rate->setCarrier($this->_code);
                $rate->setCarrierTitle($this->getConfigData('title'));
                $rate->setMethod($method);
                $rate->setMethodTitle($this->getCode('method', $method));
                $rate->setCost($costArr[$method]);
                $rate->setPrice($price);
                $result->append($rate);
            }
        }
        return $result;
    }

    /**
     * Get origin based amount form response of rate estimation
     *
     * @param array $rate
     * @return null|float
     */
    protected function _getRateAmountOriginBased($rate)
    {
        $rateTypeAmounts = [];

        foreach ($rate['ratedShipmentDetails'] ?? [] as $ratedShipmentDetail) {
            if (!is_array($ratedShipmentDetail)) {
                continue;
            }
            $netAmount = $ratedShipmentDetail['totalNetCharge']
                ?? $ratedShipmentDetail['shipmentRateDetail']['totalNetCharge']
                ?? null;
            if ($netAmount === null) {
                continue;
            }
            $rateTypeAmounts[(string) ($ratedShipmentDetail['rateType'] ?? '')] = (float) $netAmount;
        }

        if ($rateTypeAmounts === []) {
            return null;
        }

        // A zero amount is an unpriced flavour, not free shipping; fall through
        foreach (self::RATE_TYPE_PREFERENCE as $rateType) {
            if (!empty($rateTypeAmounts[$rateType])) {
                return $rateTypeAmounts[$rateType];
            }
        }

        return reset($rateTypeAmounts);
    }

    /**
     * Set free method request
     *
     * @param  $freeMethod
     */
    protected function _setFreeMethodRequest($freeMethod)
    {
        $r = $this->_rawRequest;
        $weight = $this->getTotalNumOfBoxes($r->getFreeMethodWeight());
        $r->setWeight($weight);
        $r->setService($freeMethod);
    }

    /**
     * Get configuration data of carrier
     *
     * @param string $type
     * @param string $code
     * @return array|bool
     */
    public function getCode($type, $code = '')
    {
        $codes = [
            'method' => [
                'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => Mage::helper('usa')->__('Europe First Priority'),
                'FEDEX_1_DAY_FREIGHT'                 => Mage::helper('usa')->__('1 Day Freight'),
                'FEDEX_2_DAY_FREIGHT'                 => Mage::helper('usa')->__('2 Day Freight'),
                'FEDEX_2_DAY'                         => Mage::helper('usa')->__('2 Day'),
                'FEDEX_2_DAY_AM'                      => Mage::helper('usa')->__('2 Day AM'),
                'FEDEX_3_DAY_FREIGHT'                 => Mage::helper('usa')->__('3 Day Freight'),
                'FEDEX_EXPRESS_SAVER'                 => Mage::helper('usa')->__('Express Saver'),
                'FEDEX_GROUND'                        => Mage::helper('usa')->__('Ground'),
                'FIRST_OVERNIGHT'                     => Mage::helper('usa')->__('First Overnight'),
                'GROUND_HOME_DELIVERY'                => Mage::helper('usa')->__('Home Delivery'),
                'INTERNATIONAL_ECONOMY'               => Mage::helper('usa')->__('International Economy'),
                'INTERNATIONAL_ECONOMY_FREIGHT'       => Mage::helper('usa')->__('Intl Economy Freight'),
                'INTERNATIONAL_FIRST'                 => Mage::helper('usa')->__('International First'),
                'INTERNATIONAL_GROUND'                => Mage::helper('usa')->__('International Ground'),
                'FEDEX_INTERNATIONAL_PRIORITY'         => Mage::helper('usa')->__('International Priority'),
                'FEDEX_INTERNATIONAL_PRIORITY_EXPRESS' => Mage::helper('usa')->__('International Priority Express'),
                'FEDEX_FIRST'                         => Mage::helper('usa')->__('First'),
                'FEDEX_PRIORITY'                      => Mage::helper('usa')->__('Priority'),
                'FEDEX_PRIORITY_EXPRESS'              => Mage::helper('usa')->__('Priority Express'),
                'FEDEX_PRIORITY_EXPRESS_FREIGHT'      => Mage::helper('usa')->__('Priority Express Freight'),
                'FEDEX_PRIORITY_FREIGHT'              => Mage::helper('usa')->__('Priority Freight'),
                'FEDEX_ECONOMY_SELECT'                => Mage::helper('usa')->__('Economy Select'),
                'INTERNATIONAL_PRIORITY_FREIGHT'      => Mage::helper('usa')->__('Intl Priority Freight'),
                'PRIORITY_OVERNIGHT'                  => Mage::helper('usa')->__('Priority Overnight'),
                'SMART_POST'                          => Mage::helper('usa')->__('Ground Economy'),
                'STANDARD_OVERNIGHT'                  => Mage::helper('usa')->__('Standard Overnight'),
                'FEDEX_FREIGHT'                       => Mage::helper('usa')->__('Freight'),
                'FEDEX_NATIONAL_FREIGHT'              => Mage::helper('usa')->__('National Freight'),
            ],
            'dropoff' => [
                'USE_SCHEDULED_PICKUP'      => Mage::helper('usa')->__('Use Scheduled Pickup'),
                'CONTACT_FEDEX_TO_SCHEDULE' => Mage::helper('usa')->__('Contact FedEx to Schedule'),
                'DROPOFF_AT_FEDEX_LOCATION' => Mage::helper('usa')->__('Dropoff at FedEx Location'),
                'ON_CALL'                   => Mage::helper('usa')->__('On Call'),
                'PACKAGE_RETURN_PROGRAM'    => Mage::helper('usa')->__('Package Return Program'),
                'REGULAR_STOP'              => Mage::helper('usa')->__('Regular Stop'),
                'TAG'                       => Mage::helper('usa')->__('Tag'),
            ],
            'rate_endpoint' => [
                self::RATE_ENDPOINT_STANDARD      => Mage::helper('usa')->__('Rates and Transit Times'),
                self::RATE_ENDPOINT_COMPREHENSIVE => Mage::helper('usa')->__('Comprehensive Rates and Transit Times'),
            ],
            'packaging' => [
                'FEDEX_ENVELOPE' => Mage::helper('usa')->__('FedEx Envelope'),
                'FEDEX_PAK'      => Mage::helper('usa')->__('FedEx Pak'),
                'FEDEX_BOX'      => Mage::helper('usa')->__('FedEx Box'),
                'FEDEX_TUBE'     => Mage::helper('usa')->__('FedEx Tube'),
                'FEDEX_10KG_BOX' => Mage::helper('usa')->__('FedEx 10kg Box'),
                'FEDEX_25KG_BOX' => Mage::helper('usa')->__('FedEx 25kg Box'),
                'YOUR_PACKAGING' => Mage::helper('usa')->__('Your Packaging'),
            ],
            'containers_filter' => [
                [
                    'containers' => ['FEDEX_ENVELOPE', 'FEDEX_PAK'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'FEDEX_EXPRESS_SAVER',
                                'FEDEX_2_DAY',
                                'FEDEX_2_DAY_AM',
                                'STANDARD_OVERNIGHT',
                                'PRIORITY_OVERNIGHT',
                                'FIRST_OVERNIGHT',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'INTERNATIONAL_FIRST',
                                'INTERNATIONAL_ECONOMY',
                                'FEDEX_INTERNATIONAL_PRIORITY',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['FEDEX_BOX', 'FEDEX_TUBE'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'FEDEX_2_DAY',
                                'FEDEX_2_DAY_AM',
                                'STANDARD_OVERNIGHT',
                                'PRIORITY_OVERNIGHT',
                                'FIRST_OVERNIGHT',
                                'FEDEX_FREIGHT',
                                'FEDEX_1_DAY_FREIGHT',
                                'FEDEX_2_DAY_FREIGHT',
                                'FEDEX_3_DAY_FREIGHT',
                                'FEDEX_NATIONAL_FREIGHT',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'INTERNATIONAL_FIRST',
                                'INTERNATIONAL_ECONOMY',
                                'FEDEX_INTERNATIONAL_PRIORITY',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['FEDEX_10KG_BOX', 'FEDEX_25KG_BOX'],
                    'filters'    => [
                        'within_us' => [],
                        'from_us' => ['method' => ['FEDEX_INTERNATIONAL_PRIORITY']],
                    ],
                ],
                [
                    'containers' => ['YOUR_PACKAGING'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'FEDEX_GROUND',
                                'GROUND_HOME_DELIVERY',
                                'SMART_POST',
                                'FEDEX_EXPRESS_SAVER',
                                'FEDEX_2_DAY',
                                'FEDEX_2_DAY_AM',
                                'STANDARD_OVERNIGHT',
                                'PRIORITY_OVERNIGHT',
                                'FIRST_OVERNIGHT',
                                'FEDEX_FREIGHT',
                                'FEDEX_1_DAY_FREIGHT',
                                'FEDEX_2_DAY_FREIGHT',
                                'FEDEX_3_DAY_FREIGHT',
                                'FEDEX_NATIONAL_FREIGHT',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'INTERNATIONAL_FIRST',
                                'INTERNATIONAL_ECONOMY',
                                'FEDEX_INTERNATIONAL_PRIORITY',
                                'INTERNATIONAL_GROUND',
                                'FEDEX_FREIGHT',
                                'FEDEX_1_DAY_FREIGHT',
                                'FEDEX_2_DAY_FREIGHT',
                                'FEDEX_3_DAY_FREIGHT',
                                'FEDEX_NATIONAL_FREIGHT',
                                'INTERNATIONAL_ECONOMY_FREIGHT',
                                'INTERNATIONAL_PRIORITY_FREIGHT',
                            ],
                        ],
                    ],
                ],
            ],

            'delivery_confirmation_types' => [
                'NO_SIGNATURE_REQUIRED' => Mage::helper('usa')->__('Not Required'),
                'ADULT'                 => Mage::helper('usa')->__('Adult'),
                'DIRECT'                => Mage::helper('usa')->__('Direct'),
                'INDIRECT'              => Mage::helper('usa')->__('Indirect'),
            ],

            'unit_of_measure' => [
                'LB'   =>  Mage::helper('usa')->__('Pounds'),
                'KG'   =>  Mage::helper('usa')->__('Kilograms'),
            ],
        ];
        if (!isset($codes[$type])) {
            return false;
        }

        if ($code === '') {
            return $codes[$type];
        }

        return $codes[$type][$code] ?? false;
    }

    /**
     *  Return FeDex currency ISO code by Maho Base Currency Code
     *
     *  @return string 3-digit currency code
     */
    public function getCurrencyCode()
    {
        $codes = [
            'DOP' => 'RDD', // Dominican Peso
            'XCD' => 'ECD', // Caribbean Dollars
            'ARS' => 'ARN', // Argentina Peso
            'SGD' => 'SID', // Singapore Dollars
            'KRW' => 'WON', // South Korea Won
            'JMD' => 'JAD', // Jamaican Dollars
            'CHF' => 'SFR', // Swiss Francs
            'JPY' => 'JYE', // Japanese Yen
            'KWD' => 'KUD', // Kuwaiti Dinars
            'GBP' => 'UKL', // British Pounds
            'AED' => 'DHS', // UAE Dirhams
            'MXN' => 'NMP', // Mexican Pesos
            'UYU' => 'UYP', // Uruguay New Pesos
            'CLP' => 'CHP', // Chilean Pesos
            'TWD' => 'NTD', // New Taiwan Dollars
        ];
        $currencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
        return $codes[$currencyCode] ?? $currencyCode;
    }

    /**
     * Get tracking
     *
     * @param mixed $trackings
     * @return Mage_Shipping_Model_Rate_Result|null
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings = [$trackings];
        }

        foreach ($trackings as $tracking) {
            $this->_doTrackingRequest((string) $tracking);
        }

        return $this->_result;
    }

    /**
     * Send request for tracking
     */
    protected function _doTrackingRequest(string $tracking): void
    {
        $requestString = serialize(['track' => $tracking]);
        $cached = $this->_getCachedQuotes($requestString);

        if ($cached === null) {
            $response = $this->_getRestClient()->track($tracking);
            if ($response !== [] && !isset($response['errors'])) {
                $this->_setCachedQuotes($requestString, serialize($response));
            }
        } else {
            $response = unserialize($cached, ['allowed_classes' => false]);
            if (!is_array($response)) {
                $response = [];
            }
        }

        $this->_parseTrackingResponse($tracking, $response);
    }

    /**
     * Parse tracking response
     */
    protected function _parseTrackingResponse(string $trackingValue, array $response): void
    {
        $errorTitle = Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::extractErrorMessage($response);
        $resultArray = null;

        $trackInfo = $response['output']['completeTrackResults'][0]['trackResults'][0] ?? null;
        if (is_array($trackInfo)) {
            if (isset($trackInfo['error'])) {
                $errorTitle = $trackInfo['error']['message'] ?? $trackInfo['error']['code'] ?? null;
            } else {
                $resultArray = $this->_extractTrackingData($trackInfo);
            }
        }

        if (!$this->_result) {
            $this->_result = Mage::getModel('shipping/tracking_result');
        }

        if ($resultArray !== null) {
            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->setTracking($trackingValue);
            $tracking->addData($resultArray);
            $this->_result->append($tracking);
        } else {
            $error = Mage::getModel('shipping/tracking_result_error');
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setTracking($trackingValue);
            $error->setErrorMessage($errorTitle ?: Mage::helper('usa')->__('Unable to retrieve tracking'));
            $this->_result->append($error);
        }
    }

    /**
     * Flatten a REST trackResults entry into the shipping/tracking_result_status shape
     */
    protected function _extractTrackingData(array $trackInfo): array
    {
        $resultArray = [
            'status' => (string) ($trackInfo['latestStatusDetail']['statusByLocale']
                ?? $trackInfo['latestStatusDetail']['description'] ?? ''),
            'service' => (string) ($trackInfo['serviceDetail']['description']
                ?? $trackInfo['serviceDetail']['type'] ?? ''),
        ];

        $dateAndTimes = [];
        foreach ($trackInfo['dateAndTimes'] ?? [] as $entry) {
            if (isset($entry['type'], $entry['dateTime'])) {
                $dateAndTimes[(string) $entry['type']] = (string) $entry['dateTime'];
            }
        }

        $deliveryTimestamp = strtotime(
            $dateAndTimes['ACTUAL_DELIVERY']
            ?? $dateAndTimes['ESTIMATED_DELIVERY']
            ?? $trackInfo['estimatedDeliveryTimeWindow']['window']['ends'] ?? '',
        );
        if ($deliveryTimestamp) {
            $resultArray['deliverydate'] = date(Mage_Core_Model_Locale::DATE_FORMAT, $deliveryTimestamp);
            $resultArray['deliverytime'] = date('H:i:s', $deliveryTimestamp);
        }

        $shipTimestamp = strtotime($dateAndTimes['SHIP'] ?? $dateAndTimes['ACTUAL_PICKUP'] ?? '');
        if ($shipTimestamp) {
            $resultArray['shippeddate'] = date(Mage_Core_Model_Locale::DATE_FORMAT, $shipTimestamp);
        }

        $deliveryLocation = $this->_formatTrackingAddress(
            $trackInfo['deliveryDetails']['actualDeliveryAddress']
            ?? $trackInfo['lastUpdatedDestinationAddress']
            ?? [],
        );
        if ($deliveryLocation !== '') {
            $resultArray['deliverylocation'] = $deliveryLocation;
        }

        if (!empty($trackInfo['deliveryDetails']['receivedByName'])) {
            $resultArray['signedby'] = (string) $trackInfo['deliveryDetails']['receivedByName'];
        }

        $weight = $trackInfo['packageDetails']['weightAndDimensions']['weight'][0] ?? null;
        if (isset($weight['value'], $weight['unit'])) {
            $resultArray['weight'] = "{$weight['value']} {$weight['unit']}";
        }

        $packageProgress = [];
        foreach ($trackInfo['scanEvents'] ?? [] as $event) {
            if (!is_array($event)) {
                continue;
            }
            $tempArray = ['activity' => (string) ($event['eventDescription'] ?? '')];
            $timestamp = strtotime((string) ($event['date'] ?? ''));
            if ($timestamp) {
                $tempArray['deliverydate'] = date(Mage_Core_Model_Locale::DATE_FORMAT, $timestamp);
                $tempArray['deliverytime'] = date('H:i:s', $timestamp);
            }
            $location = $this->_formatTrackingAddress($event['scanLocation'] ?? []);
            if ($location !== '') {
                $tempArray['deliverylocation'] = $location;
            }
            $packageProgress[] = $tempArray;
        }
        $resultArray['progressdetail'] = $packageProgress;

        return $resultArray;
    }

    /**
     * Render a REST address object as "City, State, Country"
     */
    protected function _formatTrackingAddress(array $address): string
    {
        $parts = [];
        foreach (['city', 'stateOrProvinceCode', 'countryCode'] as $key) {
            if (!empty($address[$key])) {
                $parts[] = (string) $address[$key];
            }
        }

        return implode(', ', $parts);
    }

    /**
     * Get tracking response
     *
     * @return string
     */
    public function getResponse()
    {
        $statuses = '';
        if ($this->_result instanceof Mage_Shipping_Model_Tracking_Result) {
            if ($trackings = $this->_result->getAllTrackings()) {
                foreach ($trackings as $tracking) {
                    if ($data = $tracking->getAllData()) {
                        if (!empty($data['status'])) {
                            $statuses .= Mage::helper('usa')->__($data['status']) . "\n<br>";
                        } else {
                            $statuses .= Mage::helper('usa')->__('Empty response') . "\n<br>";
                        }
                    }
                }
            }
        }
        if (empty($statuses)) {
            $statuses = Mage::helper('usa')->__('Empty response');
        }
        return $statuses;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    #[\Override]
    public function getAllowedMethods()
    {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = [];
        foreach ($allowed as $k) {
            $arr[$k] = $this->getCode('method', $k);
        }
        return $arr;
    }

    /**
     * Form array with appropriate structure for shipment request
     *
     * @return array
     */
    protected function _formShipmentRequest(\Maho\DataObject $request)
    {
        if ($request->getReferenceData()) {
            $referenceData = $request->getReferenceData() . $request->getPackageId();
        } else {
            $referenceData = 'Order #'
                             . $request->getOrderShipment()->getOrder()->getIncrementId()
                             . ' P'
                             . $request->getPackageId();
        }
        $packageParams = $request->getPackageParams();
        $customsValue = $packageParams->getCustomsValue();
        $height = $packageParams->getHeight();
        $width = $packageParams->getWidth();
        $length = $packageParams->getLength();
        $weightUnits = $packageParams->getWeightUnits() == Mage_Core_Model_Locale::WEIGHT_POUND ? 'LB' : 'KG';
        $dimensionsUnits = $packageParams->getDimensionUnits() == Mage_Core_Model_Locale::LENGTH_INCH ? 'IN' : 'CM';
        $unitPrice = 0;
        $itemsQty = 0;
        $itemsDesc = [];
        $countriesOfManufacture = [];
        $productIds = [];
        $packageItems = $request->getPackageItems();
        foreach ($packageItems as $itemShipment) {
            $item = new \Maho\DataObject();
            $item->setData($itemShipment);

            $unitPrice  += $item->getPrice();
            $itemsQty   += $item->getQty();

            $itemsDesc[]    = $item->getName();
            $productIds[]   = $item->getProductId();
        }

        // get countries of manufacture
        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->addStoreFilter($request->getStoreId())
            ->addFieldToFilter('entity_id', ['in' => $productIds])
            ->addAttributeToSelect('country_of_manufacture');
        foreach ($productCollection as $product) {
            $countriesOfManufacture[] = $product->getCountryOfManufacture();
        }

        $paymentType = $request->getIsReturn() ? 'RECIPIENT' : 'SENDER';
        $originCountry = Mage::getStoreConfig(
            Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID,
            $request->getStoreId(),
        );

        $packageLineItem = [
            'sequenceNumber' => 1,
            'weight' => [
                'units' => $weightUnits,
                'value' => (float) $request->getPackageWeight(),
            ],
            'customerReferences' => [
                [
                    'customerReferenceType' => 'CUSTOMER_REFERENCE',
                    'value' => $referenceData,
                ],
            ],
        ];

        if ($packageParams->getDeliveryConfirmation()) {
            $packageLineItem['packageSpecialServices'] = [
                'specialServiceTypes' => ['SIGNATURE_OPTION'],
                'signatureOptionType' => $packageParams->getDeliveryConfirmation(),
            ];
        }

        if ($length || $width || $height) {
            $packageLineItem['dimensions'] = [
                'length' => $length,
                'width'  => $width,
                'height' => $height,
                'units'  => $dimensionsUnits,
            ];
        }

        $requestedShipment = [
            // Not a typo: the Ship API spells it shipDatestamp while Rate uses shipDateStamp.
            // Store-local date: FedEx reads it in the shipper's timezone.
            'shipDatestamp' => Mage::app()->getLocale()->utcToStore($request->getStoreId())
                ->format(Mage_Core_Model_Locale::DATE_FORMAT),
            'pickupType' => $this->_getPickupType($this->getConfigData('dropoff')),
            'packagingType' => $request->getPackagingType(),
            'serviceType' => self::SERVICE_TYPE_ALIASES[$request->getShippingMethod()]
                ?? $request->getShippingMethod(),
            'shipper' => [
                'contact' => [
                    'personName' => $request->getShipperContactPersonName(),
                    'companyName' => $request->getShipperContactCompanyName(),
                    'phoneNumber' => $request->getShipperContactPhoneNumber(),
                ],
                'address' => [
                    'streetLines' => array_values(array_filter([
                        $request->getShipperAddressStreet1(),
                        $request->getShipperAddressStreet2(),
                    ])),
                    'city' => $request->getShipperAddressCity(),
                    'stateOrProvinceCode' => $request->getShipperAddressStateOrProvinceCode(),
                    'postalCode' => $request->getShipperAddressPostalCode(),
                    'countryCode' => $request->getShipperAddressCountryCode(),
                ],
            ],
            'recipients' => [
                [
                    'contact' => [
                        'personName' => $request->getRecipientContactPersonName(),
                        'companyName' => $request->getRecipientContactCompanyName(),
                        'phoneNumber' => $request->getRecipientContactPhoneNumber(),
                    ],
                    'address' => [
                        'streetLines' => array_values(array_filter([
                            $request->getRecipientAddressStreet1(),
                            $request->getRecipientAddressStreet2(),
                        ])),
                        'city' => $request->getRecipientAddressCity(),
                        'stateOrProvinceCode' => $request->getRecipientAddressStateOrProvinceCode(),
                        'postalCode' => $request->getRecipientAddressPostalCode(),
                        'countryCode' => $request->getRecipientAddressCountryCode(),
                        'residential' => (bool) $this->getConfigData('residence_delivery'),
                    ],
                ],
            ],
            'shippingChargesPayment' => [
                'paymentType' => $paymentType,
                'payor' => [
                    'responsibleParty' => [
                        'accountNumber' => ['value' => $this->getConfigData('account')],
                        'address' => ['countryCode' => $originCountry],
                    ],
                ],
            ],
            'labelSpecification' => [
                'labelFormatType' => 'COMMON2D',
                'imageType' => 'PDF',
                'labelStockType' => 'PAPER_85X11_TOP_HALF_LABEL',
            ],
            'rateRequestType' => ['ACCOUNT'],
            'totalPackageCount' => 1,
            'requestedPackageLineItems' => [$packageLineItem],
        ];

        // for international shipping
        if ($request->getShipperAddressCountryCode() != $request->getRecipientAddressCountryCode()) {
            $requestedShipment['customsClearanceDetail'] = [
                'dutiesPayment' => [
                    'paymentType' => $paymentType,
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => ['value' => $this->getConfigData('account')],
                            'address' => ['countryCode' => $originCountry],
                        ],
                    ],
                ],
                'commodities' => [
                    [
                        'weight' => [
                            'units' => $weightUnits,
                            'value' => (float) $request->getPackageWeight(),
                        ],
                        'numberOfPieces' => 1,
                        'countryOfManufacture' => implode(',', array_unique($countriesOfManufacture)),
                        'description' => implode(', ', $itemsDesc),
                        'quantity' => (int) ceil($itemsQty),
                        'quantityUnits' => 'pcs',
                        'unitPrice' => [
                            'currency' => $request->getBaseCurrencyCode(),
                            'amount' => $unitPrice,
                        ],
                        'customsValue' => [
                            'currency' => $request->getBaseCurrencyCode(),
                            'amount' => $customsValue,
                        ],
                    ],
                ],
            ];
        }

        if ($request->getMasterTrackingId()) {
            $requestedShipment['masterTrackingId'] = ['trackingNumber' => $request->getMasterTrackingId()];
        }

        return [
            'labelResponseOptions' => 'LABEL',
            'accountNumber' => ['value' => $this->getConfigData('account')],
            'requestedShipment' => $requestedShipment,
        ];
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @return \Maho\DataObject
     */
    #[\Override]
    protected function _doShipmentRequest(\Maho\DataObject $request)
    {
        $this->_prepareShipmentRequest($request);
        $result = new \Maho\DataObject();
        $requestClient = $this->_formShipmentRequest($request);
        $response = $this->_getRestClient()->createShipment($requestClient);

        $error = Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::extractErrorMessage($response);
        if ($error === null) {
            $shipment = $response['output']['transactionShipments'][0] ?? [];
            $pieceResponse = $shipment['pieceResponses'][0] ?? [];
            $encodedLabel = $pieceResponse['packageDocuments'][0]['encodedLabel'] ?? null;
            // Piece number first: multi-package child responses repeat the master number
            $trackingNumber = $pieceResponse['trackingNumber'] ?? $shipment['masterTrackingNumber'] ?? null;

            if ($encodedLabel === null || $trackingNumber === null) {
                // A 200 without errors[] but without a label is still a failure
                $error = Mage::helper('usa')->__('FedEx did not return a shipping label');
            } else {
                $result->setShippingLabelContent(base64_decode($encodedLabel));
                $result->setTrackingNumber($trackingNumber);
            }
        }
        if ($error !== null) {
            $result->setErrors($error);
        }
        $result->setGatewayResponse(Mage::helper('core')->jsonEncode($response));

        return $result;
    }

    /**
     * For multi package shipments. Delete requested shipments if the current shipment
     * request is failed
     *
     * @param array $data
     * @return bool
     */
    #[\Override]
    public function rollBack($data)
    {
        $rolledBack = true;

        foreach ($data as $item) {
            $response = $this->_getRestClient()->cancelShipment([
                'accountNumber' => ['value' => $this->getConfigData('account')],
                'trackingNumber' => $item['tracking_number'],
                'deletionControl' => 'DELETE_ONE_PACKAGE',
            ]);

            // A refused cancel answers HTTP 200 with cancelledShipment false and no errors[],
            // so the flag is the only signal that the label is still live.
            if (empty($response['output']['cancelledShipment'])) {
                $rolledBack = false;
                Mage::log(
                    sprintf(
                        'FedEx did not cancel shipment %s: %s',
                        $item['tracking_number'],
                        $response['output']['message']
                            ?? Mage_Usa_Model_Shipping_Carrier_Fedex_RestClient::extractErrorMessage($response)
                            ?? 'no reason given',
                    ),
                    Mage::LOG_ERROR,
                );
            }
        }

        return $rolledBack;
    }

    /**
     * Return container types of carrier
     *
     * @return array|bool
     */
    #[\Override]
    public function getContainerTypes(?\Maho\DataObject $params = null)
    {
        if ($params == null) {
            return $this->_getAllowedContainers($params);
        }
        $method             = $params->getMethod();
        $countryShipper     = $params->getCountryShipper();
        $countryRecipient   = $params->getCountryRecipient();
        if (($countryShipper == self::USA_COUNTRY_ID && $countryRecipient == self::CANADA_COUNTRY_ID
            || $countryShipper == self::CANADA_COUNTRY_ID && $countryRecipient == self::USA_COUNTRY_ID)
            && $method == 'FEDEX_GROUND') {
            return ['YOUR_PACKAGING' => Mage::helper('usa')->__('Your Packaging')];
        }
        if ($method == 'INTERNATIONAL_ECONOMY' || $method == 'INTERNATIONAL_FIRST') {
            $allTypes = $this->getContainerTypesAll();
            $exclude = ['FEDEX_10KG_BOX' => '', 'FEDEX_25KG_BOX' => ''];
            return array_diff_key($allTypes, $exclude);
        }
        if ($method == 'EUROPE_FIRST_INTERNATIONAL_PRIORITY') {
            $allTypes = $this->getContainerTypesAll();
            $exclude = ['FEDEX_BOX' => '', 'FEDEX_TUBE' => ''];
            return array_diff_key($allTypes, $exclude);
        }

        if ($countryShipper == self::CANADA_COUNTRY_ID && $countryRecipient == self::CANADA_COUNTRY_ID) {
            // hack for Canada domestic. Apply the same filter rules as for US domestic
            $params->setCountryShipper(self::USA_COUNTRY_ID);
            $params->setCountryRecipient(self::USA_COUNTRY_ID);
        }

        return $this->_getAllowedContainers($params);
    }

    /**
     * Return all container types of carrier
     *
     * @return array|bool
     */
    public function getContainerTypesAll()
    {
        return $this->getCode('packaging');
    }

    /**
     * Return structured data of containers witch related with shipping methods
     *
     * @return array|bool
     */
    public function getContainerTypesFilter()
    {
        return $this->getCode('containers_filter');
    }

    /**
     * Return delivery confirmation types of carrier
     *
     * @return array
     */
    #[\Override]
    public function getDeliveryConfirmationTypes(?\Maho\DataObject $params = null)
    {
        return $this->getCode('delivery_confirmation_types');
    }
}
