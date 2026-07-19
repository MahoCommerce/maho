<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_GoogleAnalytics
 */

class Mage_GoogleAnalytics_Block_Metapixel extends Mage_Core_Block_Template
{
    protected const CHECKOUT_MODULE_NAME = 'checkout';
    protected const CHECKOUT_CONTROLLER_NAME = 'onepage';

    protected function _isAvailable(): bool
    {
        return Mage::helper('googleanalytics')->isMetaPixelEnabled();
    }

    /**
     * @throws JsonException
     */
    public function _getEnhancedEcommerceData(): string
    {
        $result = [];
        $request = $this->getRequest();
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();
        $helper = Mage::helper('googleanalytics');

        // Meta Pixel doesn't have a standard 'RemoveFromCart' event.
        Mage::getSingleton('core/session')->unsRemovedProductsForAnalytics();

        /**
         * This event signifies that an item was added to a cart for purchase.
         * @link https://developers.facebook.com/docs/meta-pixel/reference#standard-events
         */
        $addedProducts = Mage::getSingleton('core/session')->getAddedProductsForAnalytics();
        if ($addedProducts) {
            foreach ($addedProducts as $_addedProduct) {
                $eventData = [];
                $eventData['value'] = $helper->formatPrice($_addedProduct['price'] * $_addedProduct['qty']);
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['content_name'] = $_addedProduct['name'];
                $eventData['content_type'] = 'product';
                $eventData['contents'] = [
                    [
                        'id' => $_addedProduct['sku'],
                        'quantity' => (int) $_addedProduct['qty'],
                        'item_price' => $helper->formatPrice($_addedProduct['price']),
                    ],
                ];
                $result[] = ['AddToCart', $eventData];
            }
            Mage::getSingleton('core/session')->unsAddedProductsForAnalytics();
        }

        if ($moduleName == 'catalog' && $controllerName == 'product') {
            // This event signifies that some content was shown to the user.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
            $productViewed = Mage::registry('current_product');
            if ($productViewed) {
                $eventData = [];
                $eventData['value'] = $helper->formatPrice($productViewed->getFinalPrice());
                $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                $eventData['content_name'] = $productViewed->getName();
                $eventData['content_ids'] = [$productViewed->getSku()];
                $eventData['content_type'] = 'product';
                $eventData['contents'] = [
                    [
                        'id' => $productViewed->getSku(),
                        'quantity' => 1,
                        'item_price' => $helper->formatPrice($productViewed->getFinalPrice()),
                    ],
                ];
                $category = Mage::registry('current_category');
                if ($category) {
                    $eventData['content_category'] = $category->getName();
                }
                $result[] = ['ViewContent', $eventData];
            }
        } elseif ($moduleName == 'catalog' && $controllerName == 'category') {
            // Log this event when the user has been presented with a list of items of a certain category.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
            $layer = Mage::getSingleton('catalog/layer');
            $category = $layer->getCurrentCategory();
            if ($category) {
                $productCollection = clone $layer->getProductCollection();
                $productCollection->addAttributeToSelect(['sku', 'name', 'price', 'special_price', 'final_price']); // Select necessary attributes

                $toolbarBlock = Mage::app()->getLayout()->getBlock('product_list_toolbar');
                if ($toolbarBlock) {
                    $pageSize = $toolbarBlock->getLimit();
                    $currentPage = $toolbarBlock->getCurrentPage();
                    if ($pageSize !== 'all') {
                        $productCollection->setPageSize($pageSize)->setCurPage($currentPage);
                    }
                } else {
                    // Fallback or default page size if toolbar isn't available
                    $productCollection->setPageSize(12)->setCurPage(1);
                }

                if ($productCollection->getSize() > 0) {
                    $contentIds = [];
                    $contents = [];
                    $totalValue = 0.00;

                    foreach ($productCollection as $productViewed) {
                        $productId = $productViewed->getSku();
                        $productPrice = $helper->formatPrice($productViewed->getFinalPrice());
                        $contentIds[] = $productId;
                        $contents[] = [
                            'id' => $productId,
                            'quantity' => 1, // Quantity is typically 1 for a list view item
                            'item_price' => $productPrice,
                        ];
                        $totalValue += $productViewed->getFinalPrice();
                    }

                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($totalValue); // Sum of displayed product prices
                    $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                    ;
                    $eventData['content_name'] = $category->getName();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product_group'; // Use 'product_group' for lists/categories
                    $eventData['contents'] = $contents;
                    $result[] = ['ViewContent', $eventData];
                }
            }
        } elseif ($moduleName == 'checkout' && $controllerName == 'cart') {
            // Meta Pixel Standard Events do not include a specific "ViewCart" event.
            // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
        } elseif ($moduleName == static::CHECKOUT_MODULE_NAME && $controllerName == static::CHECKOUT_CONTROLLER_NAME) {
            /**
             * Meta Pixel Event: InitiateCheckout
             * This event signifies that a user has begun the checkout process.
             * @link https://developers.facebook.com/docs/meta-pixel/reference#standard-events
             */
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if ($quote && $quote->hasItems()) {
                $productCollection = $quote->getAllVisibleItems(); // Use visible items to avoid double counting configurables etc.
                if (!empty($productCollection)) {
                    $contentIds = [];
                    $contents = [];
                    $totalValue = 0.00;
                    $numItems = 0;

                    foreach ($productCollection as $item) {
                        $_product = $item->getProduct();
                        $productId = $_product->getSku();
                        $contentIds[] = $productId;
                        $contents[] = [
                            'id' => $productId,
                            'quantity' => (int) $item->getQty(),
                            'item_price' => $helper->formatPrice($item->getBasePrice()),
                        ];
                        $totalValue += $item->getBaseRowTotal();
                        $numItems += (int) $item->getQty();
                    }

                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($totalValue);
                    $eventData['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product';
                    $eventData['num_items'] = $numItems;
                    $eventData['contents'] = $contents;
                    $result[] = ['InitiateCheckout', $eventData];
                }
            }
        }

        // This event signifies when one or more items is purchased by a user.
        // @see https://developers.facebook.com/docs/meta-pixel/reference#standard-events
        $orderIds = $this->getOrderIds(); // Assuming this method retrieves order IDs from the success page
        if (!empty($orderIds) && is_array($orderIds)) {
            $collection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', ['in' => $orderIds]);

            /** @var Mage_Sales_Model_Order $order */
            foreach ($collection as $order) {
                $contentIds = [];
                $contents = [];
                $numItems = 0;

                // Use visible items to avoid double counting configurables, etc.
                /** @var Mage_Sales_Model_Order_Item $item */
                foreach ($order->getAllVisibleItems() as $item) {
                    $productId = $item->getSku();
                    $contentIds[] = $productId;
                    $contents[] = [
                        'id' => $productId,
                        'quantity' => (int) $item->getQtyOrdered(),
                        'item_price' => $helper->formatPrice($item->getBasePrice()),
                    ];
                    $numItems += (int) $item->getQtyOrdered();
                }

                if (!empty($contents)) {
                    $eventData = [];
                    $eventData['value'] = $helper->formatPrice($order->getBaseGrandTotal());
                    $eventData['currency'] = $order->getBaseCurrencyCode();
                    $eventData['content_ids'] = $contentIds;
                    $eventData['content_type'] = 'product';
                    $eventData['contents'] = $contents;
                    $eventData['num_items'] = $numItems;
                    $eventData['order_id'] = $order->getIncrementId();
                    $result[] = ['Purchase', $eventData];
                }
            }
        }

        $metaDataTransport = new \Maho\DataObject();
        $metaDataTransport->setData($result);
        Mage::dispatchEvent('googleanalytics_meta_send_data_before', ['meta_data_transport' => $metaDataTransport]);
        $result = $metaDataTransport->getData();

        $eventStrings = [];
        foreach ($result as $metaEvent) {
            $eventDataJson = json_encode($metaEvent[1], JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK);
            $eventDataJsonEscaped = str_replace("'", "\\'", $eventDataJson);
            $eventStrings[] = "fbq('track', '{$metaEvent[0]}', {$eventDataJsonEscaped});";
        }
        return implode("\n", $eventStrings);
    }

    /**
     * Advanced Matching parameters for fbq('init'), SHA-256 hashed per Meta spec.
     *
     * Listeners of the dispatched event receive already-hashed values and must
     * keep any value they add SHA-256 hashed as well.
     *
     * @return array<string, string> param => hashed value, empty when disabled or no data
     */
    public function getAdvancedMatchingData(): array
    {
        if (!Mage::helper('googleanalytics')->isMetaPixelAdvancedMatchingEnabled()) {
            return [];
        }

        $data = $this->buildAdvancedMatchingData($this->_collectRawCustomerData());

        $transport = new \Maho\DataObject();
        $transport->setData($data);
        Mage::dispatchEvent('googleanalytics_metapixel_advanced_matching_data', [
            'transport' => $transport,
            'block' => $this,
        ]);
        return $transport->getData();
    }

    /**
     * Raw customer values keyed by Meta parameter name, preferring the just-placed
     * order (success page, covers guest checkout) over the logged-in customer.
     *
     * @return array<string, string>
     */
    protected function _collectRawCustomerData(): array
    {
        $orderIds = $this->getOrderIds();
        if (!empty($orderIds) && is_array($orderIds)) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', ['in' => $orderIds])
                ->getFirstItem();
            if ($order->getId()) {
                $address = $order->getBillingAddress();
                return array_filter([
                    'em' => (string) $order->getCustomerEmail(),
                    'fn' => (string) $order->getCustomerFirstname(),
                    'ln' => (string) $order->getCustomerLastname(),
                    'db' => (string) $order->getCustomerDob(),
                    'ge' => $this->_getGenderCode((int) $order->getCustomerGender()),
                    'ph' => $address ? (string) $address->getTelephone() : '',
                    'ct' => $address ? (string) $address->getCity() : '',
                    'st' => $this->_getRegionForMatching($address ?: null),
                    'zp' => $address ? (string) $address->getPostcode() : '',
                    'country' => $address ? (string) $address->getCountryId() : '',
                    'external_id' => (string) $order->getCustomerId(),
                ]);
            }
        }

        $session = Mage::getSingleton('customer/session');
        if (!$session->isLoggedIn()) {
            return [];
        }
        $customer = $session->getCustomer();
        $address = $customer->getDefaultBillingAddress() ?: null;
        return array_filter([
            'em' => (string) $customer->getEmail(),
            'fn' => (string) $customer->getFirstname(),
            'ln' => (string) $customer->getLastname(),
            'db' => (string) $customer->getDob(),
            'ge' => $this->_getGenderCode((int) $customer->getGender()),
            'ph' => $address ? (string) $address->getTelephone() : '',
            'ct' => $address ? (string) $address->getCity() : '',
            'st' => $this->_getRegionForMatching($address),
            'zp' => $address ? (string) $address->getPostcode() : '',
            'country' => $address ? (string) $address->getCountryId() : '',
            'external_id' => (string) $customer->getId(),
        ]);
    }

