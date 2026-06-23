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
            '@context' => 'https://schema.org/',
            '@type' => $type,
            'name' => $this->_getName(),
            'url' => Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB),
        ];

        $logo = $this->_getLogoUrl();
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

    protected function _getName(): string
    {
        $name = trim((string) Mage::getStoreConfig('general/store_information/name'));
        if ($name !== '') {
            return $name;
        }
        return (string) Mage::app()->getStore()->getFrontendName();
    }

    protected function _getLogoUrl(): string
    {
        $configured = trim((string) Mage::getStoreConfig(Maho_StructuredData_Helper_Data::XML_PATH_ORGANIZATION_LOGO_URL));
        if ($configured !== '') {
            return $configured;
        }

        $logoSrc = (string) Mage::getStoreConfig('design/header/logo_src');
        if ($logoSrc !== '') {
            return $this->getSkinUrl($logoSrc);
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getContactPoint(): array
    {
        $phone = trim((string) Mage::getStoreConfig(Maho_StructuredData_Helper_Data::XML_PATH_ORGANIZATION_CONTACT_PHONE))
            ?: trim((string) Mage::getStoreConfig('general/store_information/phone'));
        $email = trim((string) Mage::getStoreConfig(Maho_StructuredData_Helper_Data::XML_PATH_ORGANIZATION_CONTACT_EMAIL));

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

        return [
            '@type' => 'PostalAddress',
            'streetAddress' => preg_replace('/\s+/', ' ', $address),
        ];
    }
}
