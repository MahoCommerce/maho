<?php

/**
 * Organization / LocalBusiness JSON-LD structured data.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_Jsonld_Organization extends Maho_StructuredData_Block_Jsonld_Abstract
{
    protected string $_eventObject = 'organization';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $helper = Mage::helper('structureddata');
        $type = $this->_getOrganizationType();

        $data = [
            '@context' => Maho_StructuredData_Helper_Data::SCHEMA,
            '@type' => $type,
            'name' => $helper->getOrganizationName(),
            'url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
        ];

        $logo = $helper->getOrganizationLogoUrl();
        if ($logo !== '') {
            $data['logo'] = $logo;
        }

        $contactPoint = $this->_getContactPoint();
        if ($contactPoint !== []) {
            $data['contactPoint'] = $contactPoint;
        }

        // address is valid on Organization/OnlineStore (optional) and required on LocalBusiness, so
        // emit it whenever the store address is configured rather than only for the LocalBusiness type.
        $address = $this->_getAddress();
        if ($address !== []) {
            $data['address'] = $address;
        }

        $sameAs = $helper->getSocialProfiles();
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * Resolve the configured organization @type, validated against the allowed schema.org types so
     * only a known-good value is ever emitted (falls back to the 'OnlineStore' default for anything
     * else, matching the config.xml default).
     */
    protected function _getOrganizationType(): string
    {
        $type = (string) Mage::getStoreConfig(Maho_StructuredData_Helper_Data::XML_PATH_ORGANIZATION_TYPE);
        $allowed = array_column(
            Mage::getSingleton('structureddata/system_config_source_organization_type')->toOptionArray(),
            'value',
        );
        return in_array($type, $allowed, true) ? $type : 'OnlineStore';
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getContactPoint(): array
    {
        $phone = trim((string) Mage::getStoreConfig('general/store_information/phone'));
        $email = trim((string) Mage::getStoreConfig('trans_email/ident_support/email'));

        if ($phone === '' && $email === '') {
            return [];
        }

        $contactPoint = [
            '@type' => 'ContactPoint',
            'contactType' => 'customer service',
        ];
        if ($phone !== '') {
            $contactPoint['telephone'] = $phone;
        }
        if ($email !== '') {
            $contactPoint['email'] = $email;
        }

        return $contactPoint;
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getAddress(): array
    {
        $address = trim((string) Mage::getStoreConfig('general/store_information/address'));
        if ($address === '') {
            return [];
        }

        $data = [
            '@type' => 'PostalAddress',
            'streetAddress' => preg_replace('/\s+/', ' ', $address),
        ];

        // Google's LocalBusiness address requires a country to validate.
        $country = trim((string) Mage::getStoreConfig('general/store_information/merchant_country'));
        if ($country !== '') {
            $data['addressCountry'] = $country;
        }

        return $data;
    }
}