    /**
     * Map the customer gender attribute option id to Meta's 'm'/'f' codes.
     *
     * Matches against the admin (default) option labels so store-level
     * translations of the gender options don't break the mapping.
     */
    protected function _getGenderCode(int $optionId): string
    {
        if (!$optionId) {
            return '';
        }
        try {
            $source = Mage::getResourceModel('customer/customer')
                ->getAttribute('gender')
                ->getSource();
            // Admin (default) labels when available, so store-level translations
            // of the option labels don't break the mapping
            $options = $source instanceof Mage_Eav_Model_Entity_Attribute_Source_Table
                ? $source->getAllOptions(false, true)
                : $source->getAllOptions();
        } catch (Throwable $e) {
            return '';
        }
        foreach ($options as $option) {
            if ((int) ($option['value'] ?? 0) === $optionId) {
                return match (mb_strtolower(trim((string) ($option['label'] ?? '')))) {
                    'male', 'm' => 'm',
                    'female', 'f' => 'f',
                    default => '',
                };
            }
        }
        return '';
    }

    /**
     * Region value for Meta's `st` parameter: the 2-letter region code where one
     * exists (e.g. US/CA); otherwise the full region name, which Meta matches
     * better than internal 3+ letter directory codes.
     */
    protected function _getRegionForMatching(?Mage_Customer_Model_Address_Abstract $address): string
    {
        if (!$address) {
            return '';
        }
        $code = (string) $address->getRegionCode();
        if (preg_match('/^[A-Za-z]{2}$/', $code)) {
            return $code;
        }
        return (string) $address->getRegion();
    }

