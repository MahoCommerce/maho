<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Usa_Model_Shipping_Carrier_Usps extends Mage_Usa_Model_Shipping_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * USPS containers
     */
    public const CONTAINER_VARIABLE           = 'VARIABLE';
    public const CONTAINER_FLAT_RATE_BOX      = 'FLAT RATE BOX';
    public const CONTAINER_FLAT_RATE_ENVELOPE = 'FLAT RATE ENVELOPE';
    public const CONTAINER_RECTANGULAR        = 'RECTANGULAR';
    public const CONTAINER_NONRECTANGULAR     = 'NONRECTANGULAR';

    /**
     * USPS size
     */
    public const SIZE_REGULAR = 'REGULAR';
    public const SIZE_LARGE   = 'LARGE';

    /**
     * Default api revision
     *
     * @var int
     */
    public const DEFAULT_REVISION = 2;

    /**
     * Code of the carrier
     *
     * @var string
     */
    public const CODE = 'usps';

    /**
     * Ounces in one pound for conversion
     */
    public const OUNCES_POUND = 16;

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
     * Raw rate tracking request data
     *
     * @var \Maho\DataObject|null
     */
    protected $_rawTrackRequest = null;

    /**
     * Rate result data
     *
     * @var Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result|null
     */
    protected $_result = null;

    /**
     * REST API client
     */
    protected ?Mage_Usa_Model_Shipping_Carrier_Usps_RestClient $_restClient = null;

    /**
     * REST API base URLs
     */
    protected const REST_BASE_URL_PRODUCTION = 'https://apis.usps.com';
    protected const REST_BASE_URL_TEST = 'https://apis-tem.usps.com';

    /**
     * Container types that could be customized for USPS carrier
     *
     * @var array
     */
    protected $_customizableContainerTypes = ['VARIABLE', 'RECTANGULAR', 'NONRECTANGULAR'];

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

        $this->setRequest($request);

        $this->_result = $this->_getQuotes();

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
        } else {
            $r->setService('ALL');
        }

        if ($request->getUspsUserid()) {
            $userId = $request->getUspsUserid();
        } else {
            $userId = $this->getConfigData('userid');
        }
        $r->setUserId($userId);

        if ($request->getUspsContainer()) {
            $container = $request->getUspsContainer();
        } else {
            $container = $this->getConfigData('container');
        }
        $r->setContainer($container);

        if ($request->getUspsSize()) {
            $size = $request->getUspsSize();
        } else {
            $size = $this->getConfigData('size');
        }
        $r->setSize($size);

        if ($request->getGirth()) {
            $girth = $request->getGirth();
        } else {
            $girth = $this->getConfigData('girth');
        }
        $r->setGirth($girth);

        if ($request->getHeight()) {
            $height = $request->getHeight();
        } else {
            $height = $this->getConfigData('height');
        }
        $r->setHeight($height);

        if ($request->getLength()) {
            $length = $request->getLength();
        } else {
            $length = $this->getConfigData('length');
        }
        $r->setLength($length);

        if ($request->getWidth()) {
            $width = $request->getWidth();
        } else {
            $width = $this->getConfigData('width');
        }
        $r->setWidth($width);

        if ($request->getUspsMachinable()) {
            $machinable = $request->getUspsMachinable();
        } else {
            $machinable = $this->getConfigData('machinable');
        }
        $r->setMachinable($machinable);

        if ($request->getOrigPostcode()) {
            $r->setOrigPostal($request->getOrigPostcode());
        } else {
            $r->setOrigPostal(Mage::getStoreConfig(
                Mage_Shipping_Model_Shipping::XML_PATH_STORE_ZIP,
                $request->getStoreId(),
            ));
        }

        if ($request->getOrigCountryId()) {
            $r->setOrigCountryId($request->getOrigCountryId());
        } else {
            $r->setOrigCountryId(Mage::getStoreConfig(
                Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID,
                $request->getStoreId(),
            ));
        }

        if ($request->getDestCountryId()) {
            $destCountry = $request->getDestCountryId();
        } else {
            $destCountry = self::USA_COUNTRY_ID;
        }

        $r->setDestCountryId($destCountry);

        if (!$this->_isUSCountry($destCountry)) {
            $r->setDestCountryName($this->_getCountryName($destCountry));
        }

        if ($request->getDestPostcode()) {
            $r->setDestPostal($request->getDestPostcode());
        }

        $weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
        $r->setWeightPounds(floor($weight));
        $r->setWeightOunces(round(($weight - floor($weight)) * self::OUNCES_POUND, 1));
        if ($request->getFreeMethodWeight() != $request->getPackageWeight()) {
            $r->setFreeMethodWeight($request->getFreeMethodWeight());
        }

        $r->setValue($request->getPackageValue());
        $r->setValueWithDiscount($request->getPackageValueWithDiscount());

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
     * Starting from 23.02.2018 USPS doesn't allow to create free shipping labels via their API.
     */
    #[\Override]
    public function isShippingLabelsAvailable()
    {
        return true;
    }

    /**
     * Get quotes
     *
     * @return Mage_Shipping_Model_Rate_Result
     */
    protected function _getQuotes()
    {
        return $this->_getRestQuotes();
    }

    /**
     * Convert weight from store's unit to pounds (required by USPS API)
     *
     * @param int $weight Weight in store's unit
     * @return float Weight in pounds
     */
    #[\Override]
    public function convertWeightToLbs($weight)
    {
        if (!$weight) {
            return 0;
        }

        // Get store's weight unit configuration
        $weightUnit = Mage::getStoreConfig('general/locale/weight_unit');

        // If not configured or already in pounds, return as-is
        if (!$weightUnit || $weightUnit === 'lbs') {
            return $weight;
        }

        // Use helper to convert using php-units-of-measure
        return Mage::helper('usa')->convertMeasureWeight($weight, $weightUnit, 'lb');
    }

    /**
     * Convert dimension from store's unit to inches (required by USPS API)
     *
     * @param float $dimension
     * @return float Dimension in inches
     */
    protected function convertDimensionToInches($dimension)
    {
        if (!$dimension) {
            return 0;
        }

        // Get store's length unit configuration
        $lengthUnit = Mage::getStoreConfig('general/locale/length_unit');

        // If not configured or already in inches, return as-is
        if (!$lengthUnit || $lengthUnit === 'in') {
            return $dimension;
        }

        // Use helper to convert using php-units-of-measure
        return Mage::helper('usa')->convertMeasureDimension($dimension, $lengthUnit, 'in');
    }

    /**
     * Get REST API client instance
     */
    protected function getRestClient(): Mage_Usa_Model_Shipping_Carrier_Usps_RestClient
    {
        if ($this->_restClient === null) {
            $clientId = $this->getConfigData('client_id');
            $clientSecret = $this->getConfigData('client_secret');
            $environment = $this->getConfigData('api_environment') ?: 'production';
            $debugMode = (bool) $this->getConfigData('debug');

            $baseUrl = ($environment === 'test') ? self::REST_BASE_URL_TEST : self::REST_BASE_URL_PRODUCTION;

            $oauthClient = new Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient(
                $clientId,
                $clientSecret,
                $baseUrl,
            );

            $this->_restClient = new Mage_Usa_Model_Shipping_Carrier_Usps_RestClient(
                $oauthClient,
                $environment,
                $debugMode,
            );
        }

        return $this->_restClient;
    }

    /**
     * Get quotes using REST API
     */
    protected function _getRestQuotes(): Mage_Shipping_Model_Rate_Result
    {
        $r = $this->_rawRequest;

        // Origin must be in USA
        if (!$this->_isUSCountry($r->getOrigCountryId())) {
            return $this->_parseRestResponse([]);
        }

        try {
            $restClient = $this->getRestClient();

            // Build single request with mailClass: 'ALL' to get all available services
            $requestData = $this->buildShippingOptionsRequest($r);

            // Cache the response
            $cacheKey = Mage::helper('core')->jsonEncode($requestData);
            $cachedResponse = $this->_getCachedQuotes($cacheKey);

            if ($cachedResponse) {
                $response = Mage::helper('core')->jsonDecode($cachedResponse);
                return $this->_parseRestResponse($response);
            }

            // Make single API call
            $response = $restClient->getShippingOptions($requestData);

            // Cache the response
            $this->_setCachedQuotes($cacheKey, Mage::helper('core')->jsonEncode($response));

            return $this->_parseRestResponse($response);

        } catch (Exception $e) {
            Mage::logException($e);
            return $this->_parseRestResponse([]);
        }
    }

    /**
     * Map method code to REST API mail class for label generation
     * Note: Not used for rate requests (we use mailClass: 'ALL')
     */
    protected function mapServiceToMailClass(string $service): string
    {
        $mapping = [
            '1' => 'PRIORITY_MAIL',
            '3' => 'PRIORITY_MAIL_EXPRESS',
            '6' => 'MEDIA_MAIL',
            '7' => 'LIBRARY_MAIL',
            '13' => 'PRIORITY_MAIL_EXPRESS',
            '16' => 'PRIORITY_MAIL', // Flat Rate Envelope
            '17' => 'PRIORITY_MAIL', // Medium Flat Rate Box
            '22' => 'PRIORITY_MAIL', // Large Flat Rate Box
            '28' => 'PRIORITY_MAIL', // Small Flat Rate Box
            '29' => 'PRIORITY_MAIL', // Padded Flat Rate Envelope
            '53' => 'FIRST-CLASS_PACKAGE_SERVICE', // Note: hyphen, not underscore
            '62' => 'PRIORITY_MAIL_EXPRESS', // Padded Flat Rate Envelope
            '1058' => 'USPS_GROUND_ADVANTAGE',
            'INT_1' => 'PRIORITY_MAIL_EXPRESS_INTERNATIONAL',
            'INT_2' => 'PRIORITY_MAIL_INTERNATIONAL',
        ];

        return $mapping[$service] ?? 'PRIORITY_MAIL';
    }

    /**
     * Get rate indicator for specific service
     */
    protected function getRateIndicator(string $service): string
    {
        $mapping = [
            '1' => 'SP',    // Priority Mail Single-Piece
            '3' => 'SP',    // Priority Mail Express Single-Piece
            '6' => 'SP',    // Media Mail
            '7' => 'SP',    // Library Mail
            '13' => 'FE',   // Priority Mail Express Flat Rate Envelope
            '16' => 'FE',   // Priority Mail Flat Rate Envelope
            '17' => 'FB',   // Priority Mail Medium Flat Rate Box
            '22' => 'PL',   // Priority Mail Large Flat Rate Box
            '28' => 'FS',   // Priority Mail Small Flat Rate Box
            '29' => 'FP',   // Priority Mail Padded Flat Rate Envelope
            '53' => 'SP',   // First-Class Package Service
            '62' => 'FP',   // Priority Mail Express Padded Flat Rate Envelope
            '1058' => 'SP', // USPS Ground Advantage
            'INT_1' => 'SP',
            'INT_2' => 'SP',
        ];

        return $mapping[$service] ?? 'DR'; // Default to DR (Dimensional Rectangular)
    }

    /**
     * Get processing category for specific service
     */
    protected function getProcessingCategory(string $service): string
    {
        $mapping = [
            '1' => 'MACHINABLE',
            '3' => 'MACHINABLE',
            '6' => 'MACHINABLE',
            '7' => 'MACHINABLE',
            '13' => 'FLATS',    // Flat Rate Envelope
            '16' => 'FLATS',    // Flat Rate Envelope
            '17' => 'MACHINABLE', // Flat Rate Box
            '22' => 'MACHINABLE', // Flat Rate Box
            '28' => 'MACHINABLE', // Flat Rate Box
            '29' => 'FLATS',    // Padded Flat Rate Envelope
            '53' => 'MACHINABLE',
            '62' => 'FLATS',    // Padded Flat Rate Envelope
            '1058' => 'MACHINABLE',
            'INT_1' => 'MACHINABLE',
            'INT_2' => 'MACHINABLE',
        ];

        return $mapping[$service] ?? 'MACHINABLE';
    }

    /**
     * Build Shipping Options API request (for both domestic and international)
     * Uses mailClass: 'ALL' to get all available services in a single API call
     */
    protected function buildShippingOptionsRequest(Maho\DataObject $r): array
    {
        $weight = (float) $r->getWeightPounds() + ($r->getWeightOunces() / 16);
        $length = $this->convertDimensionToInches((float) ($r->getLength() ?: 12));
        $width = $this->convertDimensionToInches((float) ($r->getWidth() ?: 8));
        $height = $this->convertDimensionToInches((float) ($r->getHeight() ?: 6));

        $request = [
            'originZIPCode' => substr($r->getOrigPostal(), 0, 5),
            'pricingOptions' => [
                [
                    'priceType' => $this->getConfigData('commercial_pricing') ? 'COMMERCIAL' : 'RETAIL',
                ],
            ],
            'packageDescription' => [
                'weight' => $weight,
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'mailClass' => 'ALL', // Get all available services in one call
            ],
        ];

        // Add destination (domestic vs international)
        if ($this->_isUSCountry($r->getDestCountryId())) {
            // Domestic
            $request['destinationZIPCode'] = substr($r->getDestPostal(), 0, 5);
        } else {
            // International
            $request['destinationCountryCode'] = $r->getDestCountryId();
            $request['foreignPostalCode'] = $r->getDestPostal();
        }

        // Add girth if non-rectangular container
        $container = $r->getContainer();
        if ($container === 'NONRECTANGULAR' || $container === 'VARIABLE') {
            $girth = $r->getGirth();
            if ($girth) {
                $request['packageDescription']['girth'] = $this->convertDimensionToInches((float) $girth);
            }
        }

        return $request;
    }

    /**
     * Extract method code from REST API service description
     */
    protected function extractMethodCodeFromDescription(string $description): string
    {
        // Map REST API descriptions back to our method codes
        // Order matters: most specific patterns first!
        $mapping = [
            // International (must come before domestic)
            'Priority Mail Express International' => 'INT_1',
            'Priority Mail International' => 'INT_2',

            // Priority Mail Express variations (must come before regular Priority Mail)
            'Priority Mail Express Padded Flat Rate Envelope' => '62',
            'Priority Mail Express Flat Rate Envelope' => '13',
            'Priority Mail Express Sunday/Holiday Delivery' => '23',
            'Priority Mail Express' => '3',

            // Priority Mail variations (most specific first)
            'Priority Mail Padded Flat Rate Envelope' => '29',
            'Priority Mail Large Flat Rate Box' => '22',
            'Priority Mail Medium Flat Rate Box' => '17',
            'Priority Mail Small Flat Rate Box' => '28',
            'Priority Mail Flat Rate Envelope' => '16',
            'Priority Mail Regional Rate Box' => '47', // Covers A, B, C
            'Priority Mail' => '1',

            // Other services
            'First-Class Package Service' => '53',
            'USPS Ground Advantage' => '1058',
            'USPS Retail Ground' => '4',
            'Retail Ground' => '4',
            'Media Mail' => '6',
            'Library Mail' => '7',
        ];

        foreach ($mapping as $pattern => $code) {
            if (stripos($description, $pattern) !== false) {
                return $code;
            }
        }

        return '1'; // Default to Priority Mail
    }

    /**
     * Parse REST API response
     */
    protected function _parseRestResponse(array $response): Mage_Shipping_Model_Rate_Result
    {
        $result = Mage::getModel('shipping/rate_result');
        $r = $this->_rawRequest;

        // Extract rates from Shipping Options API response structure
        $allRates = [];

        if (!empty($response['pricingOptions'])) {
            foreach ($response['pricingOptions'] as $pricingOption) {
                if (!empty($pricingOption['shippingOptions'])) {
                    foreach ($pricingOption['shippingOptions'] as $shippingOption) {
                        if (!empty($shippingOption['rateOptions'])) {
                            foreach ($shippingOption['rateOptions'] as $rateOption) {
                                if (!empty($rateOption['rates'])) {
                                    foreach ($rateOption['rates'] as $rateData) {
                                        $allRates[] = array_merge($rateData, [
                                            'totalPrice' => $rateOption['totalPrice'],
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        if (empty($allRates)) {
            if ($this->getConfigData('showmethod')) {
                $error = Mage::getModel('shipping/rate_result_error');
                $error->setCarrier('usps');
                $error->setCarrierTitle($this->getConfigData('title'));
                $error->setErrorMessage($this->getConfigData('specificerrmsg') ?: 'No rates available');
                $result->append($error);
            }
            return $result;
        }

        $allowedMethods = explode(',', $this->getConfigData('allowed_methods'));

        // Group rates by method code and keep only the lowest price for each
        $methodRates = [];
        foreach ($allRates as $rateData) {
            $methodCode = $this->extractMethodCodeFromDescription($rateData['description']);

            // Check if this is a specific method request or filter by allowed methods
            if ($r->getService() !== 'ALL') {
                // Specific method requested - only show that method
                if ($methodCode !== $r->getService()) {
                    continue;
                }
            } else {
                // Requesting all methods - filter by allowed methods configuration
                if (!in_array($methodCode, $allowedMethods)) {
                    continue;
                }
            }

            // Keep only the lowest price for each method code
            if (!isset($methodRates[$methodCode]) || $rateData['totalPrice'] < $methodRates[$methodCode]['totalPrice']) {
                $methodRates[$methodCode] = $rateData;
            }
        }

        // Add the lowest rate for each method to the result
        foreach ($methodRates as $methodCode => $rateData) {
            $rate = Mage::getModel('shipping/rate_result_method');
            $rate->setCarrier('usps');
            $rate->setCarrierTitle($this->getConfigData('title'));
            $rate->setMethod($methodCode);
            $rate->setMethodTitle($this->getCode('method', $methodCode) ?: $rateData['description']);
            $rate->setCost($rateData['totalPrice']);
            $rate->setPrice($this->getMethodPrice($rateData['totalPrice'], $methodCode));

            $result->append($rate);
        }

        // If no rates were added and showmethod is enabled, show error
        if (!count($result->getAllRates()) && $this->getConfigData('showmethod')) {
            $error = Mage::getModel('shipping/rate_result_error');
            $error->setCarrier('usps');
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg') ?: 'No rates available');
            $result->append($error);
        }

        return $result;
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
        $r->setWeightPounds(floor($weight));
        $r->setWeightOunces(round(($weight - floor($weight)) * self::OUNCES_POUND, 1));
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
                '0_FCLE' => Mage::helper('usa')->__('First-Class Mail Large Envelope'),
                '0_FCL'  => Mage::helper('usa')->__('First-Class Mail Letter'),
                '0_FCSL' => Mage::helper('usa')->__('First-Class Mail Stamped Letter'),
                '0_FCPC' => Mage::helper('usa')->__('First-Class Mail Postcards'),
                '1'      => Mage::helper('usa')->__('Priority Mail'),
                '2'      => Mage::helper('usa')->__('Priority Mail Express Hold For Pickup'),
                '3'      => Mage::helper('usa')->__('Priority Mail Express'),
                '4'      => Mage::helper('usa')->__('Retail Ground'),
                '6'      => Mage::helper('usa')->__('Media Mail Parcel'),
                '7'      => Mage::helper('usa')->__('Library Mail Parcel'),
                '13'     => Mage::helper('usa')->__('Priority Mail Express Flat Rate Envelope'),
                '15'     => Mage::helper('usa')->__('First-Class Mail Large Postcards'),
                '16'     => Mage::helper('usa')->__('Priority Mail Flat Rate Envelope'),
                '17'     => Mage::helper('usa')->__('Priority Mail Medium Flat Rate Box'),
                '22'     => Mage::helper('usa')->__('Priority Mail Large Flat Rate Box'),
                '23'     => Mage::helper('usa')->__('Priority Mail Express Sunday/Holiday Delivery'),
                '25'     => Mage::helper('usa')->__('Priority Mail Express Sunday/Holiday Delivery Flat Rate Envelope'),
                '27'     => Mage::helper('usa')->__('Priority Mail Express Flat Rate Envelope Hold For Pickup'),
                '28'     => Mage::helper('usa')->__('Priority Mail Small Flat Rate Box'),
                '29'     => Mage::helper('usa')->__('Priority Mail Padded Flat Rate Envelope'),
                '30'     => Mage::helper('usa')->__('Priority Mail Express Legal Flat Rate Envelope'),
                '31'     => Mage::helper('usa')->__('Priority Mail Express Legal Flat Rate Envelope Hold For Pickup'),
                '32'     => Mage::helper('usa')->__('Priority Mail Express Sunday/Holiday Delivery Legal Flat Rate Envelope'),
                '33'     => Mage::helper('usa')->__('Priority Mail Hold For Pickup'),
                '34'     => Mage::helper('usa')->__('Priority Mail Large Flat Rate Box Hold For Pickup'),
                '35'     => Mage::helper('usa')->__('Priority Mail Medium Flat Rate Box Hold For Pickup'),
                '36'     => Mage::helper('usa')->__('Priority Mail Small Flat Rate Box Hold For Pickup'),
                '37'     => Mage::helper('usa')->__('Priority Mail Flat Rate Envelope Hold For Pickup'),
                '38'     => Mage::helper('usa')->__('Priority Mail Gift Card Flat Rate Envelope'),
                '39'     => Mage::helper('usa')->__('Priority Mail Gift Card Flat Rate Envelope Hold For Pickup'),
                '40'     => Mage::helper('usa')->__('Priority Mail Window Flat Rate Envelope'),
                '41'     => Mage::helper('usa')->__('Priority Mail Window Flat Rate Envelope Hold For Pickup'),
                '42'     => Mage::helper('usa')->__('Priority Mail Small Flat Rate Envelope'),
                '43'     => Mage::helper('usa')->__('Priority Mail Small Flat Rate Envelope Hold For Pickup'),
                '44'     => Mage::helper('usa')->__('Priority Mail Legal Flat Rate Envelope'),
                '45'     => Mage::helper('usa')->__('Priority Mail Legal Flat Rate Envelope Hold For Pickup'),
                '46'     => Mage::helper('usa')->__('Priority Mail Padded Flat Rate Envelope Hold For Pickup'),
                '47'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box A'),
                '48'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box A Hold For Pickup'),
                '49'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box B'),
                '50'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box B Hold For Pickup'),
                '53'     => Mage::helper('usa')->__('First-Class Package Service Hold For Pickup'),
                '57'     => Mage::helper('usa')->__('Priority Mail Express Sunday/Holiday Delivery Flat Rate Boxes'),
                '58'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box C'),
                '59'     => Mage::helper('usa')->__('Priority Mail Regional Rate Box C Hold For Pickup'),
                '62'     => Mage::helper('usa')->__('Priority Mail Express Padded Flat Rate Envelope'),
                '63'     => Mage::helper('usa')->__('Priority Mail Express Padded Flat Rate Envelope Hold For Pickup'),
                '64'     => Mage::helper('usa')->__('Priority Mail Express Sunday/Holiday Delivery Padded Flat Rate Envelope'),
                '72'     => Mage::helper('usa')->__('First-Class Mail Metered Letter'),
                'INT_1'  => Mage::helper('usa')->__('Priority Mail Express International'),
                'INT_2'  => Mage::helper('usa')->__('Priority Mail International'),
                'INT_4'  => Mage::helper('usa')->__('Global Express Guaranteed (GXG)'),
                'INT_5'  => Mage::helper('usa')->__('Global Express Guaranteed Document'),
                'INT_6'  => Mage::helper('usa')->__('Global Express Guaranteed Non-Document Rectangular'),
                'INT_7'  => Mage::helper('usa')->__('Global Express Guaranteed Non-Document Non-Rectangular'),
                'INT_8'  => Mage::helper('usa')->__('Priority Mail International Flat Rate Envelope'),
                'INT_9'  => Mage::helper('usa')->__('Priority Mail International Medium Flat Rate Box'),
                'INT_10' => Mage::helper('usa')->__('Priority Mail Express International Flat Rate Envelope'),
                'INT_11' => Mage::helper('usa')->__('Priority Mail International Large Flat Rate Box'),
                'INT_12' => Mage::helper('usa')->__('USPS GXG Envelopes'),
                'INT_13' => Mage::helper('usa')->__('First-Class Mail International Letter'),
                'INT_14' => Mage::helper('usa')->__('First-Class Mail International Large Envelope'),
                'INT_15' => Mage::helper('usa')->__('First-Class Package International Service'),
                'INT_16' => Mage::helper('usa')->__('Priority Mail International Small Flat Rate Box'),
                'INT_17' => Mage::helper('usa')->__('Priority Mail Express International Legal Flat Rate Envelope'),
                'INT_18' => Mage::helper('usa')->__('Priority Mail International Gift Card Flat Rate Envelope'),
                'INT_19' => Mage::helper('usa')->__('Priority Mail International Window Flat Rate Envelope'),
                'INT_20' => Mage::helper('usa')->__('Priority Mail International Small Flat Rate Envelope'),
                'INT_21' => Mage::helper('usa')->__('First-Class Mail International Postcard'),
                'INT_22' => Mage::helper('usa')->__('Priority Mail International Legal Flat Rate Envelope'),
                'INT_23' => Mage::helper('usa')->__('Priority Mail International Padded Flat Rate Envelope'),
                'INT_24' => Mage::helper('usa')->__('Priority Mail International DVD Flat Rate priced box'),
                'INT_25' => Mage::helper('usa')->__('Priority Mail International Large Video Flat Rate priced box'),
                'INT_27' => Mage::helper('usa')->__('Priority Mail Express International Padded Flat Rate Envelope'),
                '1058'   => Mage::helper('usa')->__('USPS Ground Advantage'),
            ],

            'service_to_code' => [
                '0_FCLE' => 'First Class',
                '0_FCL'  => 'First Class',
                '0_FCSL' => 'First Class',
                '0_FCPC' => 'First Class',
                '1'      => 'Priority',
                '2'      => 'Priority Express',
                '3'      => 'Priority Express',
                '4'      => 'Retail Ground',
                '6'      => 'Media',
                '7'      => 'Library',
                '13'     => 'Priority Express',
                '15'     => 'First Class',
                '16'     => 'Priority',
                '17'     => 'Priority',
                '22'     => 'Priority',
                '23'     => 'Priority Express',
                '25'     => 'Priority Express',
                '27'     => 'Priority Express',
                '28'     => 'Priority',
                '29'     => 'Priority',
                '30'     => 'Priority Express',
                '31'     => 'Priority Express',
                '32'     => 'Priority Express',
                '33'     => 'Priority',
                '34'     => 'Priority',
                '35'     => 'Priority',
                '36'     => 'Priority',
                '37'     => 'Priority',
                '38'     => 'Priority',
                '39'     => 'Priority',
                '40'     => 'Priority',
                '41'     => 'Priority',
                '42'     => 'Priority',
                '43'     => 'Priority',
                '44'     => 'Priority',
                '45'     => 'Priority',
                '46'     => 'Priority',
                '47'     => 'Priority',
                '48'     => 'Priority',
                '49'     => 'Priority',
                '50'     => 'Priority',
                '53'     => 'First Class',
                '57'     => 'Priority Express',
                '58'     => 'Priority',
                '59'     => 'Priority',
                '62'     => 'Priority Express',
                '63'     => 'Priority Express',
                '64'     => 'Priority Express',
                '72'     => 'First Class',
                'INT_1'  => 'Priority Express',
                'INT_2'  => 'Priority',
                'INT_4'  => 'Priority Express',
                'INT_5'  => 'Priority Express',
                'INT_6'  => 'Priority Express',
                'INT_7'  => 'Priority Express',
                'INT_8'  => 'Priority',
                'INT_9'  => 'Priority',
                'INT_10' => 'Priority Express',
                'INT_11' => 'Priority',
                'INT_12' => 'Priority Express',
                'INT_13' => 'First Class',
                'INT_14' => 'First Class',
                'INT_15' => 'First Class',
                'INT_16' => 'Priority',
                'INT_17' => 'Priority',
                'INT_18' => 'Priority',
                'INT_19' => 'Priority',
                'INT_20' => 'Priority',
                'INT_21' => 'First Class',
                'INT_22' => 'Priority',
                'INT_23' => 'Priority',
                'INT_24' => 'Priority',
                'INT_25' => 'Priority',
                'INT_27' => 'Priority Express',
                '1058'   => 'Ground Advantage',
            ],

            // Added because USPS has different services but with same CLASSID value, which is "0"
            'method_to_code' => [
                'First-Class Mail Large Envelope'      => '0_FCLE',
                'First-Class Mail Letter'              => '0_FCL',
                'First-Class Mail Stamped Letter'      => '0_FCSL',
                'First-Class Mail Metered Letter'      => '72',
            ],

            'first_class_mail_type' => [
                'LETTER'      => Mage::helper('usa')->__('Letter'),
                'FLAT'        => Mage::helper('usa')->__('Flat'),
                'PARCEL'      => Mage::helper('usa')->__('Parcel'),
            ],

            'container' => [
                'VARIABLE'           => Mage::helper('usa')->__('Variable'),
                'FLAT RATE ENVELOPE' => Mage::helper('usa')->__('Flat-Rate Envelope'),
                'FLAT RATE BOX'      => Mage::helper('usa')->__('Flat-Rate Box'),
                'RECTANGULAR'        => Mage::helper('usa')->__('Rectangular'),
                'NONRECTANGULAR'     => Mage::helper('usa')->__('Non-rectangular'),
            ],

            'containers_filter' => [
                [
                    'containers' => ['VARIABLE'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'Priority Mail Express Flat Rate Envelope',
                                'Priority Mail Express Flat Rate Envelope Hold For Pickup',
                                'Priority Mail Flat Rate Envelope',
                                'Priority Mail Large Flat Rate Box',
                                'Priority Mail Medium Flat Rate Box',
                                'Priority Mail Small Flat Rate Box',
                                'Priority Mail Express Hold For Pickup',
                                'Priority Mail Express',
                                'Priority Mail',
                                'Priority Mail Hold For Pickup',
                                'Priority Mail Large Flat Rate Box Hold For Pickup',
                                'Priority Mail Medium Flat Rate Box Hold For Pickup',
                                'Priority Mail Small Flat Rate Box Hold For Pickup',
                                'Priority Mail Flat Rate Envelope Hold For Pickup',
                                'Priority Mail Small Flat Rate Envelope',
                                'Priority Mail Small Flat Rate Envelope Hold For Pickup',
                                'First-Class Package Service Hold For Pickup',
                                'Priority Mail Express Flat Rate Boxes',
                                'Priority Mail Express Flat Rate Boxes Hold For Pickup',
                                'Retail Ground',
                                'Media Mail',
                                'First-Class Mail Large Envelope',
                                'Priority Mail Express Sunday/Holiday Delivery',
                                'Priority Mail Express Sunday/Holiday Delivery Flat Rate Envelope',
                                'Priority Mail Express Sunday/Holiday Delivery Flat Rate Boxes',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'Priority Mail Express International Flat Rate Envelope',
                                'Priority Mail International Flat Rate Envelope',
                                'Priority Mail International Large Flat Rate Box',
                                'Priority Mail International Medium Flat Rate Box',
                                'Priority Mail International Small Flat Rate Box',
                                'Priority Mail International Small Flat Rate Envelope',
                                'Priority Mail Express International Flat Rate Boxes',
                                'Global Express Guaranteed (GXG)',
                                'USPS GXG Envelopes',
                                'Priority Mail Express International',
                                'Priority Mail International',
                                'First-Class Mail International Letter',
                                'First-Class Mail International Large Envelope',
                                'First-Class Package International Service',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['FLAT RATE BOX'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'Priority Mail Large Flat Rate Box',
                                'Priority Mail Medium Flat Rate Box',
                                'Priority Mail Small Flat Rate Box',
                                'Priority Mail International Large Flat Rate Box',
                                'Priority Mail International Medium Flat Rate Box',
                                'Priority Mail International Small Flat Rate Box',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'Priority Mail International Large Flat Rate Box',
                                'Priority Mail International Medium Flat Rate Box',
                                'Priority Mail International Small Flat Rate Box',
                                'Priority Mail International DVD Flat Rate priced box',
                                'Priority Mail International Large Video Flat Rate priced box',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['FLAT RATE ENVELOPE'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'Priority Mail Express Flat Rate Envelope',
                                'Priority Mail Express Flat Rate Envelope Hold For Pickup',
                                'Priority Mail Flat Rate Envelope',
                                'First-Class Mail Large Envelope',
                                'Priority Mail Flat Rate Envelope Hold For Pickup',
                                'Priority Mail Small Flat Rate Envelope',
                                'Priority Mail Small Flat Rate Envelope Hold For Pickup',
                                'Priority Mail Express Sunday/Holiday Delivery Flat Rate Envelope',
                                'Priority Mail Express Padded Flat Rate Envelope',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'Priority Mail Express International Flat Rate Envelope',
                                'Priority Mail International Flat Rate Envelope',
                                'First-Class Mail International Large Envelope',
                                'Priority Mail International Small Flat Rate Envelope',
                                'Priority Mail Express International Legal Flat Rate Envelope',
                                'Priority Mail International Gift Card Flat Rate Envelope',
                                'Priority Mail International Window Flat Rate Envelope',
                                'Priority Mail International Legal Flat Rate Envelope',
                                'Priority Mail Express International Padded Flat Rate Envelope',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['RECTANGULAR'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'Priority Mail Express',
                                'Priority Mail',
                                'Retail Ground',
                                'Media Mail',
                                'Library Mail',
                                'First-Class Package Service',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'USPS GXG Envelopes',
                                'Priority Mail Express International',
                                'Priority Mail International',
                                'First-Class Package International Service',
                            ],
                        ],
                    ],
                ],
                [
                    'containers' => ['NONRECTANGULAR'],
                    'filters'    => [
                        'within_us' => [
                            'method' => [
                                'Priority Mail Express',
                                'Priority Mail',
                                'Retail Ground',
                                'Media Mail',
                                'Library Mail',
                                'First-Class Package Service',
                            ],
                        ],
                        'from_us' => [
                            'method' => [
                                'Global Express Guaranteed (GXG)',
                                'Priority Mail Express International',
                                'Priority Mail International',
                                'First-Class Package International Service',
                            ],
                        ],
                    ],
                ],
            ],
            'size' => [
                'REGULAR'     => Mage::helper('usa')->__('Regular'),
                'LARGE'       => Mage::helper('usa')->__('Large'),
            ],

            'machinable' => [
                'true'        => Mage::helper('usa')->__('Yes'),
                'false'       => Mage::helper('usa')->__('No'),
            ],

            'delivery_confirmation_types' => [
                'True' => Mage::helper('usa')->__('Not Required'),
                'False'  => Mage::helper('usa')->__('Required'),
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
     * Get tracking
     *
     * @param mixed $trackingData
     * @return Mage_Shipping_Model_Rate_Result|null
     */
    public function getTracking($trackingData)
    {
        $this->setTrackingRequest();

        if (!is_array($trackingData)) {
            $trackingData = [$trackingData];
        }

        $this->_getRestTracking($trackingData);

        return $this->_result;
    }

    /**
     * Set tracking request
     */
    protected function setTrackingRequest(): void
    {
        $r = new \Maho\DataObject();
        $r->setClientId($this->getConfigData('client_id'));
        $this->_rawTrackRequest = $r;
    }

    /**
     * Get tracking using REST API
     */
    protected function _getRestTracking(array $trackingData): void
    {
        $restClient = $this->getRestClient();

        foreach ($trackingData as $trackingNumber) {
            try {
                $responseData = $restClient->getTracking($trackingNumber);
                $this->_parseRestTrackingResponse($trackingNumber, $responseData);
            } catch (Exception $e) {
                Mage::log('USPS Tracking Error for ' . $trackingNumber . ': ' . $e->getMessage(), Mage::LOG_ERROR, 'usps_rest_api.log');
                $this->_parseRestTrackingResponse($trackingNumber, []);
            }
        }
    }

    /**
     * Parse REST tracking response with detailed events
     */
    protected function _parseRestTrackingResponse(string $trackingValue, array $response): void
    {
        if (!$this->_result) {
            $this->_result = Mage::getModel('shipping/tracking_result');
        }

        if (!empty($response['trackingEvents'])) {
            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setCarrier('usps');
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->setTracking($trackingValue);

            // Use statusSummary if available, otherwise build from latest event
            if (!empty($response['statusSummary'])) {
                $summary = $response['statusSummary'];
            } else {
                $latestEvent = $response['trackingEvents'][0] ?? [];
                $summary = $latestEvent['eventType'] ?? 'In Transit';
            }

            $tracking->setTrackSummary($summary);

            // Process all tracking events for detailed progress
            $progressDetail = [];
            foreach ($response['trackingEvents'] as $event) {
                // Parse eventTimestamp (ISO 8601 format: "2023-08-02T07:31:00Z")
                $deliveryDate = '';
                $deliveryTime = '';
                if (!empty($event['eventTimestamp'])) {
                    try {
                        $dt = new DateTime($event['eventTimestamp']);
                        $deliveryDate = $dt->format('Y-m-d');
                        $deliveryTime = $dt->format('H:i:s');
                    } catch (Exception $e) {
                        // Invalid timestamp, leave empty
                    }
                }

                $progressDetail[] = [
                    'activity' => $event['eventType'] ?? '',
                    'deliverydate' => $deliveryDate,
                    'deliverytime' => $deliveryTime,
                    'deliverylocation' => $this->formatTrackingLocation($event),
                ];
            }

            if (!empty($progressDetail)) {
                $tracking->setProgressdetail($progressDetail);
            }

            $this->_result->append($tracking);
        } else {
            $error = Mage::getModel('shipping/tracking_result_error');
            $error->setCarrier('usps');
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setTracking($trackingValue);
            $error->setErrorMessage(Mage::helper('usa')->__('Unable to retrieve tracking'));
            $this->_result->append($error);
        }
    }

    /**
     * Format tracking event location
     */
    protected function formatTrackingLocation(array $event): string
    {
        $parts = [];

        if (!empty($event['eventCity'])) {
            $parts[] = $event['eventCity'];
        }
        if (!empty($event['eventState'])) {
            $parts[] = $event['eventState'];
        }
        if (!empty($event['eventZIP'])) {
            $parts[] = $event['eventZIP'];
        }
        if (!empty($event['eventCountry'])) {
            $parts[] = $event['eventCountry'];
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
            if ($trackingData = $this->_result->getAllTrackings()) {
                foreach ($trackingData as $tracking) {
                    if ($data = $tracking->getAllData()) {
                        if (!empty($data['track_summary'])) {
                            $statuses .= Mage::helper('usa')->__($data['track_summary']);
                        } else {
                            $statuses .= Mage::helper('usa')->__('Empty response');
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
     * Return USPS county name by country ISO 3166-1-alpha-2 code
     * Return false for unknown countries
     *
     * @param string $countryId
     * @return string|false
     */
    protected function _getCountryName($countryId)
    {
        $countries = [
            'AD' => 'Andorra',
            'AE' => 'United Arab Emirates',
            'AF' => 'Afghanistan',
            'AG' => 'Antigua and Barbuda',
            'AI' => 'Anguilla',
            'AL' => 'Albania',
            'AM' => 'Armenia',
            'AN' => 'Netherlands Antilles',
            'AO' => 'Angola',
            'AR' => 'Argentina',
            'AT' => 'Austria',
            'AU' => 'Australia',
            'AW' => 'Aruba',
            'AX' => 'Aland Island (Finland)',
            'AZ' => 'Azerbaijan',
            'BA' => 'Bosnia-Herzegovina',
            'BB' => 'Barbados',
            'BD' => 'Bangladesh',
            'BE' => 'Belgium',
            'BF' => 'Burkina Faso',
            'BG' => 'Bulgaria',
            'BH' => 'Bahrain',
            'BI' => 'Burundi',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BN' => 'Brunei Darussalam',
            'BO' => 'Bolivia',
            'BR' => 'Brazil',
            'BS' => 'Bahamas',
            'BT' => 'Bhutan',
            'BW' => 'Botswana',
            'BY' => 'Belarus',
            'BZ' => 'Belize',
            'CA' => 'Canada',
            'CC' => 'Cocos Island (Australia)',
            'CD' => 'Congo, Democratic Republic of the',
            'CF' => 'Central African Republic',
            'CG' => 'Congo, Republic of the',
            'CH' => 'Switzerland',
            'CI' => 'Ivory Coast (Cote d Ivoire)',
            'CK' => 'Cook Islands (New Zealand)',
            'CL' => 'Chile',
            'CM' => 'Cameroon',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'CU' => 'Cuba',
            'CV' => 'Cape Verde',
            'CX' => 'Christmas Island (Australia)',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DE' => 'Germany',
            'DJ' => 'Djibouti',
            'DK' => 'Denmark',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'DZ' => 'Algeria',
            'EC' => 'Ecuador',
            'EE' => 'Estonia',
            'EG' => 'Egypt',
            'ER' => 'Eritrea',
            'ES' => 'Spain',
            'ET' => 'Ethiopia',
            'FI' => 'Finland',
            'FJ' => 'Fiji',
            'FK' => 'Falkland Islands',
            'FM' => 'Micronesia, Federated States of',
            'FO' => 'Faroe Islands',
            'FR' => 'France',
            'GA' => 'Gabon',
            'GB' => 'Great Britain and Northern Ireland',
            'GD' => 'Grenada',
            'GE' => 'Georgia, Republic of',
            'GF' => 'French Guiana',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GL' => 'Greenland',
            'GM' => 'Gambia',
            'GN' => 'Guinea',
            'GP' => 'Guadeloupe',
            'GQ' => 'Equatorial Guinea',
            'GR' => 'Greece',
            'GS' => 'South Georgia (Falkland Islands)',
            'GT' => 'Guatemala',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HK' => 'Hong Kong',
            'HN' => 'Honduras',
            'HR' => 'Croatia',
            'HT' => 'Haiti',
            'HU' => 'Hungary',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IL' => 'Israel',
            'IN' => 'India',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'IS' => 'Iceland',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JO' => 'Jordan',
            'JP' => 'Japan',
            'KE' => 'Kenya',
            'KG' => 'Kyrgyzstan',
            'KH' => 'Cambodia',
            'KI' => 'Kiribati',
            'KM' => 'Comoros',
            'KN' => 'Saint Kitts (Saint Christopher and Nevis)',
            'KP' => 'North Korea (Korea, Democratic People\'s Republic of)',
            'KR' => 'South Korea (Korea, Republic of)',
            'KW' => 'Kuwait',
            'KY' => 'Cayman Islands',
            'KZ' => 'Kazakhstan',
            'LA' => 'Laos',
            'LB' => 'Lebanon',
            'LC' => 'Saint Lucia',
            'LI' => 'Liechtenstein',
            'LK' => 'Sri Lanka',
            'LR' => 'Liberia',
            'LS' => 'Lesotho',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'LV' => 'Latvia',
            'LY' => 'Libya',
            'MA' => 'Morocco',
            'MC' => 'Monaco (France)',
            'MD' => 'Moldova',
            'MG' => 'Madagascar',
            'MK' => 'Macedonia, Republic of',
            'ML' => 'Mali',
            'MM' => 'Burma',
            'MN' => 'Mongolia',
            'MO' => 'Macao',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MS' => 'Montserrat',
            'MT' => 'Malta',
            'MU' => 'Mauritius',
            'MV' => 'Maldives',
            'MW' => 'Malawi',
            'MX' => 'Mexico',
            'MY' => 'Malaysia',
            'MZ' => 'Mozambique',
            'NA' => 'Namibia',
            'NC' => 'New Caledonia',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NI' => 'Nicaragua',
            'NL' => 'Netherlands',
            'NO' => 'Norway',
            'NP' => 'Nepal',
            'NR' => 'Nauru',
            'NZ' => 'New Zealand',
            'OM' => 'Oman',
            'PA' => 'Panama',
            'PE' => 'Peru',
            'PF' => 'French Polynesia',
            'PG' => 'Papua New Guinea',
            'PH' => 'Philippines',
            'PK' => 'Pakistan',
            'PL' => 'Poland',
            'PM' => 'Saint Pierre and Miquelon',
            'PN' => 'Pitcairn Island',
            'PT' => 'Portugal',
            'PY' => 'Paraguay',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RS' => 'Serbia',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'SA' => 'Saudi Arabia',
            'SB' => 'Solomon Islands',
            'SC' => 'Seychelles',
            'SD' => 'Sudan',
            'SE' => 'Sweden',
            'SG' => 'Singapore',
            'SH' => 'Saint Helena',
            'SI' => 'Slovenia',
            'SK' => 'Slovak Republic',
            'SL' => 'Sierra Leone',
            'SM' => 'San Marino',
            'SN' => 'Senegal',
            'SO' => 'Somalia',
            'SR' => 'Suriname',
            'ST' => 'Sao Tome and Principe',
            'SV' => 'El Salvador',
            'SY' => 'Syrian Arab Republic',
            'SZ' => 'Swaziland',
            'TC' => 'Turks and Caicos Islands',
            'TD' => 'Chad',
            'TG' => 'Togo',
            'TH' => 'Thailand',
            'TJ' => 'Tajikistan',
            'TK' => 'Tokelau (Union Group) (Western Samoa)',
            'TL' => 'East Timor (Timor-Leste, Democratic Republic of)',
            'TM' => 'Turkmenistan',
            'TN' => 'Tunisia',
            'TO' => 'Tonga',
            'TR' => 'Turkey',
            'TT' => 'Trinidad and Tobago',
            'TV' => 'Tuvalu',
            'TW' => 'Taiwan',
            'TZ' => 'Tanzania',
            'UA' => 'Ukraine',
            'UG' => 'Uganda',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VA' => 'Vatican City',
            'VC' => 'Saint Vincent and the Grenadines',
            'VE' => 'Venezuela',
            'VG' => 'British Virgin Islands',
            'VN' => 'Vietnam',
            'VU' => 'Vanuatu',
            'WF' => 'Wallis and Futuna Islands',
            'WS' => 'Western Samoa',
            'YE' => 'Yemen',
            'YT' => 'Mayotte (France)',
            'ZA' => 'South Africa',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'US' => 'United States',
        ];

        return $countries[$countryId] ?? false;
    }

    /**
     * Clean service name from unsupported strings and characters
     *
     * @param  string $name
     * @return string
     */
    protected function _filterServiceName($name)
    {
        $name = (string) preg_replace(
            ['~<[^/!][^>]+>.*</[^>]+>~sU', '~\<!--.*--\>~isU', '~<[^>]+>~is'],
            '',
            html_entity_decode($name),
        );
        $name = str_replace('*', '', $name);

        return $name;
    }

    /**
     * Do shipment request via REST API v3
     *
     * @return \Maho\DataObject
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _doShipmentRequest(\Maho\DataObject $request)
    {
        $result = new \Maho\DataObject();

        try {
            $restClient = $this->getRestClient();

            // Create payment authorization token
            $paymentToken = $this->createPaymentAuthToken($restClient);
            $restClient->setPaymentAuthToken($paymentToken);

            // Determine if domestic or international
            $recipientCountry = $request->getRecipientAddressCountryCode();
            $isDomestic = $this->_isUSCountry($recipientCountry);

            // Build label request
            if ($isDomestic) {
                $labelData = $this->buildDomesticLabelRequest($request);
                $response = $restClient->createDomesticLabel($labelData);
            } else {
                $labelData = $this->buildInternationalLabelRequest($request);
                $response = $restClient->createInternationalLabel($labelData);
            }

            // Process response
            $this->processLabelResponse($result, $response);

            return $result;
        } catch (Exception $e) {
            Mage::logException($e);
            $result->setErrors($e->getMessage());
            return $result;
        }
    }

    /**
     * Create payment authorization token
     */
    protected function createPaymentAuthToken(Mage_Usa_Model_Shipping_Carrier_Usps_RestClient $restClient): string
    {
        $accountType = $this->getConfigData('payment_account_type');
        $accountNumber = $this->getConfigData('payment_account_number');
        $crid = $this->getConfigData('payment_crid');
        $mid = $this->getConfigData('payment_mid');

        if (!$accountType || !$accountNumber || !$crid || !$mid) {
            Mage::throwException(
                Mage::helper('usa')->__('Payment account configuration is incomplete. Please configure Account Type, Account Number, CRID, and MID in System > Configuration > USPS.'),
            );
        }

        $paymentData = [
            'roles' => [
                [
                    'roleName' => 'PAYER',
                    'CRID' => $crid,
                    'MID' => $mid,
                    'manifestMID' => $mid,
                    'accountType' => $accountType,
                    'accountNumber' => $accountNumber,
                ],
                [
                    'roleName' => 'LABEL_OWNER',
                    'CRID' => $crid,
                    'MID' => $mid,
                    'manifestMID' => $mid,
                    'accountType' => $accountType,
                    'accountNumber' => $accountNumber,
                ],
            ],
        ];

        // Add permit ZIP if using PERMIT account type
        if ($accountType === 'PERMIT') {
            $permitZip = $this->getConfigData('payment_permit_zip');
            if ($permitZip) {
                foreach ($paymentData['roles'] as &$role) {
                    $role['permitZIP'] = $permitZip;
                }
            }
        }

        return $restClient->createPaymentAuthorization($paymentData);
    }

    /**
     * Build domestic label request data
     */
    protected function buildDomesticLabelRequest(\Maho\DataObject $request): array
    {
        $packageParams = $request->getPackageParams();
        $shipperAddress = $request->getShipperAddress();
        $recipientAddress = $request->getRecipientAddress();

        // Get weight in pounds using helper for conversion
        $weight = $request->getPackageWeight();
        $weightUnit = $request->getPackageWeightUnit() === 'KILOGRAM' ? 'kg' : 'lb';
        $weightInPounds = Mage::helper('usa')->convertMeasureWeight($weight, $weightUnit, 'lb');

        // Get dimensions in inches using helper for conversion
        $length = $this->convertDimensionToInches($packageParams->getLength() ?: 12);
        $width = $this->convertDimensionToInches($packageParams->getWidth() ?: 8);
        $height = $this->convertDimensionToInches($packageParams->getHeight() ?: 6);

        // Map service type
        $serviceCode = $request->getShippingMethod();
        $mailClass = $this->mapServiceToMailClass($serviceCode);

        $labelData = [
            'imageInfo' => [
                'imageType' => 'PDF',
                'labelType' => '4X6LABEL',
                'receiptOption' => 'SEPARATE_PAGE',
                'suppressPostage' => false,
                'suppressMailDate' => false,
                'returnLabel' => false,
            ],
            'fromAddress' => [
                'streetAddress' => $shipperAddress->getStreet1(),
                'secondaryAddress' => $shipperAddress->getStreet2() ?: '',
                'city' => $shipperAddress->getCity(),
                'state' => $shipperAddress->getRegionCode(),
                'ZIPCode' => $shipperAddress->getPostcode(),
                'firstName' => $shipperAddress->getFirstname(),
                'lastName' => $shipperAddress->getLastname(),
                'firm' => $shipperAddress->getCompany() ?: '',
                'phone' => $shipperAddress->getPhone() ?: '',
            ],
            'toAddress' => [
                'streetAddress' => $recipientAddress->getStreet1(),
                'secondaryAddress' => $recipientAddress->getStreet2() ?: '',
                'city' => $recipientAddress->getCity(),
                'state' => $recipientAddress->getRegionCode(),
                'ZIPCode' => $recipientAddress->getPostcode(),
                'firstName' => $recipientAddress->getFirstname(),
                'lastName' => $recipientAddress->getLastname(),
                'firm' => $recipientAddress->getCompany() ?: '',
                'phone' => $recipientAddress->getPhone() ?: '',
            ],
            'packageDescription' => [
                'mailClass' => $mailClass,
                'rateIndicator' => $this->getRateIndicator($serviceCode),
                'weightUOM' => 'LB',
                'weight' => round($weightInPounds, 2),
                'dimensionsUOM' => 'IN',
                'length' => (float) $length,
                'width' => (float) $width,
                'height' => (float) $height,
                'processingCategory' => $this->getProcessingCategory($serviceCode),
                'mailingDate' => date('Y-m-d'),
                'destinationEntryFacilityType' => 'NONE',
            ],
        ];

        // Add package value if present
        if ($packageParams->getCustomsValue()) {
            $labelData['packageDescription']['packageOptions'] = [
                'packageValue' => (float) $packageParams->getCustomsValue(),
            ];
        }

        // Add extra services (like delivery confirmation)
        $extraServices = $this->getExtraServices($request);
        if (!empty($extraServices)) {
            $labelData['packageDescription']['extraServices'] = $extraServices;
        }

        return $labelData;
    }

    /**
     * Build international label request data
     */
    protected function buildInternationalLabelRequest(\Maho\DataObject $request): array
    {
        $packageParams = $request->getPackageParams();
        $shipperAddress = $request->getShipperAddress();
        $recipientAddress = $request->getRecipientAddress();

        // Get weight in pounds using helper for conversion
        $weight = $request->getPackageWeight();
        $weightUnit = $request->getPackageWeightUnit() === 'KILOGRAM' ? 'kg' : 'lb';
        $weightInPounds = Mage::helper('usa')->convertMeasureWeight($weight, $weightUnit, 'lb');

        // Get dimensions in inches using helper for conversion
        $length = $this->convertDimensionToInches($packageParams->getLength() ?: 12);
        $width = $this->convertDimensionToInches($packageParams->getWidth() ?: 8);
        $height = $this->convertDimensionToInches($packageParams->getHeight() ?: 6);

        // Map service type
        $serviceCode = $request->getShippingMethod();
        $mailClass = $this->mapServiceToMailClass($serviceCode);

        $labelData = [
            'imageInfo' => [
                'imageType' => 'PDF',
                'labelType' => '4X6LABEL',
                'receiptOption' => 'SEPARATE_PAGE',
            ],
            'fromAddress' => [
                'streetAddress' => $shipperAddress->getStreet1(),
                'city' => $shipperAddress->getCity(),
                'state' => $shipperAddress->getRegionCode(),
                'ZIPCode' => $shipperAddress->getPostcode(),
                'firstName' => $shipperAddress->getFirstname(),
                'lastName' => $shipperAddress->getLastname(),
                'firm' => $shipperAddress->getCompany() ?: '',
                'phone' => $shipperAddress->getPhone() ?: '',
            ],
            'toAddress' => [
                'streetAddress' => $recipientAddress->getStreet1(),
                'city' => $recipientAddress->getCity(),
                'province' => $recipientAddress->getRegionCode() ?: '',
                'postalCode' => $recipientAddress->getPostcode(),
                'country' => $recipientAddress->getCountryId(),
                'firstName' => $recipientAddress->getFirstname(),
                'lastName' => $recipientAddress->getLastname(),
                'firm' => $recipientAddress->getCompany() ?: '',
                'phone' => $recipientAddress->getPhone() ?: '',
            ],
            'packageDescription' => [
                'mailClass' => $mailClass,
                'rateIndicator' => 'I',  // International rate indicator
                'weightUOM' => 'LB',
                'weight' => round($weightInPounds, 2),
                'dimensionsUOM' => 'IN',
                'length' => (float) $length,
                'width' => (float) $width,
                'height' => (float) $height,
                'mailingDate' => date('Y-m-d'),
            ],
            'customsForm' => [
                'customsContentType' => $packageParams->getContentType() ?: 'MERCHANDISE',
                'contentsExplanation' => $packageParams->getContentsExplanation() ?: 'Merchandise',
                'restriction' => 'NONE',
                'sendersCustomsReference' => $request->getOrderShipment()
                    ? $request->getOrderShipment()->getOrder()->getIncrementId()
                    : '',
                'importersReference' => '',
                'importersContact' => '',
                'customsItems' => $this->buildCustomsItems($request),
            ],
        ];

        return $labelData;
    }

    /**
     * Build customs items for international shipments
     */
    protected function buildCustomsItems(\Maho\DataObject $request): array
    {
        $items = [];
        $packageItems = $request->getPackageItems();

        if (!$packageItems) {
            // Fallback: create a single item from package params
            $packageParams = $request->getPackageParams();
            $items[] = [
                'description' => 'Package Contents',
                'quantity' => 1,
                'value' => (float) ($packageParams->getCustomsValue() ?: 100),
                'weight' => (float) $request->getPackageWeight(),
                'originCountry' => 'US',
                'tariffNumber' => '',
            ];
        } else {
            foreach ($packageItems as $item) {
                $items[] = [
                    'description' => substr($item['name'], 0, 50),
                    'quantity' => (int) $item['qty'],
                    'value' => (float) $item['customs_value'],
                    'weight' => (float) $item['weight'],
                    'originCountry' => $item['country_of_manufacture'] ?? 'US',
                    'tariffNumber' => $item['hs_code'] ?? '',
                ];
            }
        }

        return $items;
    }

    /**
     * Get extra services (like delivery confirmation)
     */
    protected function getExtraServices(\Maho\DataObject $request): array
    {
        $services = [];

        // Add signature confirmation if requested
        if ($request->getDeliveryConfirmation()) {
            $services[] = 930; // Delivery Confirmation
        }

        return $services;
    }

    /**
     * Process label response from API
     */
    protected function processLabelResponse(\Maho\DataObject $result, array $response): void
    {
        // Check if response has label data
        if (empty($response['labelImage'])) {
            Mage::throwException(
                Mage::helper('usa')->__('No label data received from USPS'),
            );
        }

        // labelImage is always base64 encoded according to USPS API
        $labelContent = base64_decode((string) $response['labelImage']);

        // Set tracking number (check for both domestic and international)
        if (!empty($response['trackingNumber'])) {
            $result->setTrackingNumber($response['trackingNumber']);
        } elseif (!empty($response['internationalTrackingNumber'])) {
            $result->setTrackingNumber($response['internationalTrackingNumber']);
        }

        // Set label content
        $result->setShippingLabelContent($labelContent);

        // Set label format
        $result->setLabelFormat('PDF'); // Default to PDF
    }

    /**
     * Return container types of carrier
     *
     * @return array|bool
     */
    #[\Override]
    public function getContainerTypes(?\Maho\DataObject $params = null)
    {
        if (is_null($params)) {
            return $this->_getAllowedContainers();
        }
        return $this->_isUSCountry($params->getCountryRecipient()) ? [] : $this->_getAllowedContainers($params);
    }

    /**
     * Return all container types of carrier
     *
     * @return array|bool
     */
    public function getContainerTypesAll()
    {
        return $this->getCode('container');
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
        if ($params == null) {
            return [];
        }
        $countryRecipient = $params->getCountryRecipient();
        if ($this->_isUSCountry($countryRecipient)) {
            return $this->getCode('delivery_confirmation_types');
        }
        return [];
    }

    /**
     * Check whether girth is allowed for the USPS
     *
     * @param null|string $countyDest
     * @return bool
     */
    #[\Override]
    public function isGirthAllowed($countyDest = null)
    {
        return $this->_isUSCountry($countyDest) ? false : true;
    }

    /**
     * Return content types of package
     *
     * @return array
     */
    #[\Override]
    public function getContentTypes(\Maho\DataObject $params)
    {
        $countryShipper     = $params->getCountryShipper();
        $countryRecipient   = $params->getCountryRecipient();

        if ($countryShipper == self::USA_COUNTRY_ID
            && $countryRecipient != self::USA_COUNTRY_ID
        ) {
            return [
                'MERCHANDISE' => Mage::helper('usa')->__('Merchandise'),
                'SAMPLE' => Mage::helper('usa')->__('Sample'),
                'GIFT' => Mage::helper('usa')->__('Gift'),
                'DOCUMENTS' => Mage::helper('usa')->__('Documents'),
                'RETURN' => Mage::helper('usa')->__('Return'),
                'OTHER' => Mage::helper('usa')->__('Other'),
            ];
        }
        return [];
    }

    /**
     * Parse zip from string to zip5-zip4
     *
     * @param string $zipString
     * @param bool $returnFull
     * @return array
     */
    protected function _parseZip($zipString, $returnFull = false)
    {
        $zip4 = '';
        $zip5 = '';
        $zip = [$zipString];
        if (preg_match('/[\\d\\w]{5}\\-[\\d\\w]{4}/', $zipString) != 0) {
            $zip = explode('-', $zipString);
        }
        $zipCount = count($zip);
        for ($i = 0; $i < $zipCount; ++$i) {
            if (strlen($zip[$i]) == 5) {
                $zip5 = $zip[$i];
            } elseif (strlen($zip[$i]) == 4) {
                $zip4 = $zip[$i];
            }
        }
        if (empty($zip5) && empty($zip4) && $returnFull) {
            $zip5 = $zipString;
        }

        return [$zip5, $zip4];
    }

    /**
     * @deprecated
     */
    protected function _methodsMapper($method, $valuesToLabels = true)
    {
        return $method;
    }

    /**
     * @deprecated
     */
    public function getMethodLabel($value)
    {
        return $this->_methodsMapper($value, true);
    }

    /**
     * Get value of method by its label
     * @deprecated
     */
    public function getMethodValue($label)
    {
        return $this->_methodsMapper($label, false);
    }

    /**
     * @deprecated
     */
    protected function setTrackingReqeust()
    {
        $this->setTrackingRequest();
    }
}
