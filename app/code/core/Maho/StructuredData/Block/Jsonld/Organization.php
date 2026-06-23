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
    #[\Override]
    protected function isTypeEnabled(): bool
    {
        $helper = Mage::helper('structureddata');
        return $helper->isOrganizationEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getStructuredData(): array
    {
        $helper = Mage::helper('structureddata');
        $type = (string) Mage::getStoreConfig(Maho_StructuredData_Helper_Data::XML_PATH_ORGANIZATION_TYPE) ?: 'Organization';

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

        if ($type === 'LocalBusiness') {
            $address = $this->_getAddress();
            if ($address !== []) {
                $data['address'] = $address;
            }
        }

        $sameAs = $helper->getSocialProfiles();
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
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