    /**
     * Normalize raw values per the Meta Advanced Matching spec and SHA-256 hash
     * them. Pure function of its input: empty or invalid values are omitted.
     *
     * @see https://developers.facebook.com/docs/meta-pixel/advanced/advanced-matching
     * @param array<string, string> $raw
     * @return array<string, string>
     */
    public function buildAdvancedMatchingData(array $raw): array
    {
        $normalized = [];
        foreach ($raw as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $normalized[$key] = match ($key) {
                'em' => mb_strtolower($value),
                'ph' => $this->_normalizePhone($value, trim((string) ($raw['country'] ?? ''))),
                'fn', 'ln', 'ct', 'st' => (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value)),
                'zp' => str_replace(' ', '', mb_strtolower(explode('-', $value)[0])),
                'country' => mb_strtolower(substr($value, 0, 2)),
                'ge' => in_array($value, ['m', 'f'], true) ? $value : '',
                'db' => $this->_normalizeDob($value),
                default => $value,
            };
            if ($normalized[$key] === '') {
                unset($normalized[$key]);
            }
        }
        return array_map(static fn(string $v): string => hash('sha256', $v), $normalized);
    }

    /**
     * Meta expects birthdates as YYYYMMDD; dob is stored as 'Y-m-d' or 'Y-m-d H:i:s'.
     */
    protected function _normalizeDob(string $dob): string
    {
        $timestamp = strtotime($dob);
        if ($timestamp === false) {
            return '';
        }
        $date = date('Ymd', $timestamp);
        // Reject non-real dates: strtotime() rolls the legacy '0000-00-00'
        // sentinel back to year -1 instead of failing, yielding '-00011130'
        return preg_match('/^\d{8}$/', $date) ? $date : '';
    }

    /**
     * Normalize a phone number to digits with a country calling code, per the
     * Meta spec ("include the country code, even if all of your data is from
     * the same country").
     *
     * Numbers entered in international format ('+' or '00' prefix) are kept as
     * entered; national-format numbers get their trunk '0' dropped (kept for
     * Italy, where it is part of the number) and the billing country's calling
     * code prepended. Unknown country: digits are sent as entered (best effort).
     */
    protected function _normalizePhone(string $phone, string $countryId): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with(trim($phone), '+')) {
            return $digits;
        }
        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }
        $callingCode = self::COUNTRY_CALLING_CODES[strtoupper($countryId)] ?? '';
        if ($callingCode === '') {
            return $digits;
        }
        if (strtoupper($countryId) !== 'IT') {
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                return '';
            }
        }
        // Long enough to already include the country code it starts with
        if (str_starts_with($digits, $callingCode) && strlen($digits) >= strlen($callingCode) + 9) {
            return $digits;
        }
        return $callingCode . $digits;
    }

    /**
     * ITU-T E.164 country calling codes by ISO 3166-1 alpha-2 country.
     */
    private const COUNTRY_CALLING_CODES = [
        'AD' => '376', 'AE' => '971', 'AF' => '93', 'AG' => '1', 'AI' => '1', 'AL' => '355', 'AM' => '374',
        'AO' => '244', 'AR' => '54', 'AS' => '1', 'AT' => '43', 'AU' => '61', 'AW' => '297', 'AX' => '358',
        'AZ' => '994', 'BA' => '387', 'BB' => '1', 'BD' => '880', 'BE' => '32', 'BF' => '226', 'BG' => '359',
        'BH' => '973', 'BI' => '257', 'BJ' => '229', 'BL' => '590', 'BM' => '1', 'BN' => '673', 'BO' => '591',
        'BQ' => '599', 'BR' => '55', 'BS' => '1', 'BT' => '975', 'BW' => '267', 'BY' => '375', 'BZ' => '501',
        'CA' => '1', 'CC' => '61', 'CD' => '243', 'CF' => '236', 'CG' => '242', 'CH' => '41', 'CI' => '225',
        'CK' => '682', 'CL' => '56', 'CM' => '237', 'CN' => '86', 'CO' => '57', 'CR' => '506', 'CU' => '53',
        'CV' => '238', 'CW' => '599', 'CX' => '61', 'CY' => '357', 'CZ' => '420', 'DE' => '49', 'DJ' => '253',
        'DK' => '45', 'DM' => '1', 'DO' => '1', 'DZ' => '213', 'EC' => '593', 'EE' => '372', 'EG' => '20',
        'EH' => '212', 'ER' => '291', 'ES' => '34', 'ET' => '251', 'FI' => '358', 'FJ' => '679', 'FK' => '500',
        'FM' => '691', 'FO' => '298', 'FR' => '33', 'GA' => '241', 'GB' => '44', 'GD' => '1', 'GE' => '995',
        'GF' => '594', 'GG' => '44', 'GH' => '233', 'GI' => '350', 'GL' => '299', 'GM' => '220', 'GN' => '224',
        'GP' => '590', 'GQ' => '240', 'GR' => '30', 'GT' => '502', 'GU' => '1', 'GW' => '245', 'GY' => '592',
        'HK' => '852', 'HN' => '504', 'HR' => '385', 'HT' => '509', 'HU' => '36', 'ID' => '62', 'IE' => '353',
        'IL' => '972', 'IM' => '44', 'IN' => '91', 'IO' => '246', 'IQ' => '964', 'IR' => '98', 'IS' => '354',
        'IT' => '39', 'JE' => '44', 'JM' => '1', 'JO' => '962', 'JP' => '81', 'KE' => '254', 'KG' => '996',
        'KH' => '855', 'KI' => '686', 'KM' => '269', 'KN' => '1', 'KP' => '850', 'KR' => '82', 'KW' => '965',
        'KY' => '1', 'KZ' => '7', 'LA' => '856', 'LB' => '961', 'LC' => '1', 'LI' => '423', 'LK' => '94',
        'LR' => '231', 'LS' => '266', 'LT' => '370', 'LU' => '352', 'LV' => '371', 'LY' => '218', 'MA' => '212',
        'MC' => '377', 'MD' => '373', 'ME' => '382', 'MF' => '590', 'MG' => '261', 'MH' => '692', 'MK' => '389',
        'ML' => '223', 'MM' => '95', 'MN' => '976', 'MO' => '853', 'MP' => '1', 'MQ' => '596', 'MR' => '222',
        'MS' => '1', 'MT' => '356', 'MU' => '230', 'MV' => '960', 'MW' => '265', 'MX' => '52', 'MY' => '60',
        'MZ' => '258', 'NA' => '264', 'NC' => '687', 'NE' => '227', 'NF' => '672', 'NG' => '234', 'NI' => '505',
        'NL' => '31', 'NO' => '47', 'NP' => '977', 'NR' => '674', 'NU' => '683', 'NZ' => '64', 'OM' => '968',
        'PA' => '507', 'PE' => '51', 'PF' => '689', 'PG' => '675', 'PH' => '63', 'PK' => '92', 'PL' => '48',
        'PM' => '508', 'PR' => '1', 'PS' => '970', 'PT' => '351', 'PW' => '680', 'PY' => '595', 'QA' => '974',
        'RE' => '262', 'RO' => '40', 'RS' => '381', 'RU' => '7', 'RW' => '250', 'SA' => '966', 'SB' => '677',
        'SC' => '248', 'SD' => '249', 'SE' => '46', 'SG' => '65', 'SH' => '290', 'SI' => '386', 'SJ' => '47',
        'SK' => '421', 'SL' => '232', 'SM' => '378', 'SN' => '221', 'SO' => '252', 'SR' => '597', 'SS' => '211',
        'ST' => '239', 'SV' => '503', 'SX' => '1', 'SY' => '963', 'SZ' => '268', 'TC' => '1', 'TD' => '235',
        'TG' => '228', 'TH' => '66', 'TJ' => '992', 'TK' => '690', 'TL' => '670', 'TM' => '993', 'TN' => '216',
        'TO' => '676', 'TR' => '90', 'TT' => '1', 'TV' => '688', 'TW' => '886', 'TZ' => '255', 'UA' => '380',
        'UG' => '256', 'US' => '1', 'UY' => '598', 'UZ' => '998', 'VA' => '379', 'VC' => '1', 'VE' => '58',
        'VG' => '1', 'VI' => '1', 'VN' => '84', 'VU' => '678', 'WF' => '681', 'WS' => '685', 'XK' => '383',
        'YE' => '967', 'YT' => '262', 'ZA' => '27', 'ZM' => '260', 'ZW' => '263',
    ];

    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->_isAvailable()) {
            return '';
        }
        return parent::_toHtml();
    }
}
