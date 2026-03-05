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
                $headerName = (string) $this->getConfigData('custom_header_name');
                $token = $this->getConfigData('auth_token');
                if ($headerName && $token && preg_match('/^[a-zA-Z0-9\-]+$/', $headerName)) {
                    $headers[$headerName] = $token;
                }
            }

            $timeout = min((int) $this->getConfigData('timeout') ?: 10, 60);

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
        if ($this->_lastMethods) {
            return $this->_lastMethods;
        }

        return ['shippingbridge' => $this->getConfigData('title')];
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
            $attributeCodes = $this->_getAdditionalAttributeCodes();
            $productIds = [];
            $superAttributeMap = [];

            // First pass: collect product IDs and super attributes for configurables
            foreach ($allItems as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }
                $productId = (int) $item->getProductId();
                $productIds[] = $productId;

                if ($item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                    $superAttributeMap[$productId] = $this->_getSuperAttributeCodes($item->getProduct());
                    // Collect child simple product ID for attribute resolution
                    if ($item->getHasChildren()) {
                        foreach ($item->getChildren() as $child) {
                            $productIds[] = (int) $child->getProductId();
                        }
                    }
                } elseif ($item->getHasChildren() && $item->isShipSeparately()) {
                    // Ship-separately children will be sent as individual items
                    foreach ($item->getChildren() as $child) {
                        if (!$child->getProduct()->isVirtual()) {
                            $productIds[] = (int) $child->getProductId();
                        }
                    }
                }
            }

            // Merge all attribute codes needed for the collection query
            $allSuperCodes = array_unique(array_merge([], ...array_values($superAttributeMap)));
            $allCodesToLoad = array_unique(array_merge($attributeCodes, $allSuperCodes));

            $products = [];
            if ($allCodesToLoad && $productIds) {
                $products = $this->_loadProductAttributes(array_unique($productIds), $allCodesToLoad);
            }

            // Second pass: build item data
            foreach ($allItems as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    // Ship-separately: send each child as its own item
                    foreach ($item->getChildren() as $child) {
                        if ($child->getProduct()->isVirtual()) {
                            continue;
                        }
                        $childData = $this->_buildItemData($child, (float) $item->getQty() * (float) $child->getQty());
                        $this->_mergeAttributeValues($childData, (int) $child->getProductId(), $attributeCodes, $products);
                        $items[] = $childData;
                    }
                } else {
                    $itemData = $this->_buildItemData($item);
                    $parentProductId = (int) $item->getProductId();
                    $isConfigurable = $item->getProductType() === Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;

                    // For configurables, resolve attributes from the child simple product
                    $attrProductId = $parentProductId;
                    if ($isConfigurable && $item->getHasChildren()) {
                        $children = $item->getChildren();
                        $firstChild = reset($children);
                        if ($firstChild) {
                            $attrProductId = (int) $firstChild->getProductId();
                        }
                    }

                    // Always include super attributes for configurables
                    $superCodes = $superAttributeMap[$parentProductId] ?? [];
                    if ($superCodes) {
                        $this->_mergeAttributeValues($itemData, $attrProductId, $superCodes, $products, $parentProductId);
                    }

                    // Additional attributes: child first, fall back to parent
                    $this->_mergeAttributeValues($itemData, $attrProductId, $attributeCodes, $products, $parentProductId);
                    $items[] = $itemData;
                }
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
                'region' => $this->_getDestRegionName($request),
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

    protected function _buildItemData(Mage_Sales_Model_Quote_Item_Abstract $item, ?float $qty = null): array
    {
        return [
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'qty' => $qty ?? (float) $item->getQty(),
            'weight' => (float) $item->getWeight(),
            'price' => (float) $item->getPrice(),
            'row_total' => (float) $item->getRowTotal(),
        ];
    }

    protected function _mergeAttributeValues(array &$itemData, int $productId, array $attributeCodes, array $products, ?int $fallbackProductId = null): void
    {
        if (!$attributeCodes || !isset($products[$productId])) {
            return;
        }
        foreach ($attributeCodes as $code) {
            $value = $this->_formatAttributeValue($products[$productId], $code);
            if ($value === null && $fallbackProductId !== null && $fallbackProductId !== $productId && isset($products[$fallbackProductId])) {
                $value = $this->_formatAttributeValue($products[$fallbackProductId], $code);
            }
            $itemData[$code] = $value;
        }
    }

    protected function _getSuperAttributeCodes(Mage_Catalog_Model_Product $product): array
    {
        /** @var Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $codes = [];
        foreach ($typeInstance->getConfigurableAttributes($product) as $attribute) {
            $codes[] = $attribute->getProductAttribute()->getAttributeCode();
        }
        return $codes;
    }

    protected function _getAdditionalAttributeCodes(): array
    {
        $value = (string) $this->getConfigData('additional_attributes');
        if ($value === '') {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $value)));
    }

    /**
     * @return array<int, Mage_Catalog_Model_Product>
     */
    protected function _loadProductAttributes(array $productIds, array $attributeCodes): array
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addAttributeToSelect($attributeCodes);
        $collection->addFieldToFilter('entity_id', ['in' => $productIds]);
        $collection->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);

        $products = [];
        foreach ($collection as $product) {
            $products[(int) $product->getId()] = $product;
        }
        return $products;
    }

    protected function _formatAttributeValue(Mage_Catalog_Model_Product $product, string $code): mixed
    {
        $attribute = $product->getResource()->getAttribute($code);
        if (!$attribute) {
            return null;
        }

        $value = $product->getData($code);
        if ($value === null || $value === '') {
            return null;
        }

        $inputType = $attribute->getFrontendInput();
        if ($inputType === 'select' || $inputType === 'multiselect') {
            $previousStoreId = $attribute->getStoreId();
            $adminText = $attribute->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
                ->getSource()
                ->getOptionText($value);
            $attribute->setStoreId($previousStoreId);

            return [
                'value' => $value,
                'label' => $adminText ?: $value,
            ];
        }

        return $value;
    }

    protected function _getDestRegionName(Mage_Shipping_Model_Rate_Request $request): ?string
    {
        $regionId = $request->getDestRegionId();
        if ($regionId) {
            $region = Mage::getModel('directory/region')->load($regionId);
            if ($region->getId()) {
                return $region->getName();
            }
        }
        return $request->getDestRegionCode();
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
