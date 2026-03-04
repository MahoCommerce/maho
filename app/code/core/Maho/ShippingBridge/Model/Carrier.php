<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ShippingBridge
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ShippingBridge_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'shippingbridge';

    protected array $_lastMethods = [];

    /**
     * @return Mage_Shipping_Model_Rate_Result|false
     */
    #[\Override]
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $checkResult = $this->checkAvailableShipCountries($request);
        if ($checkResult !== $this) {
            return $checkResult ?: false;
        }

        $apiUrl = $this->getConfigData('api_url');
        if (empty($apiUrl)) {
            Mage::log('Shipping Bridge: API endpoint URL is not configured', Mage::LOG_ERROR, 'shipping_bridge.log');
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');
        $payload = $this->_buildRequestPayload($request);

        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            $authType = $this->getConfigData('auth_type');
            if ($authType === 'bearer') {
                $token = $this->getConfigData('auth_token');
                if ($token) {
                    $headers['Authorization'] = 'Bearer ' . $token;
                }
            } elseif ($authType === 'custom_header') {
                $headerName = $this->getConfigData('custom_header_name');
                $token = $this->getConfigData('auth_token');
                if ($headerName && $token) {
                    $headers[$headerName] = $token;
                }
            }

            $timeout = (int) $this->getConfigData('timeout') ?: 10;

            if ($this->getConfigFlag('debug')) {
                Mage::log(
                    "Shipping Bridge Request:\nURL: {$apiUrl}\n" . Mage::helper('core')->jsonEncode($payload),
                    Mage::LOG_DEBUG,
                    'shipping_bridge.log',
                );
            }

            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => $timeout]);
            $response = $client->request('POST', $apiUrl, [
                'headers' => $headers,
                'body' => Mage::helper('core')->jsonEncode($payload),
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                Mage::log(
                    "Shipping Bridge: API returned HTTP {$statusCode}",
                    Mage::LOG_ERROR,
                    'shipping_bridge.log',
                );
                return $result;
            }

            $responseBody = $response->getContent();

            if ($this->getConfigFlag('debug')) {
                Mage::log(
                    "Shipping Bridge Response:\n{$responseBody}",
                    Mage::LOG_DEBUG,
                    'shipping_bridge.log',
                );
            }

            $methods = $this->_parseResponse($responseBody);
            if ($methods === null) {
                return $result;
            }

            $this->_lastMethods = [];
            foreach ($methods as $methodData) {
                $method = Mage::getModel('shipping/rate_result_method');
                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData('title'));
                $method->setMethod($methodData['code']);
                $method->setMethodTitle($methodData['title']);
                $method->setPrice((float) $methodData['price']);
                $method->setCost((float) ($methodData['cost'] ?? $methodData['price']));

                if (!empty($methodData['description'])) {
                    $method->setMethodDescription($methodData['description']);
                }
                if (!empty($methodData['logo'])) {
                    $method->setMethodLogo($methodData['logo']);
                }

                $result->append($method);
                $this->_lastMethods[$methodData['code']] = $methodData['title'];
            }
        } catch (\Throwable $e) {
            Mage::log(
                'Shipping Bridge: ' . $e->getMessage(),
                Mage::LOG_ERROR,
                'shipping_bridge.log',
            );
            return $result;
        }

        return $result;
    }

    /**
     * @return array
     */
    #[\Override]
    public function getAllowedMethods()
    {
        return $this->_lastMethods;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function isTrackingAvailable()
    {
        return true;
    }

    protected function _buildRequestPayload(Mage_Shipping_Model_Rate_Request $request): array
    {
        $items = [];
        $allItems = $request->getAllItems();
        if ($allItems) {
            foreach ($allItems as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                $items[] = [
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'qty' => (float) $item->getQty(),
                    'weight' => (float) $item->getWeight(),
                    'price' => (float) $item->getPrice(),
                    'row_total' => (float) $item->getRowTotal(),
                ];
            }
        }

        $payload = [
            'cart' => [
                'items' => $items,
                'totals' => [
                    'subtotal' => (float) $request->getPackageValue(),
                    'weight' => (float) $request->getPackageWeight(),
                    'qty' => (float) $request->getPackageQty(),
                ],
            ],
            'shipping_address' => [
                'firstname' => $request->getDestFirstname(),
                'lastname' => $request->getDestLastname(),
                'street' => $request->getDestStreet(),
                'city' => $request->getDestCity(),
                'region' => $request->getDestRegionCode(),
                'region_code' => $request->getDestRegionCode(),
                'postcode' => $request->getDestPostcode(),
                'country_id' => $request->getDestCountryId(),
            ],
            'currency' => $request->getPackageCurrency()->getCurrencyCode(),
            'store_id' => (int) $request->getStoreId(),
            'customer' => $this->_buildCustomerData($request),
        ];

        return $payload;
    }

    protected function _buildCustomerData(Mage_Shipping_Model_Rate_Request $request): array
    {
        $allItems = $request->getAllItems();
        $quote = $allItems ? reset($allItems)->getQuote() : null;

        if (!$quote) {
            return [];
        }

        $groupId = (int) $quote->getCustomerGroupId();
        $group = Mage::getModel('customer/group')->load($groupId);

        return [
            'customer_id' => $quote->getCustomerId() ? (int) $quote->getCustomerId() : null,
            'email' => $quote->getBillingAddress()->getEmail(),
            'group_id' => $groupId,
            'group_code' => $group->getCustomerGroupCode(),
            'is_guest' => !$quote->getCustomerId(),
        ];
    }

    protected function _parseResponse(string $responseBody): ?array
    {
        try {
            $data = Mage::helper('core')->jsonDecode($responseBody);
        } catch (\JsonException $e) {
            Mage::log(
                'Shipping Bridge: Invalid JSON response - ' . $e->getMessage(),
                Mage::LOG_ERROR,
                'shipping_bridge.log',
            );
            return null;
        }

        if (!isset($data['methods']) || !is_array($data['methods'])) {
            Mage::log(
                'Shipping Bridge: Response missing "methods" array',
                Mage::LOG_ERROR,
                'shipping_bridge.log',
            );
            return null;
        }

        $methods = [];
        foreach ($data['methods'] as $i => $method) {
            if (empty($method['code']) || empty($method['title']) || !isset($method['price'])) {
                Mage::log(
                    "Shipping Bridge: Method at index {$i} missing required fields (code, title, price)",
                    Mage::LOG_WARNING,
                    'shipping_bridge.log',
                );
                continue;
            }
            $methods[] = $method;
        }

        return $methods;
    }
}
